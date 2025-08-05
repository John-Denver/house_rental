<?php
/**
 * Debug M-Pesa STK Push
 * This file helps debug M-Pesa STK push issues
 */

require_once '../config/db.php';
require_once 'mpesa_config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log file
$logFile = __DIR__ . '/logs/mpesa_debug.log';
$logDir = dirname($logFile);

// Create logs directory if it doesn't exist
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

function logDebug($message, $data = null) {
    global $logFile;
    $logEntry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    if ($data) {
        $logEntry .= json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
    $logEntry .= "----------------------------------------\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Test parameters
$testData = [
    'booking_id' => 1,
    'phone_number' => '254700000000',
    'amount' => 100
];

logDebug("Starting M-Pesa STK Push Debug Test", $testData);

try {
    // Get M-Pesa access token
    logDebug("Getting M-Pesa access token...");
    $access_token = getMpesaAccessToken();
    
    if (!$access_token) {
        logDebug("ERROR: Failed to get M-Pesa access token");
        echo "Failed to get M-Pesa access token\n";
        exit;
    }
    
    logDebug("Access token obtained successfully", ['token' => substr($access_token, 0, 20) . '...']);
    
    // Generate M-Pesa password
    logDebug("Generating M-Pesa password...");
    $password_data = generateMpesaPassword();
    logDebug("Password generated", $password_data);
    
    // Format phone number
    logDebug("Formatting phone number...");
    $formatted_phone = formatPhoneNumber($testData['phone_number']);
    logDebug("Phone number formatted", ['original' => $testData['phone_number'], 'formatted' => $formatted_phone]);
    
    // Generate unique reference
    $reference = 'RENTAL_' . $testData['booking_id'] . '_' . time();
    logDebug("Reference generated", ['reference' => $reference]);
    
    // Prepare STK Push request
    $stk_push_data = [
        'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
        'Password' => $password_data['password'],
        'Timestamp' => $password_data['timestamp'],
        'TransactionType' => MPESA_TRANSACTION_TYPE,
        'Amount' => intval($testData['amount']),
        'PartyA' => $formatted_phone,
        'PartyB' => MPESA_PARTYB,
        'PhoneNumber' => $formatted_phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $reference,
        'TransactionDesc' => 'Rental Payment - Test'
    ];
    
    logDebug("STK Push request data prepared", $stk_push_data);
    
    // Make STK Push request
    logDebug("Making STK Push request to M-Pesa API...");
    
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    // Log curl info
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log curl verbose output
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    logDebug("CURL Verbose Output", ['output' => $verboseLog]);
    
    logDebug("M-Pesa API Response", [
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'response' => $response
    ]);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        
        if (isset($result['CheckoutRequestID'])) {
            logDebug("SUCCESS: STK Push initiated successfully", $result);
            echo "SUCCESS: STK Push initiated successfully\n";
            echo "CheckoutRequestID: " . $result['CheckoutRequestID'] . "\n";
            echo "MerchantRequestID: " . $result['MerchantRequestID'] . "\n";
        } else {
            logDebug("ERROR: Failed to get CheckoutRequestID from response", $result);
            echo "ERROR: Failed to get CheckoutRequestID from response\n";
            echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        logDebug("ERROR: HTTP request failed", [
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'response' => $response
        ]);
        echo "ERROR: HTTP request failed\n";
        echo "HTTP Code: " . $httpCode . "\n";
        echo "CURL Error: " . $curlError . "\n";
        echo "Response: " . $response . "\n";
    }
    
} catch (Exception $e) {
    logDebug("EXCEPTION: " . $e->getMessage());
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\nDebug log written to: " . $logFile . "\n";
?> 