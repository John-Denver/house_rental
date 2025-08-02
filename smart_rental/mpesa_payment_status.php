<?php
/**
 * M-Pesa Payment Status Checker
 * Checks the status of M-Pesa payments
 */

require_once '../config/db.php';
require_once 'mpesa_config.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['checkout_request_id'])) {
    http_response_code(400);
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
        WHERE pr.checkout_request_id = ? AND rb.user_id = ?
    ");
    $stmt->bind_param('si', $checkout_request_id, $_SESSION['user_id']);
    $stmt->execute();
    $payment_request = $stmt->get_result()->fetch_assoc();

    if (!$payment_request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment request not found']);
        exit;
    }

    // If payment is already completed or failed, return the status
    if ($payment_request['status'] === 'completed') {
        echo json_encode([
            'success' => true,
            'data' => [
                'status' => 'completed',
                'receipt_number' => $payment_request['mpesa_receipt_number'],
                'amount' => $payment_request['amount'],
                'message' => 'Payment completed successfully'
            ]
        ]);
        exit;
    }

    if ($payment_request['status'] === 'failed') {
        echo json_encode([
            'success' => true,
            'data' => [
                'status' => 'failed',
                'message' => $payment_request['result_desc'] ?? 'Payment failed'
            ]
        ]);
        exit;
    }

    // If still pending, check with M-Pesa API
    $access_token = getMpesaAccessToken();
    if (!$access_token) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to get M-Pesa access token']);
        exit;
    }

    // Generate M-Pesa password for query
    $password_data = generateMpesaPassword();
    
    // Prepare STK Query request
    $stk_query_data = [
        'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
        'Password' => $password_data['password'],
        'Timestamp' => $password_data['timestamp'],
        'CheckoutRequestID' => $checkout_request_id
    ];

    // Make STK Query request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, MPESA_STK_QUERY_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_query_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        
        if (isset($result['ResultCode'])) {
            $result_code = $result['ResultCode'];
            $result_desc = $result['ResultDesc'];
            
            // Update payment request status based on result
            $status = ($result_code === 0) ? 'completed' : 'failed';
            
            $stmt = $conn->prepare("
                UPDATE mpesa_payment_requests 
                SET 
                    result_code = ?,
                    result_desc = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE checkout_request_id = ?
            ");
            $stmt->bind_param('ssss', $result_code, $result_desc, $status, $checkout_request_id);
            $stmt->execute();

            // If payment was successful, update booking status
            if ($result_code === 0) {
                // Update booking status to paid
                $stmt = $conn->prepare("
                    UPDATE rental_bookings 
                    SET status = 'paid', updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $payment_request['booking_id']);
                $stmt->execute();

                // Record payment in booking_payments table
                $stmt = $conn->prepare("
                    INSERT INTO booking_payments (
                        booking_id, amount, payment_method, transaction_id, 
                        payment_date, status, notes, created_at
                    ) VALUES (?, ?, 'M-Pesa', ?, NOW(), 'completed', ?, NOW())
                ");
                $transaction_id = 'MPESA_' . time();
                $notes = 'M-Pesa Payment - Checkout Request: ' . $checkout_request_id;
                $stmt->bind_param('idss', 
                    $payment_request['booking_id'], 
                    $payment_request['amount'], 
                    $transaction_id, 
                    $notes
                );
                $stmt->execute();
                
                // Record monthly payment
                $currentMonth = date('Y-m-01');
                $stmt = $conn->prepare("
                    INSERT INTO monthly_rent_payments 
                    (booking_id, month, amount, status, payment_date, payment_method, transaction_id, notes)
                    VALUES (?, ?, ?, 'paid', NOW(), 'M-Pesa', ?, ?)
                    ON DUPLICATE KEY UPDATE
                    status = 'paid',
                    payment_date = NOW(),
                    payment_method = 'M-Pesa',
                    transaction_id = VALUES(transaction_id),
                    notes = VALUES(notes),
                    updated_at = CURRENT_TIMESTAMP
                ");
                $monthlyNotes = 'M-Pesa Payment - Checkout Request: ' . $checkout_request_id;
                $stmt->bind_param('isds', 
                    $payment_request['booking_id'], 
                    $currentMonth, 
                    $payment_request['amount'], 
                    $transaction_id, 
                    $monthlyNotes
                );
                $stmt->execute();

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'status' => 'completed',
                        'receipt_number' => $transaction_id,
                        'amount' => $payment_request['amount'],
                        'message' => 'Payment completed successfully'
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'status' => 'failed',
                        'message' => getMpesaErrorMessage($result_code) ?? $result_desc
                    ]
                ]);
            }
        } else {
            // Still pending
            echo json_encode([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                    'message' => 'Payment is still being processed'
                ]
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to check payment status',
            'http_code' => $httpCode,
            'response' => $response
        ]);
    }

} catch (Exception $e) {
    error_log('M-Pesa Payment Status Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}
?> 