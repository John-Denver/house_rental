<?php
/**
 * M-Pesa Payment Status Checker
 * Checks the status of M-Pesa payments
 */

// Start output buffering to ensure clean JSON responses
ob_start();

require_once 'mpesa_config.php';

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
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
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
        WHERE pr.checkout_request_id = ? AND rb.user_id = ?
    ");
    $stmt->bind_param('si', $checkout_request_id, $_SESSION['user_id']);
    $stmt->execute();
    $payment_request = $stmt->get_result()->fetch_assoc();
    
    // Log the current payment request status for debugging
    error_log("Payment request status for checkout_request_id: $checkout_request_id - Status: " . ($payment_request['status'] ?? 'not found'));

    if (!$payment_request) {
        http_response_code(404);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Payment request not found']);
        exit;
    }

    // If payment is already completed or failed, return the status
    if ($payment_request['status'] === 'completed') {
        // Log the completed payment for debugging
        error_log("Payment already completed for checkout_request_id: $checkout_request_id - Receipt: " . $payment_request['mpesa_receipt_number']);
        
        // Get booking status to ensure consistency
        $bookingStmt = $conn->prepare("
            SELECT status, payment_status 
            FROM rental_bookings 
            WHERE id = ?
        ");
        $bookingStmt->bind_param('i', $payment_request['booking_id']);
        $bookingStmt->execute();
        $booking = $bookingStmt->get_result()->fetch_assoc();
        
        // Check for status inconsistency
        if ($booking && $booking['payment_status'] !== 'paid') {
            error_log("Status inconsistency detected for booking {$payment_request['booking_id']}: payment completed but booking not marked as paid");
            
            // Attempt to fix the inconsistency
            $fixStmt = $conn->prepare("
                UPDATE rental_bookings 
                SET payment_status = 'paid', status = 'confirmed', updated_at = NOW()
                WHERE id = ? AND payment_status != 'paid'
            ");
            $fixStmt->bind_param('i', $payment_request['booking_id']);
            $fixStmt->execute();
            
            if ($fixStmt->affected_rows > 0) {
                error_log("Status inconsistency fixed for booking {$payment_request['booking_id']}");
            }
        }
        
        ob_clean();
        header('Content-Type: application/json');
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
        ob_clean();
        header('Content-Type: application/json');
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to get M-Pesa access token']);
        exit;
    }
    
    // Log that we're checking M-Pesa API
    error_log("Checking M-Pesa API for checkout_request_id: $checkout_request_id");

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
        
        // Log the M-Pesa response for debugging
        error_log("M-Pesa STK Query Response for checkout_request_id: $checkout_request_id - " . json_encode($result));
        
        // Check if we have a valid response
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
                // Log successful payment detection
                error_log("Payment successful detected via API for checkout_request_id: $checkout_request_id");
                
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
                
                        // Include payment tracking helper
        require_once 'includes/payment_tracking_helper.php';
        
        // Check if this is the first payment for this booking
        $hasFirstPayment = hasFirstPaymentBeenMade($conn, $payment_request['booking_id']);
        
        if (!$hasFirstPayment) {
            // This is the initial payment (security deposit + first month rent)
            $breakdown = getInitialPaymentBreakdown($conn, $payment_request['booking_id']);
            $securityDepositAmount = $breakdown['security_deposit'];
            $monthlyRentAmount = $breakdown['monthly_rent'];
            
            // Record initial payment
            recordInitialPayment(
                $conn, 
                $payment_request['booking_id'], 
                $payment_request['amount'], 
                $securityDepositAmount, 
                $monthlyRentAmount, 
                'M-Pesa', 
                $transaction_id, 
                null, 
                'M-Pesa Initial Payment - Checkout Request: ' . $checkout_request_id
            );
        } else {
            // This is a monthly rent payment
            $nextMonth = getNextUnpaidMonth($conn, $payment_request['booking_id']);
            
            // Record monthly payment
            recordMonthlyPayment(
                $conn, 
                $payment_request['booking_id'], 
                $nextMonth, 
                $payment_request['amount'], 
                'M-Pesa', 
                $transaction_id, 
                null, 
                'M-Pesa Monthly Payment - Checkout Request: ' . $checkout_request_id
            );
        }

                ob_clean();
                header('Content-Type: application/json');
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
                // Check if it's a cancelled payment
                $isCancelled = ($result_code === '1032' || $result_code === '1039');
                $status = $isCancelled ? 'cancelled' : 'failed';
                $message = $isCancelled ? 'Payment was cancelled by user' : (getMpesaErrorMessage($result_code) ?? $result_desc);
                
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'status' => $status,
                        'message' => $message,
                        'error_code' => $result_code,
                        'is_cancelled' => $isCancelled
                    ]
                ]);
            }
        } else {
            // No ResultCode in response - this usually means the payment is still pending
            error_log("No ResultCode in M-Pesa response - payment still pending for checkout_request_id: $checkout_request_id");
            
            // Still pending - check if it's been too long (STK push expires after 3 minutes)
            $requestTime = strtotime($payment_request['created_at']);
            $currentTime = time();
            $timeDiff = $currentTime - $requestTime;
            
            if ($timeDiff > 180) { // 3 minutes - give more time for user to complete payment
                // STK push has likely expired
                $stmt = $conn->prepare("
                    UPDATE mpesa_payment_requests 
                    SET 
                        result_code = '1037',
                        result_desc = 'STK Push expired - no response received after 3 minutes',
                        status = 'failed',
                        updated_at = NOW()
                    WHERE checkout_request_id = ?
                ");
                $stmt->bind_param('s', $checkout_request_id);
                $stmt->execute();
                
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'status' => 'failed',
                        'message' => 'Payment request expired. Please try again.',
                        'error_code' => '1037',
                        'is_expired' => true
                    ]
                ]);
            } else {
                // Still pending - provide encouraging message
                $timeRemaining = 180 - $timeDiff;
                $minutes = floor($timeRemaining / 60);
                $seconds = $timeRemaining % 60;
                
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'status' => 'pending',
                        'message' => 'Payment is still being processed. Please check your phone for the M-Pesa prompt and complete the payment.',
                        'time_remaining' => $timeRemaining,
                        'time_formatted' => sprintf('%02d:%02d', $minutes, $seconds)
                    ]
                ]);
            }
        }
    } else {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to check payment status',
            'http_code' => $httpCode,
            'response' => $response
        ]);
    }

} catch (Exception $e) {
    error_log('M-Pesa Payment Status Error: ' . $e->getMessage());
    error_log('M-Pesa Payment Status Error Stack: ' . $e->getTraceAsString());
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