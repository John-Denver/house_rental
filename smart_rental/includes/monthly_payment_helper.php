<?php
/**
 * Monthly Payment Helper Functions
 * Handles monthly rent payment logic
 */

/**
 * Get current month payment status for a booking
 */
function getCurrentMonthPaymentStatus($conn, $bookingId) {
    $currentMonth = date('Y-m-01'); // First day of current month
    
    $stmt = $conn->prepare("
        SELECT status, payment_date, amount 
        FROM monthly_rent_payments 
        WHERE booking_id = ? AND month = ?
    ");
    $stmt->bind_param('is', $bookingId, $currentMonth);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        return $result;
    }
    
    // If no record exists, check if it's overdue
    $today = date('Y-m-d');
    $status = ($today > date('Y-m-15')) ? 'overdue' : 'unpaid'; // Consider overdue after 15th
    
    return [
        'status' => $status,
        'payment_date' => null,
        'amount' => null
    ];
}

/**
 * Get all monthly payments for a booking
 */
function getMonthlyPayments($conn, $bookingId) {
    $stmt = $conn->prepare("
        SELECT 
            month,
            amount,
            status,
            payment_date,
            payment_method,
            mpesa_receipt_number,
            notes
        FROM monthly_rent_payments 
        WHERE booking_id = ?
        ORDER BY month DESC
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Generate monthly payment records for a booking
 */
function generateMonthlyPayments($conn, $bookingId, $startDate, $endDate, $monthlyRent) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $current = clone $start;
    
    while ($current <= $end) {
        $month = $current->format('Y-m-01');
        
        // Check if record already exists
        $stmt = $conn->prepare("
            SELECT id FROM monthly_rent_payments 
            WHERE booking_id = ? AND month = ?
        ");
        $stmt->bind_param('is', $bookingId, $month);
        $stmt->execute();
        
        if (!$stmt->get_result()->fetch_assoc()) {
            // Insert new monthly payment record
            $insertStmt = $conn->prepare("
                INSERT INTO monthly_rent_payments (booking_id, month, amount, status)
                VALUES (?, ?, ?, 'unpaid')
            ");
            $insertStmt->bind_param('isd', $bookingId, $month, $monthlyRent);
            $insertStmt->execute();
        }
        
        $current->add(new DateInterval('P1M'));
    }
}

/**
 * Record a monthly payment
 */
function recordMonthlyPayment($conn, $bookingId, $month, $amount, $paymentMethod, $transactionId = null, $mpesaReceiptNumber = null, $notes = null) {
    $paymentDate = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("
        INSERT INTO monthly_rent_payments 
        (booking_id, month, amount, status, payment_date, payment_method, transaction_id, mpesa_receipt_number, notes)
        VALUES (?, ?, ?, 'paid', ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        status = 'paid',
        payment_date = VALUES(payment_date),
        payment_method = VALUES(payment_method),
        transaction_id = VALUES(transaction_id),
        mpesa_receipt_number = VALUES(mpesa_receipt_number),
        notes = VALUES(notes),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param('isdsisss', 
        $bookingId, 
        $month, 
        $amount, 
        $paymentDate, 
        $paymentMethod, 
        $transactionId, 
        $mpesaReceiptNumber, 
        $notes
    );
    
    return $stmt->execute();
}

/**
 * Get payment status badge HTML
 */
function getPaymentStatusBadge($status) {
    $statusClass = [
        'paid' => 'success',
        'unpaid' => 'warning',
        'overdue' => 'danger'
    ][$status] ?? 'secondary';
    
    $statusText = [
        'paid' => 'Paid',
        'unpaid' => 'Unpaid',
        'overdue' => 'Overdue'
    ][$status] ?? ucfirst($status);
    
    return '<span class="badge bg-' . $statusClass . '">' . $statusText . '</span>';
}

/**
 * Format month for display
 */
function formatMonth($month) {
    return date('F Y', strtotime($month));
}

/**
 * Check if a month is in the past
 */
function isPastMonth($month) {
    return date('Y-m-01') > $month;
}

/**
 * Check if a month is current
 */
function isCurrentMonth($month) {
    return date('Y-m-01') === $month;
}
?> 