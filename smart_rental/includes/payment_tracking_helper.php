<?php
/**
 * Payment Tracking Helper Functions
 * Handles payment tracking logic for initial payments vs monthly payments
 */

/**
 * Record initial payment (security deposit + first month rent)
 */
function recordInitialPayment($conn, $bookingId, $amount, $securityDepositAmount, $monthlyRentAmount, $paymentMethod, $transactionId = null, $mpesaReceiptNumber = null, $notes = null) {
    $paymentDate = date('Y-m-d H:i:s');
    
    // Get the first month of the booking
    $stmt = $conn->prepare("
        SELECT start_date FROM rental_bookings WHERE id = ?
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    $firstMonth = date('Y-m-01', strtotime($booking['start_date']));
    
    // Record in payment_tracking table
    $stmt = $conn->prepare("
        INSERT INTO payment_tracking
        (booking_id, payment_type, amount, security_deposit_amount, monthly_rent_amount, month, is_first_payment, status, payment_date, payment_method, transaction_id, mpesa_receipt_number, notes)
        VALUES (?, 'initial_payment', ?, ?, ?, ?, 1, 'completed', ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('iddssssss',
        $bookingId,
        $amount,
        $securityDepositAmount,
        $monthlyRentAmount,
        $firstMonth,
        $paymentDate,
        $paymentMethod,
        $transactionId,
        $mpesaReceiptNumber,
        $notes
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to record initial payment: " . $stmt->error);
    }
    
    // Record in monthly_rent_payments table for the first month
    $stmt = $conn->prepare("
        INSERT INTO monthly_rent_payments
        (booking_id, month, amount, status, payment_type, is_first_payment, security_deposit_amount, monthly_rent_amount, payment_date, payment_method, transaction_id, mpesa_receipt_number, notes)
        VALUES (?, ?, ?, 'paid', 'initial_payment', 1, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        status = 'paid',
        payment_type = 'initial_payment',
        is_first_payment = 1,
        security_deposit_amount = VALUES(security_deposit_amount),
        monthly_rent_amount = VALUES(monthly_rent_amount),
        payment_date = VALUES(payment_date),
        payment_method = VALUES(payment_method),
        transaction_id = VALUES(transaction_id),
        mpesa_receipt_number = VALUES(mpesa_receipt_number),
        notes = VALUES(notes),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param('isddssssss',
        $bookingId,
        $firstMonth,
        $monthlyRentAmount, // Only the monthly rent amount for the monthly_rent_payments table
        $securityDepositAmount,
        $monthlyRentAmount,
        $paymentDate,
        $paymentMethod,
        $transactionId,
        $mpesaReceiptNumber,
        $notes
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to record monthly payment: " . $stmt->error);
    }
    
    return true;
}

/**
 * Record pre-payment for next unpaid month
 */
function recordPrePayment($conn, $bookingId, $amount, $paymentMethod, $transactionId = null, $mpesaReceiptNumber = null, $notes = null) {
    $paymentDate = date('Y-m-d H:i:s');
    
    // Get the next unpaid month
    $nextUnpaidMonth = getNextUnpaidMonth($conn, $bookingId);
    
    if (!$nextUnpaidMonth) {
        throw new Exception('No unpaid months found for pre-payment');
    }
    
    // Record in payment_tracking table
    $stmt = $conn->prepare("
        INSERT INTO payment_tracking
        (booking_id, payment_type, amount, monthly_rent_amount, month, is_first_payment, status, payment_date, payment_method, transaction_id, mpesa_receipt_number, notes)
        VALUES (?, 'monthly_rent', ?, ?, ?, 0, 'completed', ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('idssssssss',
        $bookingId,
        $amount,
        $amount,
        $nextUnpaidMonth,
        $paymentDate,
        $paymentMethod,
        $transactionId,
        $mpesaReceiptNumber,
        $notes
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to record payment tracking: " . $stmt->error);
    }
    
    // Record in monthly_rent_payments table
    $stmt = $conn->prepare("
        INSERT INTO monthly_rent_payments
        (booking_id, month, amount, status, payment_type, is_first_payment, monthly_rent_amount, payment_date, payment_method, transaction_id, mpesa_receipt_number, notes)
        VALUES (?, ?, ?, 'paid', 'monthly_rent', 0, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        status = 'paid',
        payment_type = 'monthly_rent',
        is_first_payment = 0,
        monthly_rent_amount = VALUES(monthly_rent_amount),
        payment_date = VALUES(payment_date),
        payment_method = VALUES(payment_method),
        transaction_id = VALUES(transaction_id),
        mpesa_receipt_number = VALUES(mpesa_receipt_number),
        notes = VALUES(notes),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param('isdsisssss',
        $bookingId,
        $nextUnpaidMonth,
        $amount,
        $amount,
        $paymentDate,
        $paymentMethod,
        $transactionId,
        $mpesaReceiptNumber,
        $notes
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to record monthly payment: " . $stmt->error);
    }
    
    return [
        'success' => true,
        'month_paid' => $nextUnpaidMonth,
        'message' => 'Pre-payment recorded for ' . date('F Y', strtotime($nextUnpaidMonth))
    ];
}

/**
 * Record monthly rent payment (for subsequent months)
 */
function recordMonthlyPayment($conn, $bookingId, $month, $amount, $paymentMethod, $transactionId = null, $mpesaReceiptNumber = null, $notes = null) {
    $paymentDate = date('Y-m-d H:i:s');
    
    // Check if this is the first payment
    $stmt = $conn->prepare("
        SELECT COUNT(*) as paid_count FROM monthly_rent_payments 
        WHERE booking_id = ? AND status = 'paid'
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $isFirstPayment = ($result['paid_count'] == 0);
    
    // Record in payment_tracking table
    $stmt = $conn->prepare("
        INSERT INTO payment_tracking
        (booking_id, payment_type, amount, monthly_rent_amount, month, is_first_payment, status, payment_date, payment_method, transaction_id, mpesa_receipt_number, notes)
        VALUES (?, 'monthly_rent', ?, ?, ?, ?, 'completed', ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('idssssssss',
        $bookingId,
        $amount,
        $amount,
        $month,
        $isFirstPayment ? 1 : 0,
        $paymentDate,
        $paymentMethod,
        $transactionId,
        $mpesaReceiptNumber,
        $notes
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to record payment tracking: " . $stmt->error);
    }
    
    // Record in monthly_rent_payments table
    $stmt = $conn->prepare("
        INSERT INTO monthly_rent_payments
        (booking_id, month, amount, status, payment_type, is_first_payment, monthly_rent_amount, payment_date, payment_method, transaction_id, mpesa_receipt_number, notes)
        VALUES (?, ?, ?, 'paid', 'monthly_rent', ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        status = 'paid',
        payment_type = 'monthly_rent',
        is_first_payment = VALUES(is_first_payment),
        monthly_rent_amount = VALUES(monthly_rent_amount),
        payment_date = VALUES(payment_date),
        payment_method = VALUES(payment_method),
        transaction_id = VALUES(transaction_id),
        mpesa_receipt_number = VALUES(mpesa_receipt_number),
        notes = VALUES(notes),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param('isdsisssss',
        $bookingId,
        $month,
        $amount,
        $isFirstPayment ? 1 : 0,
        $amount,
        $paymentDate,
        $paymentMethod,
        $transactionId,
        $mpesaReceiptNumber,
        $notes
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to record monthly payment: " . $stmt->error);
    }
    
    return true;
}

/**
 * Get next unpaid month for a booking
 */
function getNextUnpaidMonth($conn, $bookingId) {
    $stmt = $conn->prepare("
        SELECT get_next_unpaid_month(?) as next_month
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['next_month'];
}

/**
 * Check if first payment has been made
 */
function hasFirstPaymentBeenMade($conn, $bookingId) {
    $stmt = $conn->prepare("
        SELECT has_first_payment_been_made(?) as has_first_payment
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['has_first_payment'] == 1;
}

/**
 * Get payment summary for a booking
 */
function getPaymentSummary($conn, $bookingId) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_payments,
            SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_payments,
            SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_payments,
            SUM(amount) as total_amount,
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount,
            SUM(security_deposit_amount) as total_security_deposit,
            SUM(monthly_rent_amount) as total_monthly_rent
        FROM monthly_rent_payments 
        WHERE booking_id = ?
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Determine payment type based on booking status and payment history
 */
function determinePaymentType($conn, $bookingId) {
    // Check if first payment has been made
    $hasFirstPayment = hasFirstPaymentBeenMade($conn, $bookingId);
    
    if (!$hasFirstPayment) {
        return 'initial_payment';
    } else {
        return 'monthly_rent';
    }
}

/**
 * Get the amount breakdown for initial payment
 */
function getInitialPaymentBreakdown($conn, $bookingId) {
    $stmt = $conn->prepare("
        SELECT 
            rb.monthly_rent,
            rb.security_deposit,
            0 as additional_fees,
            (rb.monthly_rent + rb.security_deposit) as total_amount
        FROM rental_bookings rb
        WHERE rb.id = ?
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    
    $result = $stmt->get_result()->fetch_assoc();
    
    // If security_deposit is empty or 0, use monthly_rent as default
    if (empty($result['security_deposit']) || $result['security_deposit'] == 0) {
        $result['security_deposit'] = $result['monthly_rent'];
        $result['total_amount'] = $result['monthly_rent'] * 2; // monthly_rent + security_deposit
    }
    
    return $result;
}

/**
 * Get monthly rent amount for a booking
 */
function getMonthlyRentAmount($conn, $bookingId) {
    $stmt = $conn->prepare("
        SELECT monthly_rent FROM rental_bookings WHERE id = ?
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['monthly_rent'] ?? 0;
}


?> 