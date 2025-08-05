<?php
/**
 * M-Pesa Callback Endpoint
 * This file receives callbacks from M-Pesa API
 */

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Accept all methods for testing, but log non-POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // For testing purposes, allow GET requests but log them
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'status' => 'success',
            'message' => 'Callback endpoint is accessible',
            'method' => 'GET',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
}

// Get the raw POST data
$input = file_get_contents('php://input');
$callbackData = json_decode($input, true);

// Log the callback data
$logFile = __DIR__ . '/logs/mpesa_callback.log';
$logDir = dirname($logFile);

// Create logs directory if it doesn't exist
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Log the callback
$logEntry = date('Y-m-d H:i:s') . " - Callback received:\n";
$logEntry .= json_encode($callbackData, JSON_PRETTY_PRINT) . "\n";
$logEntry .= "----------------------------------------\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Handle nested callback structure
if (isset($callbackData['Body']['stkCallback'])) {
    $callbackData = $callbackData['Body']['stkCallback'];
}

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=house_rental;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $logEntry = date('Y-m-d H:i:s') . " - Database connection failed: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Still return success to M-Pesa even if database fails
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Callback received']);
    exit();
}

// Process the callback
$response = [
    'status' => 'success',
    'message' => 'Callback received',
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => $callbackData
];

// Determine payment status based on result code
$resultCode = $callbackData['ResultCode'] ?? null;
$resultDesc = $callbackData['ResultDesc'] ?? 'Unknown';

// Define status based on result code
// Convert to string for consistent comparison
$resultCodeStr = (string)$resultCode;

if ($resultCodeStr === '0') {
    $status = 'completed';
    $response['payment_status'] = 'success';
} elseif ($resultCodeStr === '4999') {
    $status = 'processing';
    $response['payment_status'] = 'processing';
} elseif (in_array($resultCodeStr, ['1032', '1037', '1039'])) {
    $status = 'failed';
    $response['payment_status'] = 'failed';
} else {
    $status = 'failed';
    $response['payment_status'] = 'failed';
}

