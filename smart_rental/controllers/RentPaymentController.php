<?php
class RentPaymentController {
    private $conn;
    private $lateFeePercentage = 5.00; // 5% late fee
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Generate monthly rent invoice for a booking
     * Handles rollover of unpaid balances to the next month
     */
    public function generateMonthlyInvoice($bookingId) {
        // Get booking details
        $booking = $this->getBookingDetails($bookingId);
        if (!$booking) {
            throw new Exception('Booking not found');
        }
        
        // Calculate due date (first day of next month)
        $dueDate = new DateTime('first day of next month');
        $dueDateStr = $dueDate->format('Y-m-d');
        
        // Check if invoice already exists for this period
        $stmt = $this->conn->prepare("
            SELECT id FROM rent_payments 
            WHERE booking_id = ? 
            AND YEAR(due_date) = YEAR(?) 
            AND MONTH(due_date) = MONTH(?)
        ");
        $stmt->bind_param('iss', $bookingId, $dueDateStr, $dueDateStr);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            return false; // Invoice already exists
        }
        
        // Calculate any outstanding balance from previous months
        $previousBalance = $this->getOutstandingBalance($bookingId);
        
        // Calculate late fee if payment is overdue (after 5th of the month)
        $today = new DateTime();
        $lateFee = 0;
        $status = 'pending';
        
        // If it's after the 5th of the month, add late fee
        if ($today->format('j') > 5) {
            $lateFee = $booking['monthly_rent'] * ($this->lateFeePercentage / 100);
            $status = 'overdue';
        }
        
        // If there's a previous balance, mark as overdue
        if ($previousBalance > 0) {
            $status = 'overdue';
        }
        
        // Calculate total due (monthly rent + previous balance + late fee)
        $totalDue = $booking['monthly_rent'] + $previousBalance + $lateFee;
        
        // Insert new rent payment record
        $stmt = $this->conn->prepare("
            INSERT INTO rent_payments 
            (booking_id, amount_due, due_date, balance_forwarded, late_fee, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param('idddds', 
            $bookingId, 
            $totalDue,
            $dueDateStr,
            $previousBalance,
            $lateFee,
            $status
        );
        
        return $stmt->execute();
    }
    
    /**
     * Process a rent payment
     * Handles partial payments and applies remaining amounts to next invoice
     */
    public function processPayment($bookingId, $amount, $paymentMethod = 'mpesa') {
        $this->conn->begin_transaction();
        
        try {
            // Get the most recent unpaid or partially paid invoice
            $stmt = $this->conn->prepare("
                SELECT * FROM rent_payments 
                WHERE booking_id = ? AND status IN ('pending', 'partial', 'overdue')
                ORDER BY due_date ASC 
                LIMIT 1 FOR UPDATE
            ");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $invoice = $stmt->get_result()->fetch_assoc();
            
            if (!$invoice) {
                // If no pending invoices, create a new one for the current month
                $this->generateMonthlyInvoice($bookingId);
                // Try to get the newly created invoice
                $stmt->execute();
                $invoice = $stmt->get_result()->fetch_assoc();
                
                if (!$invoice) {
                    throw new Exception('Failed to create or retrieve invoice');
                }
            }
            
            $remainingBalance = $invoice['amount_due'] - $invoice['amount_paid'];
            $paymentAmount = min($amount, $remainingBalance);
            
            // Update invoice
            $newAmountPaid = $invoice['amount_paid'] + $paymentAmount;
            $status = $newAmountPaid >= $invoice['amount_due'] ? 'paid' : 'partial';
            
            $stmt = $this->conn->prepare("
                UPDATE rent_payments 
                SET amount_paid = ?, 
                    status = ?,
                    payment_date = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('dsi', $newAmountPaid, $status, $invoice['id']);
            $stmt->execute();
            
            // Record payment in payment history
            $this->recordPayment($bookingId, $paymentAmount, $paymentMethod, $invoice['id']);
            
            // Update booking last payment date
            $this->updateLastPaymentDate($bookingId);
            
            // If there's remaining amount, apply to next invoice
            $remainingAmount = $amount - $paymentAmount;
            if ($remainingAmount > 0) {
                // Generate next month's invoice if it doesn't exist
                $this->generateMonthlyInvoice($bookingId);
                // Process remaining amount with next invoice
                $this->processPayment($bookingId, $remainingAmount, $paymentMethod);
            }
            
            $this->conn->commit();
            return [
                'success' => true,
                'amount_applied' => $paymentAmount,
                'remaining_balance' => $this->getOutstandingBalance($bookingId)
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Get outstanding balance for a booking
     */
    public function getOutstandingBalance($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT SUM(amount_due - amount_paid) as balance
            FROM rent_payments 
            WHERE booking_id = ? 
            AND status IN ('pending', 'partial', 'overdue')
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return (float)($result['balance'] ?? 0);
    }
    
    /**
     * Get payment history for a booking
     */
    public function getPaymentHistory($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM rent_payments 
            WHERE booking_id = ? 
            ORDER BY due_date DESC
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Record payment in payment history
     */
    private function recordPayment($bookingId, $amount, $method, $invoiceId = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO payment_history 
            (booking_id, invoice_id, amount, payment_method, status, created_at)
            VALUES (?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt->bind_param('iids', $bookingId, $invoiceId, $amount, $method);
        return $stmt->execute();
    }
    
    /**
     * Update last payment date for a booking
     */
    private function updateLastPaymentDate($bookingId) {
        $stmt = $this->conn->prepare("
            UPDATE bookings 
            SET last_payment_date = CURDATE() 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        return $stmt->execute();
    }
    
    /**
     * Get booking details
     */
    private function getBookingDetails($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM bookings WHERE id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Process late fees for overdue payments
     */
    public function processLateFees() {
        $stmt = $this->conn->query("
            SELECT * FROM rent_payments 
            WHERE status IN ('pending', 'partial')
            AND due_date < CURDATE()
            AND (late_fee IS NULL OR late_fee = 0)
            FOR UPDATE
        ");
        
        while ($invoice = $stmt->fetch_assoc()) {
            $lateFee = $invoice['amount_due'] * ($this->lateFeePercentage / 100);
            $newAmount = $invoice['amount_due'] + $lateFee;
            
            $update = $this->conn->prepare("
                UPDATE rent_payments 
                SET amount_due = ?, 
                    late_fee = ?,
                    status = 'overdue'
                WHERE id = ?
            
            ");
            $update->bind_param('ddi', $newAmount, $lateFee, $invoice['id']);
            $update->execute();
        }
    }
    
    /**
     * Generate invoices for all active bookings (to be run monthly)
     */
    public function generateMonthlyInvoices() {
        // Get all active bookings
        $stmt = $this->conn->query("
            SELECT id FROM bookings 
            WHERE status = 'approved' 
            AND (last_payment_date IS NULL OR last_payment_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH))
        ");
        
        $count = 0;
        while ($booking = $stmt->fetch_assoc()) {
            if ($this->generateMonthlyInvoice($booking['id'])) {
                $count++;
            }
        }
        
        return $count;
    }
}
