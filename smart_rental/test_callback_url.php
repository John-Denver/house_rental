<?php
/**
 * Test M-Pesa Callback URL Accessibility
 */

echo "<h2>M-Pesa Callback URL Test</h2>";

// Test the callback URL
$callbackUrl = "http://localhost/rental_system_bse/smart_rental/mpesa_payment_status.php";

echo "<h3>Testing Callback URL: $callbackUrl</h3>";

// Test with a simple POST request
$testData = [
    'checkout_request_id' => 'test_' . time(),
    'test' => true
];

echo "<h4>Test Data:</h4>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Use cURL to test the callback
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $callbackUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($testData))
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h4>Test Results:</h4>";
echo "<ul>";
echo "<li><strong>HTTP Code:</strong> $httpCode</li>";
echo "<li><strong>Response:</strong> " . htmlspecialchars($response) . "</li>";
if ($error) {
    echo "<li><strong>cURL Error:</strong> $error</li>";
}
echo "</ul>";

// Check if the callback file exists
$callbackFile = __DIR__ . '/mpesa_payment_status.php';
echo "<h4>File Check:</h4>";
echo "<ul>";
echo "<li><strong>File exists:</strong> " . (file_exists($callbackFile) ? 'Yes' : 'No') . "</li>";
echo "<li><strong>File path:</strong> $callbackFile</li>";
echo "<li><strong>File readable:</strong> " . (is_readable($callbackFile) ? 'Yes' : 'No') . "</li>";
echo "</ul>";

// Check error logs
echo "<h4>Error Log Check:</h4>";
$logFiles = [
    'C:/xampp/apache/logs/error.log',
    'C:/xampp/php/logs/php_error.log',
    __DIR__ . '/logs/mpesa_callback.log',
    __DIR__ . '/logs/mpesa_debug.log'
];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "<p><strong>$logFile:</strong> Exists</p>";
        // Get last few lines
        $lines = file($logFile);
        if ($lines) {
            $lastLines = array_slice($lines, -5);
            echo "<pre>" . htmlspecialchars(implode('', $lastLines)) . "</pre>";
        }
    } else {
        echo "<p><strong>$logFile:</strong> Does not exist</p>";
    }
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='test_mpesa_callback_manual.php?booking_id=43'>Manual M-Pesa Callback Test</a></li>";
echo "<li><a href='test_mpesa_callback.php?booking_id=43'>M-Pesa Callback Test</a></li>";
echo "<li><a href='debug_initial_payment.php?booking_id=43'>Initial Payment Debug</a></li>";
echo "</ul>";
?> 