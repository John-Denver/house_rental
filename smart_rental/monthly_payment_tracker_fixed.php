<?php
/**
 * Monthly Payment Tracker - Fixed Version
 * Simple system to track and allocate monthly payments
 * 
 * Logic:
 * - First payment = First month rent + Security deposit
 * - Subsequent payments = Next unpaid month's rent
 * - Payments are allocated chronologically starting from the booking start date
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

class MonthlyPaymentTracker {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get or create monthly payment records for a booking
     */
    public function getMonthlyPayments($bookingId) {
        // First, ensure monthly payment records exist for this booking
        $this->ensureMonthlyRecordsExist($bookingId);
        
        // Get all monthly payments for this booking
        $stmt = $this->conn->prepare("
            SELECT 
                id,
                month,
                amount,
                status,
                payment_date,
                payment_method,
                mpesa_receipt_number,
                notes,
                is_first_payment,
                payment_type
            FROM monthly_rent_payments 
            WHERE booking_id = ?
            ORDER BY month ASC
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Ensure monthly payment records exist for a booking
     */
    private function ensureMonthlyRecordsExist($bookingId) {
        // Get booking details
        $stmt = $this->conn->prepare("
            SELECT id, start_date, end_date, monthly_rent, security_deposit
            FROM rental_bookings 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        
        if (!$booking) {
            throw new Exception("Booking not found");
        }
        
        // Check if monthly records already exist
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM monthly_rent_payments 
            WHERE booking_id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] == 0) {
            // Create monthly payment records
            $this->createMonthlyRecords($booking);
        }
    }
    
    /**
     * Create monthly payment records for a booking
     */
    private function createMonthlyRecords($booking) {
        $start = new DateTime($booking['start_date']);
        $end = new DateTime($booking['end_date']);
        $current = clone $start;
        
        while ($current <= $end) {
            $month = $current->format('Y-m-01');
            $isFirstPayment = ($current == $start);
            
            // Calculate amount
            $amount = $booking['monthly_rent'];
            if ($isFirstPayment) {
                $amount += $booking['security_deposit'];
            }
            
            // Insert monthly payment record
            $stmt = $this->conn->prepare("
                INSERT INTO monthly_rent_payments 
                (booking_id, month, amount, status, payment_type, is_first_payment, notes)
                VALUES (?, ?, ?, 'unpaid', ?, ?, ?)
            ");
            
            $paymentType = $isFirstPayment ? 'initial_payment' : 'monthly_rent';
            $notes = $isFirstPayment ? 'First month rent + security deposit' : 'Monthly rent payment';
            
            // Fixed bind_param - 6 parameters for 6 placeholders
            $stmt->bind_param('isdsisss', 
                $booking['id'],
                $month,
                $amount,
                $paymentType,
                $isFirstPayment ? 1 : 0,
                $notes
            );
            $stmt->execute();
            
            $current->add(new DateInterval('P1M'));
        }
    }
    
    /**
     * Allocate a payment to the next unpaid month
     */
    public function allocatePayment($bookingId, $paymentAmount, $paymentDate, $paymentMethod, $transactionId = null, $mpesaReceipt = null) {
        // Get the next unpaid month
        $nextUnpaidMonth = $this->getNextUnpaidMonth($bookingId);
        
        if (!$nextUnpaidMonth) {
            throw new Exception("No unpaid months found for this booking");
        }
        
        // Update the monthly payment record
        $stmt = $this->conn->prepare("
            UPDATE monthly_rent_payments 
            SET status = 'paid',
                payment_date = ?,
                payment_method = ?,
                transaction_id = ?,
                mpesa_receipt_number = ?,
                notes = CONCAT(IFNULL(notes, ''), ' - Paid on ', ?)
            WHERE booking_id = ? AND month = ?
        ");
        
        $stmt->bind_param('sssssss', 
            $paymentDate,
            $paymentMethod,
            $transactionId,
            $mpesaReceipt,
            $paymentDate,
            $bookingId,
            $nextUnpaidMonth
        );
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'allocated_month' => $nextUnpaidMonth,
                'amount' => $paymentAmount,
                'message' => "Payment allocated to " . date('F Y', strtotime($nextUnpaidMonth))
            ];
        } else {
            throw new Exception("Failed to allocate payment: " . $stmt->error);
        }
    }
    
    /**
     * Get the next unpaid month for a booking
     */
    private function getNextUnpaidMonth($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT month 
            FROM monthly_rent_payments 
            WHERE booking_id = ? AND status = 'unpaid'
            ORDER BY month ASC 
            LIMIT 1
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result ? $result['month'] : null;
    }
    
    /**
     * Get payment summary for a booking
     */
    public function getPaymentSummary($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total_months,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_months,
                SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_months,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN status = 'unpaid' THEN amount ELSE 0 END) as total_unpaid
            FROM monthly_rent_payments 
            WHERE booking_id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get next payment due
     */
    public function getNextPaymentDue($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT month, amount 
            FROM monthly_rent_payments 
            WHERE booking_id = ? AND status = 'unpaid'
            ORDER BY month ASC 
            LIMIT 1
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
}

// Test the system
if (isset($_GET['test'])) {
    echo "<h2>Monthly Payment Tracker Test - Fixed Version</h2>";
    
    $tracker = new MonthlyPaymentTracker($conn);
    
    // Test with a booking ID
    $testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 6;
    
    echo "<h3>Testing with Booking ID: $testBookingId</h3>";
    
    try {
        // Get monthly payments
        $payments = $tracker->getMonthlyPayments($testBookingId);
        
        echo "<h4>Monthly Payments:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Month</th><th>Amount</th><th>Status</th><th>Type</th><th>Payment Date</th>";
        echo "</tr>";
        
        foreach ($payments as $payment) {
            $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
            echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
            echo "<td>" . $payment['payment_type'] . "</td>";
            echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Get payment summary
        $summary = $tracker->getPaymentSummary($testBookingId);
        echo "<h4>Payment Summary:</h4>";
        echo "<ul>";
        echo "<li>Total Months: " . $summary['total_months'] . "</li>";
        echo "<li>Paid Months: " . $summary['paid_months'] . "</li>";
        echo "<li>Unpaid Months: " . $summary['unpaid_months'] . "</li>";
        echo "<li>Total Paid: KSh " . number_format($summary['total_paid'], 2) . "</li>";
        echo "<li>Total Unpaid: KSh " . number_format($summary['total_unpaid'], 2) . "</li>";
        echo "</ul>";
        
        // Get next payment due
        $nextPayment = $tracker->getNextPaymentDue($testBookingId);
        if ($nextPayment) {
            echo "<h4>Next Payment Due:</h4>";
            echo "<p>" . date('F Y', strtotime($nextPayment['month'])) . " - KSh " . number_format($nextPayment['amount'], 2) . "</p>";
        } else {
            echo "<h4>Next Payment Due:</h4>";
            echo "<p>All payments completed!</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
}
?> 