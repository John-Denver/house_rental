<?php
/**
 * M-Pesa Connection Debug Script
 * Tests connectivity to M-Pesa sandbox API
 */

require_once '../config/db.php';
require_once 'mpesa_config.php';

echo "<h2>M-Pesa Connection Debug</h2>";

// Test 1: Basic connectivity
echo "<h3>🔍 Test 1: Basic Connectivity</h3>";
echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #e7f3ff;'>";

$testUrl = 'https://sandbox.safaricom.co.ke';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    echo "<p style='color: green;'>✅ <strong>Basic connectivity:</strong> Successfully connected to Safaricom sandbox</p>";
} else {
    echo "<p style='color: red;'>❌ <strong>Basic connectivity:</strong> Failed to connect to Safaricom sandbox</p>";
    echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
    echo "<p><strong>Error:</strong> $error</p>";
}
echo "</div>";

// Test 2: Access Token Request
echo "<h3>🔑 Test 2: Access Token Request</h3>";
echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #e7f3ff;'>";

$credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, MPESA_AUTH_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $credentials,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p><strong>Auth URL:</strong> " . MPESA_AUTH_URL . "</p>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['access_token'])) {
        echo "<p style='color: green;'>✅ <strong>Access Token:</strong> Successfully obtained</p>";
        echo "<p><strong>Token:</strong> " . substr($result['access_token'], 0, 20) . "...</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ <strong>Access Token:</strong> Response received but no token found</p>";
        echo "<p><strong>Response:</strong> $response</p>";
    }
} else {
    echo "<p style='color: red;'>❌ <strong>Access Token:</strong> Failed to obtain</p>";
    echo "<p><strong>Error:</strong> $error</p>";
    echo "<p><strong>Response:</strong> $response</p>";
}
echo "</div>";

// Test 3: STK Push URL
echo "<h3>📱 Test 3: STK Push URL Test</h3>";
echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #e7f3ff;'>";
echo "<p><strong>STK Push URL:</strong> " . MPESA_STK_PUSH_URL . "</p>";
echo "<p><strong>Business Shortcode:</strong> " . MPESA_BUSINESS_SHORTCODE . "</p>";
echo "<p><strong>Consumer Key:</strong> " . substr(MPESA_CONSUMER_KEY, 0, 10) . "...</p>";
echo "<p><strong>Consumer Secret:</strong> " . substr(MPESA_CONSUMER_SECRET, 0, 10) . "...</p>";
echo "</div>";

// Test 4: Network Diagnostics
echo "<h3>🌐 Test 4: Network Diagnostics</h3>";
echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #e7f3ff;'>";

// Test DNS resolution
$host = 'sandbox.safaricom.co.ke';
$ip = gethostbyname($host);
if ($ip !== $host) {
    echo "<p style='color: green;'>✅ <strong>DNS Resolution:</strong> $host resolves to $ip</p>";
} else {
    echo "<p style='color: red;'>❌ <strong>DNS Resolution:</strong> Failed to resolve $host</p>";
}

// Test if cURL is available
if (function_exists('curl_version')) {
    $curlInfo = curl_version();
    echo "<p style='color: green;'>✅ <strong>cURL:</strong> Available (version: " . $curlInfo['version'] . ")</p>";
} else {
    echo "<p style='color: red;'>❌ <strong>cURL:</strong> Not available</p>";
}

// Test SSL
if (function_exists('openssl_get_cipher_methods')) {
    echo "<p style='color: green;'>✅ <strong>OpenSSL:</strong> Available</p>";
} else {
    echo "<p style='color: red;'>❌ <strong>OpenSSL:</strong> Not available</p>";
}
echo "</div>";

// Test 5: Alternative Connection Test
echo "<h3>🔄 Test 5: Alternative Connection Test</h3>";
echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #e7f3ff;'>";

// Test with different SSL settings
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, MPESA_AUTH_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $credentials,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>Total Time:</strong> " . round($info['total_time'], 2) . " seconds</p>";
echo "<p><strong>Connect Time:</strong> " . round($info['connect_time'], 2) . " seconds</p>";

if ($httpCode === 200) {
    echo "<p style='color: green;'>✅ <strong>Alternative Test:</strong> Success</p>";
} else {
    echo "<p style='color: red;'>❌ <strong>Alternative Test:</strong> Failed</p>";
    echo "<p><strong>Error:</strong> $error</p>";
}
echo "</div>";

// Test 6: Troubleshooting Steps
echo "<h3>🔧 Test 6: Troubleshooting Steps</h3>";
echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #fff3cd;'>";
echo "<h4>If connection fails, try these steps:</h4>";
echo "<ol>";
echo "<li><strong>Check Internet Connection:</strong> Ensure your server has internet access</li>";
echo "<li><strong>Firewall Settings:</strong> Allow outbound HTTPS (port 443) connections</li>";
echo "<li><strong>Proxy Settings:</strong> If behind a proxy, configure cURL proxy settings</li>";
echo "<li><strong>SSL Certificates:</strong> Update CA certificates if needed</li>";
echo "<li><strong>DNS Settings:</strong> Check if DNS resolution works</li>";
echo "<li><strong>Alternative Network:</strong> Try from a different network</li>";
echo "</ol>";
echo "</div>";

// Test 7: Manual Test Instructions
echo "<h3>🧪 Test 7: Manual Testing</h3>";
echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #d1ecf1;'>";
echo "<h4>Manual Testing Steps:</h4>";
echo "<ol>";
echo "<li><strong>Test Payment Page:</strong> <a href='booking_payment.php?id=1' target='_blank'>booking_payment.php?id=1</a></li>";
echo "<li><strong>Enter Test Phone:</strong> 700000000</li>";
echo "<li><strong>Click Pay:</strong> Watch browser console for errors</li>";
echo "<li><strong>Check Network Tab:</strong> Look for failed requests</li>";
echo "<li><strong>Check Server Logs:</strong> Look for PHP errors</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p><a href='booking_payment.php?id=1' class='btn btn-primary'>Test Payment Page</a></p>";
?> 