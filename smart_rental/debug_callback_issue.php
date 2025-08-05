<?php
/**
 * Debug Callback Issue
 * Check what's happening with the callback processing
 */

require_once '../config/db.php';

echo "=== Debug Callback Issue ===\n\n";

// Check recent payment requests
$query = "SELECT * FROM mpesa_payment_requests ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($query);

echo "Recent Payment Requests:\n";
echo "ID | Booking ID | Checkout Request ID | Status | Result Code | Result Desc | Created At\n";
echo "---|------------|-------------------|--------|-------------|-------------|------------\n";

while ($row = $result->fetch_assoc()) {
    echo sprintf(
        "%d | %d | %s | %s | %s | %s | %s\n",
        $row['id'],
        $row['booking_id'],
        $row['checkout_request_id'],
        $row['status'],
        $row['result_code'] ?? 'NULL',
        $row['result_desc'] ?? 'NULL',
        $row['created_at']
    );
}

echo "\n=== Recent Callback Log ===\n";
$logFile = __DIR__ . '/logs/mpesa_callback.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $recentLines = array_slice($lines, -50); // Last 50 lines
    echo implode("\n", $recentLines);
} else {
    echo "Callback log file not found.\n";
}

echo "\n=== Database Schema Check ===\n";
$schemaQuery = "DESCRIBE mpesa_payment_requests";
$schemaResult = $conn->query($schemaQuery);

echo "mpesa_payment_requests table structure:\n";
while ($row = $schemaResult->fetch_assoc()) {
    echo sprintf(
        "%s | %s | %s | %s | %s | %s\n",
        $row['Field'],
        $row['Type'],
        $row['Null'],
        $row['Key'],
        $row['Default'],
        $row['Extra']
    );
}

echo "\n=== Test Callback Data ===\n";
$testCallbackData = [
    'ResultCode' => '0',
    'ResultDesc' => 'Success',
    'MpesaReceiptNumber' => 'TEST123456',
    'Amount' => '100',
    'PhoneNumber' => '254700000000',
    'MerchantRequestID' => 'TEST-1754039932',
    'CheckoutRequestID' => 'ws_CO_040820252345410712512358'
];

echo "Test callback data:\n";
echo json_encode($testCallbackData, JSON_PRETTY_PRINT) . "\n\n";

// Check if this checkout request ID exists
$checkoutId = $testCallbackData['CheckoutRequestID'];
$checkQuery = "SELECT * FROM mpesa_payment_requests WHERE checkout_request_id = ?";
$stmt = $conn->prepare($checkQuery);
$stmt->bind_param('s', $checkoutId);
$stmt->execute();
$checkResult = $stmt->get_result();

if ($checkResult->num_rows > 0) {
    $row = $checkResult->fetch_assoc();
    echo "Found matching payment request:\n";
    echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No matching payment request found for checkout ID: $checkoutId\n";
}

echo "\n=== Debug Complete ===\n";
?> 