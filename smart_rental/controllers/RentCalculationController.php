<?php

class RentCalculationController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Calculate monthly rent for a booking
     * Rent is calculated monthly on a rollover basis
     * If rent was not paid last month, it accumulates to the current month
     */
    public function calculateMonthlyRent($bookingId, $month = null) {
        if (!$month) {
            $month = date('Y-m-01'); // Current month
        }

        // Get booking details
        $booking = $this->getBookingDetails($bookingId);
        if (!$booking) {
            throw new Exception("Booking not found");
        }

        $monthlyRent = $booking['monthly_rent'];
        $previousBalance = $this->getPreviousMonthBalance($bookingId, $month);
        $currentMonthPayments = $this->getCurrentMonthPayments($bookingId, $month);

        // Calculate total due for current month
        $totalDue = $monthlyRent + $previousBalance - $currentMonthPayments;

        return [
            'monthly_rent' => $monthlyRent,
            'previous_balance' => $previousBalance,
            'current_month_payments' => $currentMonthPayments,
            'total_due' => max(0, $totalDue), // Cannot be negative
            'month' => $month
        ];
    }

    /**
     * Get the balance from the previous month
     */
    private function getPreviousMonthBalance($bookingId, $currentMonth) {
        $previousMonth = date('Y-m-01', strtotime($currentMonth . ' -1 month'));
        
        $stmt = $this->conn->prepare("
            SELECT 
                COALESCE(SUM(amount_due), 0) as total_due,
                COALESCE(SUM(amount_paid), 0) as total_paid
            FROM rent_payments 
            WHERE booking_id = ? 
            AND month = ?
        ");
        
        $stmt->bind_param('is', $bookingId, $previousMonth);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return max(0, $result['total_due'] - $result['total_paid']);
    }

    /**
     * Get payments made in the current month
     */
    private function getCurrentMonthPayments($bookingId, $month) {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(amount_paid), 0) as total_paid
            FROM rent_payments 
            WHERE booking_id = ? 
            AND month = ?
        ");
        
        $stmt->bind_param('is', $bookingId, $month);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['total_paid'];
    }

    /**
     * Create or update rent payment record for a month
     */
    public function createRentPaymentRecord($bookingId, $month, $amountDue = null) {
        // Get booking details if amount not provided
        if (!$amountDue) {
            $booking = $this->getBookingDetails($bookingId);
            $amountDue = $booking['monthly_rent'];
        }

        // Check if record already exists
        $stmt = $this->conn->prepare("
            SELECT id FROM rent_payments 
            WHERE booking_id = ? AND month = ?
        ");
        $stmt->bind_param('is', $bookingId, $month);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            // Update existing record
            $stmt = $this->conn->prepare("
                UPDATE rent_payments 
                SET amount_due = ?, updated_at = NOW()
                WHERE booking_id = ? AND month = ?
            ");
            $stmt->bind_param('dis', $amountDue, $bookingId, $month);
        } else {
            // Create new record
            $stmt = $this->conn->prepare("
                INSERT INTO rent_payments (
                    booking_id, month, amount_due, due_date, status, created_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $dueDate = date('Y-m-05', strtotime($month)); // Due on 5th of month
            $stmt->bind_param('isds', $bookingId, $month, $amountDue, $dueDate);
        }

        return $stmt->execute();
    }

    /**
     * Record a rent payment
     */
    public function recordRentPayment($bookingId, $amount, $month, $paymentMethod = 'manual') {
        $this->conn->begin_transaction();
        
        try {
            // Update rent_payments table
            $stmt = $this->conn->prepare("
                UPDATE rent_payments 
                SET amount_paid = amount_paid + ?, 
                    paid_date = NOW(),
                    status = CASE 
                        WHEN (amount_paid + ?) >= amount_due THEN 'paid'
                        ELSE 'partial'
                    END,
                    updated_at = NOW()
                WHERE booking_id = ? AND month = ?
            ");
            $stmt->bind_param('ddis', $amount, $amount, $bookingId, $month);
            $stmt->execute();

            // Record payment in booking_payments
            $stmt = $this->conn->prepare("
                INSERT INTO booking_payments (
                    booking_id, amount, payment_method, status, payment_date
                ) VALUES (?, ?, ?, 'completed', NOW())
            ");
            $stmt->bind_param('ids', $bookingId, $amount, $paymentMethod);
            $stmt->execute();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Get booking details
     */
    private function getBookingDetails($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT b.*, h.price as property_price
            FROM rental_bookings b
            JOIN houses h ON b.house_id = h.id
            WHERE b.id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        // Use property price as monthly rent (fallback if monthly_rent column doesn't exist)
        if ($result) {
            $result['monthly_rent'] = $result['property_price'];
        }
        
        return $result;
    }

    /**
     * Generate monthly rent invoice
     */
    public function generateMonthlyInvoice($bookingId, $month = null) {
        if (!$month) {
            $month = date('Y-m-01');
        }

        $rentCalculation = $this->calculateMonthlyRent($bookingId, $month);
        
        // Create or update rent payment record
        $this->createRentPaymentRecord($bookingId, $month, $rentCalculation['total_due']);
        
        return $rentCalculation;
    }

    /**
     * Get rent payment history for a booking
     */
    public function getRentPaymentHistory($bookingId, $limit = 12) {
        $stmt = $this->conn->prepare("
            SELECT * FROM rent_payments 
            WHERE booking_id = ? 
            ORDER BY month DESC 
            LIMIT ?
        ");
        $stmt->bind_param('ii', $bookingId, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?> 