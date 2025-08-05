<?php
/**
 * M-Pesa STK Push Handler
 * Initiates STK Push for rental payments
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
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

// Log the raw input for debugging
error_log('M-Pesa STK Push - Raw input: ' . $raw_input);
error_log('M-Pesa STK Push - Parsed input: ' . json_encode($input));

if (!$input) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid JSON data',
        'raw_input' => $raw_input,
        'json_error' => json_last_error_msg()
    ]);
    exit;
}

// Validate required fields
$required_fields = ['booking_id', 'phone_number', 'amount'];
$missing_fields = array_diff($required_fields, array_keys($input));

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit;
}

$booking_id = intval($input['booking_id']);
$phone_number = $input['phone_number'];
$amount = floatval($input['amount']);

// Validate amount
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid amount']);
    exit;
}

try {
    // Get booking details
    $stmt = $conn->prepare("
        SELECT rb.*, h.house_no, h.price as property_price, u.username as tenant_name
        FROM rental_bookings rb
        JOIN houses h ON rb.house_id = h.id
        JOIN users u ON rb.user_id = u.id
        WHERE rb.id = ? AND rb.user_id = ?
    ");
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    // Check if booking is already paid
    if ($booking['status'] === 'paid') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Booking is already paid']);
        exit;
    }

    // Get M-Pesa access token
    $access_token = getMpesaAccessToken();
    if (!$access_token) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to get M-Pesa access token']);
        exit;
    }

    // Generate M-Pesa password
    $password_data = generateMpesaPassword();
    
    // Format phone number
    $formatted_phone = formatPhoneNumber($phone_number);
    
    // Generate unique reference
    $reference = 'RENTAL_' . $booking_id . '_' . time();
    
    // Prepare STK Push request
    $stk_push_data = [
        'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
        'Password' => $password_data['password'],
        'Timestamp' => $password_data['timestamp'],
        'TransactionType' => MPESA_TRANSACTION_TYPE,
        'Amount' => intval($amount),
        'PartyA' => $formatted_phone,
        'PartyB' => MPESA_PARTYB,
        'PhoneNumber' => $formatted_phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $reference,
        'TransactionDesc' => 'Rental Payment - ' . $booking['house_no']
    ];

    // Make STK Push request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, MPESA_STK_PUSH_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_push_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        
        if (isset($result['CheckoutRequestID'])) {
            // Store payment request in database
            $stmt = $conn->prepare("
                INSERT INTO mpesa_payment_requests (
                    booking_id, checkout_request_id, phone_number, amount, 
                    reference, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->bind_param('issds', 
                $booking_id, 
                $result['CheckoutRequestID'], 
                $phone_number, 
                $amount, 
                $reference
            );
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'STK Push sent successfully',
                'data' => [
                    'checkout_request_id' => $result['CheckoutRequestID'],
                    'merchant_request_id' => $result['MerchantRequestID'],
                    'reference' => $reference,
                    'amount' => $amount,
                    'phone_number' => $phone_number
                ]
            ]);
        } else {
            // Check for specific M-Pesa error codes
            $errorMessage = 'Failed to initiate STK Push';
            $isProcessing = false;
            
            if (isset($result['errorCode'])) {
                $errorMessage = getMpesaErrorMessage($result['errorCode']);
                // Check if this is a "still processing" error
                if (in_array($result['errorCode'], ['2001', '2002', '2003', '2004'])) {
                    $isProcessing = true;
                }
            } elseif (isset($result['errorMessage'])) {
                $errorMessage = $result['errorMessage'];
                // Check if message contains processing keywords
                if (strpos(strtolower($result['errorMessage']), 'processing') !== false) {
                    $isProcessing = true;
                }
            }
            
            if ($isProcessing) {
                // This is actually a success case - STK push was sent but still processing
                // Store a placeholder request so user can check status
                $placeholder_id = 'PROC_' . time() . '_' . rand(1000, 9999);
                
                $stmt = $conn->prepare("
                    INSERT INTO mpesa_payment_requests (
                        booking_id, checkout_request_id, phone_number, amount, 
                        reference, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'processing', NOW())
                ");
                $stmt->bind_param('issds', 
                    $booking_id, 
                    $placeholder_id, 
                    $phone_number, 
                    $amount, 
                    $reference
                );
                $stmt->execute();
                
                echo json_encode([
                    'success' => false,
                    'message' => $errorMessage,
                    'processing' => true,
                    'data' => [
                        'checkout_request_id' => $placeholder_id,
                        'reference' => $reference,
                        'amount' => $amount,
                        'phone_number' => $phone_number
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $errorMessage,
                    'error' => $result
                ]);
            }
        }
    } else {
        $errorMessage = 'Failed to connect to M-Pesa API';
        if ($httpCode === 401) {
            $errorMessage = 'Authentication failed - check M-Pesa credentials';
        } elseif ($httpCode === 403) {
            $errorMessage = 'Access denied - check M-Pesa permissions';
        } elseif ($httpCode === 500) {
            $errorMessage = 'M-Pesa server error - try again later';
        }
        
        echo json_encode([
            'success' => false,
            'message' => $errorMessage,
            'http_code' => $httpCode,
            'response' => $response
        ]);
    }

} catch (Exception $e) {
    error_log('M-Pesa STK Push Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}
?> 