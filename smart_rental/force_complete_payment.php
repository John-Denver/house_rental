<?php
/**
 * Force Complete Payment
 * Manually complete a payment for testing purposes
 */

// Start output buffering
ob_start();

// Database connection
$host = "localhost";
$dbname = "house_rental";
$username = "root";
$password = "";

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    http_response_code(500);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['checkout_request_id'])) {
    http_response_code(400);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing checkout request ID']);
    exit;
}

$checkout_request_id = $input['checkout_request_id'];

try {
    // Get payment request details
    $stmt = $conn->prepare("
        SELECT pr.*, rb.house_id, rb.user_id, rb.status as booking_status
        FROM mpesa_payment_requests pr
        JOIN rental_bookings rb ON pr.booking_id = rb.id
        WHERE pr.checkout_request_id = ?
    ");
    $stmt->bind_param('s', $checkout_request_id);
    $stmt->execute();
    $payment_request = $stmt->get_result()->fetch_assoc();
    
    if (!$payment_request) {
        http_response_code(404);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Payment request not found']);
        exit;
    }
    
    // Force complete the payment
    $stmt = $conn->prepare("
        UPDATE mpesa_payment_requests 
        SET 
            result_code = '0',
            result_desc = 'Success (Force Completed)',
            status = 'completed',
            mpesa_receipt_number = 'FORCE_' . ?,
            updated_at = NOW()
        WHERE checkout_request_id = ?
    ");
    $receipt = 'FORCE_' . time();
    $stmt->bind_param('ss', $receipt, $checkout_request_id);
    $stmt->execute();
    
    // Update booking status
    $stmt = $conn->prepare("
        UPDATE rental_bookings 
        SET status = 'confirmed', payment_status = 'paid', updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $payment_request['booking_id']);
    $stmt->execute();
    
    // Record payment in booking_payments table
    try {
        $stmt = $conn->prepare("
            INSERT INTO booking_payments (
                booking_id, amount, payment_method, transaction_id, 
                payment_date, status, notes, created_at
            ) VALUES (?, ?, 'M-Pesa', ?, NOW(), 'completed', ?, NOW())
        ");
        $transaction_id = 'MPESA_FORCE_' . time();
        $notes = 'M-Pesa Payment (Force Completed) - Checkout Request: ' . $checkout_request_id;
        $stmt->bind_param('idss', 
            $payment_request['booking_id'], 
            $payment_request['amount'], 
            $transaction_id, 
            $notes
        );
        $stmt->execute();
    } catch (Exception $e) {
        // Continue without recording in booking_payments
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Payment force completed successfully',
        'data' => [
            'status' => 'completed',
            'receipt_number' => $receipt,
            'amount' => $payment_request['amount'],
            'message' => 'Payment completed successfully (force completed)'
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Force Complete Payment Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}
?> 