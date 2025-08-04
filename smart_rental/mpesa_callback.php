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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
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

// Check if payment was successful
if (isset($callbackData['ResultCode']) && $callbackData['ResultCode'] === '0') {
    $response['payment_status'] = 'success';
    $response['receipt_number'] = $callbackData['MpesaReceiptNumber'] ?? 'N/A';
    $response['amount'] = $callbackData['Amount'] ?? 'N/A';
    $response['phone'] = $callbackData['PhoneNumber'] ?? 'N/A';
    
    // Update payment request in database
    try {
        $checkoutRequestId = $callbackData['CheckoutRequestID'] ?? null;
        $merchantRequestId = $callbackData['MerchantRequestID'] ?? null;
        $resultCode = $callbackData['ResultCode'] ?? null;
        $resultDesc = $callbackData['ResultDesc'] ?? null;
        $mpesaReceiptNumber = $callbackData['MpesaReceiptNumber'] ?? null;
        $transactionDate = $callbackData['TransactionDate'] ?? null;
        $amount = $callbackData['Amount'] ?? null;
        $phoneNumber = $callbackData['PhoneNumber'] ?? null;
        
        if ($checkoutRequestId) {
            // Update the payment request status
            $updateQuery = "UPDATE mpesa_payment_requests SET 
                status = 'completed',
                result_code = ?,
                result_desc = ?,
                mpesa_receipt_number = ?,
                transaction_date = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE checkout_request_id = ?";
            
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([
                $resultCode,
                $resultDesc,
                $mpesaReceiptNumber,
                $transactionDate,
                $checkoutRequestId
            ]);
            
            // Get the booking ID for this payment
            $bookingQuery = "SELECT booking_id FROM mpesa_payment_requests WHERE checkout_request_id = ?";
            $stmt = $pdo->prepare($bookingQuery);
            $stmt->execute([$checkoutRequestId]);
            $bookingResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bookingResult) {
                $bookingId = $bookingResult['booking_id'];
                
                // Update booking status to 'confirmed'
                $updateBookingQuery = "UPDATE rental_bookings SET 
                    status = 'confirmed',
                    payment_status = 'paid',
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
                
                $stmt = $pdo->prepare($updateBookingQuery);
                $stmt->execute([$bookingId]);
                
                // Include payment tracking helper
                require_once 'includes/payment_tracking_helper.php';
                
                // Check if this is the first payment for this booking
                $hasFirstPayment = hasFirstPaymentBeenMade($pdo, $bookingId);
                
                if (!$hasFirstPayment) {
                    // This is the initial payment (security deposit + first month rent)
                    $breakdown = getInitialPaymentBreakdown($pdo, $bookingId);
                    $securityDepositAmount = $breakdown['security_deposit'];
                    $monthlyRentAmount = $breakdown['monthly_rent'];
                    
                    // Record initial payment
                    recordInitialPayment(
                        $pdo, 
                        $bookingId, 
                        $amount, 
                        $securityDepositAmount, 
                        $monthlyRentAmount, 
                        'M-Pesa', 
                        $checkoutRequestId, 
                        $mpesaReceiptNumber, 
                        'M-Pesa Initial Payment - Checkout Request: ' . $checkoutRequestId
                    );
                } else {
                    // This is a monthly rent payment
                    $nextMonth = getNextUnpaidMonth($pdo, $bookingId);
                    
                    // Record monthly payment
                    recordMonthlyPayment(
                        $pdo, 
                        $bookingId, 
                        $nextMonth, 
                        $amount, 
                        'M-Pesa', 
                        $checkoutRequestId, 
                        $mpesaReceiptNumber, 
                        'M-Pesa Monthly Payment - Checkout Request: ' . $checkoutRequestId
                    );
                }
                
                $logEntry = date('Y-m-d H:i:s') . " - Booking $bookingId updated to confirmed and monthly payment recorded\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            }
            
            $logEntry = date('Y-m-d H:i:s') . " - Payment request updated successfully\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
        
    } catch (PDOException $e) {
        $logEntry = date('Y-m-d H:i:s') . " - Database update failed: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " - Payment SUCCESS:\n";
    $logEntry .= "Receipt: " . ($callbackData['MpesaReceiptNumber'] ?? 'N/A') . "\n";
    $logEntry .= "Amount: " . ($callbackData['Amount'] ?? 'N/A') . "\n";
    $logEntry .= "Phone: " . ($callbackData['PhoneNumber'] ?? 'N/A') . "\n";
    $logEntry .= "CheckoutRequestID: " . ($callbackData['CheckoutRequestID'] ?? 'N/A') . "\n";
    $logEntry .= "----------------------------------------\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
} else {
    $response['payment_status'] = 'failed';
    $response['error_code'] = $callbackData['ResultCode'] ?? 'Unknown';
    $response['error_message'] = $callbackData['ResultDesc'] ?? 'Unknown error';
    
    // Update payment request status to failed
    try {
        $checkoutRequestId = $callbackData['CheckoutRequestID'] ?? null;
        $resultCode = $callbackData['ResultCode'] ?? null;
        $resultDesc = $callbackData['ResultDesc'] ?? null;
        
        if ($checkoutRequestId) {
            $updateQuery = "UPDATE mpesa_payment_requests SET 
                status = 'failed',
                result_code = ?,
                result_desc = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE checkout_request_id = ?";
            
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([$resultCode, $resultDesc, $checkoutRequestId]);
            
            $logEntry = date('Y-m-d H:i:s') . " - Payment request updated to failed\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
        
    } catch (PDOException $e) {
        $logEntry = date('Y-m-d H:i:s') . " - Database update failed: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " - Payment FAILED:\n";
    $logEntry .= "Error Code: " . ($callbackData['ResultCode'] ?? 'Unknown') . "\n";
    $logEntry .= "Error Message: " . ($callbackData['ResultDesc'] ?? 'Unknown error') . "\n";
    $logEntry .= "CheckoutRequestID: " . ($callbackData['CheckoutRequestID'] ?? 'N/A') . "\n";
    $logEntry .= "----------------------------------------\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Return success response to M-Pesa
http_response_code(200);
echo json_encode($response);
?> 