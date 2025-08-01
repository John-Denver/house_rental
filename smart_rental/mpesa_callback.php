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
    
    // Here you would typically:
    // 1. Update the booking status in your database
    // 2. Send confirmation emails
    // 3. Update payment records
    
    $logEntry = date('Y-m-d H:i:s') . " - Payment SUCCESS:\n";
    $logEntry .= "Receipt: " . ($callbackData['MpesaReceiptNumber'] ?? 'N/A') . "\n";
    $logEntry .= "Amount: " . ($callbackData['Amount'] ?? 'N/A') . "\n";
    $logEntry .= "Phone: " . ($callbackData['PhoneNumber'] ?? 'N/A') . "\n";
    $logEntry .= "----------------------------------------\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
} else {
    $response['payment_status'] = 'failed';
    $response['error_code'] = $callbackData['ResultCode'] ?? 'Unknown';
    $response['error_message'] = $callbackData['ResultDesc'] ?? 'Unknown error';
    
    $logEntry = date('Y-m-d H:i:s') . " - Payment FAILED:\n";
    $logEntry .= "Error Code: " . ($callbackData['ResultCode'] ?? 'Unknown') . "\n";
    $logEntry .= "Error Message: " . ($callbackData['ResultDesc'] ?? 'Unknown error') . "\n";
    $logEntry .= "----------------------------------------\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Return success response to M-Pesa
http_response_code(200);
echo json_encode($response);
?> 