// Log the status determination for debugging
$logEntry = date('Y-m-d H:i:s') . " - Status determination: ResultCode=$resultCodeStr, Status=$status\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Handle successful payments
if ($status === 'completed') {
    $response['payment_status'] = 'success';
    
    // Extract data from CallbackMetadata if present
    $receiptNumber = $callbackData['MpesaReceiptNumber'] ?? 'N/A';
    $amount = $callbackData['Amount'] ?? 'N/A';
    $phone = $callbackData['PhoneNumber'] ?? 'N/A';
    
    // Check if data is in CallbackMetadata structure
    if (isset($callbackData['CallbackMetadata']['Item'])) {
        foreach ($callbackData['CallbackMetadata']['Item'] as $item) {
            if (isset($item['Name']) && isset($item['Value'])) {
                switch ($item['Name']) {
                    case 'MpesaReceiptNumber':
                        $receiptNumber = $item['Value'];
                        break;
                    case 'Amount':
                        $amount = $item['Value'];
                        break;
                    case 'PhoneNumber':
                        $phone = $item['Value'];
                        break;
                }
            }
        }
    }
    
    $response['receipt_number'] = $receiptNumber;
    $response['amount'] = $amount;
    $response['phone'] = $phone;
    
    // Update payment request in database with transaction and race condition protection
    try {
        $checkoutRequestId = $callbackData['CheckoutRequestID'] ?? null;
        $merchantRequestId = $callbackData['MerchantRequestID'] ?? null;
        $resultCode = $callbackData['ResultCode'] ?? null;
        $resultDesc = $callbackData['ResultDesc'] ?? null;
        $mpesaReceiptNumber = $receiptNumber;
        $transactionDate = $callbackData['TransactionDate'] ?? null;
        $dbAmount = $amount;
        $dbPhoneNumber = $phone;
        
        if ($checkoutRequestId) {
            // Start database transaction to prevent race conditions
            $pdo->beginTransaction();
            
            try {
                // Check if payment is already processed to prevent duplicate processing
                $checkQuery = "SELECT status, booking_id FROM mpesa_payment_requests 
                              WHERE checkout_request_id = ? FOR UPDATE";
                $stmt = $pdo->prepare($checkQuery);
                $stmt->execute([$checkoutRequestId]);
                $existingPayment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existingPayment) {
                    throw new Exception("Payment request not found: $checkoutRequestId");
                }
                
                // Prevent duplicate processing
                if ($existingPayment['status'] === 'completed') {
                    $logEntry = date('Y-m-d H:i:s') . " - Payment already completed for checkout_request_id: $checkoutRequestId\n";
                    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
                    
                    $pdo->rollback();
                    http_response_code(200);
                    echo json_encode(['status' => 'success', 'message' => 'Payment already processed']);
                    exit();
                }
                
                // Update the payment request status with optimistic locking
                $updateQuery = "UPDATE mpesa_payment_requests SET 
                    status = ?,
                    result_code = ?,
                    result_desc = ?,
                    mpesa_receipt_number = ?,
                    transaction_date = ?,
                    callback_data = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE checkout_request_id = ? AND status != 'completed'";
                
                $stmt = $pdo->prepare($updateQuery);
                $stmt->execute([
                    $status,
                    $resultCode,
                    $resultDesc,
                    $mpesaReceiptNumber,
                    $transactionDate,
                    json_encode($callbackData),
                    $checkoutRequestId
                ]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception("Payment request not found or already completed");
                }
                
                // Get booking ID for further updates
                $bookingId = $existingPayment['booking_id'];
                
                // Update booking status to confirmed and payment_status to paid
                $updateBookingQuery = "UPDATE rental_bookings SET 
                    status = 'confirmed',
                    payment_status = 'paid',
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND (status = 'pending' OR status = 'confirmed')";
                
                $stmt = $pdo->prepare($updateBookingQuery);
                $stmt->execute([$bookingId]);
                
                // Record payment in booking_payments table
                $insertPaymentQuery = "INSERT INTO booking_payments (
                    booking_id, amount, payment_method, transaction_id, 
                    payment_date, status, notes, created_at
                ) VALUES (?, ?, 'M-Pesa', ?, NOW(), 'completed', ?, NOW())";
                
                $transactionId = 'MPESA_' . time();
                $notes = 'M-Pesa Payment - Checkout Request: ' . $checkoutRequestId;
                
                $stmt = $pdo->prepare($insertPaymentQuery);
                $stmt->execute([
                    $bookingId,
                    $dbAmount,
                    $transactionId,
                    $notes
                ]);
                
                // Commit the transaction
                $pdo->commit();
                
                $logEntry = date('Y-m-d H:i:s') . " - Payment completed successfully for checkout_request_id: $checkoutRequestId\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollback();
                
                $logEntry = date('Y-m-d H:i:s') . " - Payment processing error: " . $e->getMessage() . "\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
                
                // Still return success to M-Pesa to prevent retries
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Callback processed with errors']);
                exit();
            }
        }
        
    } catch (PDOException $e) {
        $logEntry = date('Y-m-d H:i:s') . " - Database update failed: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " - Payment SUCCESS:\n";
    $logEntry .= "Receipt: " . $receiptNumber . "\n";
    $logEntry .= "Amount: " . $amount . "\n";
    $logEntry .= "Phone: " . $phone . "\n";
    $logEntry .= "CheckoutRequestID: " . ($callbackData['CheckoutRequestID'] ?? 'N/A') . "\n";
    $logEntry .= "ResultCode: " . ($callbackData['ResultCode'] ?? 'N/A') . "\n";
    $logEntry .= "ResultDesc: " . ($callbackData['ResultDesc'] ?? 'N/A') . "\n";
    $logEntry .= "----------------------------------------\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
} else {
    // Handle non-completed payments (processing, failed, etc.)
    $response['error_code'] = $callbackData['ResultCode'] ?? 'Unknown';
    $response['error_message'] = $callbackData['ResultDesc'] ?? 'Unknown error';
    
    // Update payment request status based on determined status
    try {
        $checkoutRequestId = $callbackData['CheckoutRequestID'] ?? null;
        
        if ($checkoutRequestId) {
            $updateQuery = "UPDATE mpesa_payment_requests SET 
                status = ?,
                result_code = ?,
                result_desc = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE checkout_request_id = ?";
            
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([$status, $resultCode, $resultDesc, $checkoutRequestId]);
            
            $logEntry = date('Y-m-d H:i:s') . " - Payment request updated to $status\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
        
    } catch (PDOException $e) {
        $logEntry = date('Y-m-d H:i:s') . " - Database update failed: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " - Payment $status:\n";
    $logEntry .= "Result Code: " . ($callbackData['ResultCode'] ?? 'Unknown') . "\n";
    $logEntry .= "Result Description: " . ($callbackData['ResultDesc'] ?? 'Unknown error') . "\n";
    $logEntry .= "CheckoutRequestID: " . ($callbackData['CheckoutRequestID'] ?? 'N/A') . "\n";
    $logEntry .= "----------------------------------------\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Return success response to M-Pesa
http_response_code(200);
echo json_encode($response);
?> 