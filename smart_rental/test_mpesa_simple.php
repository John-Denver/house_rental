<?php
/**
 * Simple M-Pesa Test
 * Quick test for connectivity and credentials
 */

require_once 'mpesa_config.php';

echo "<h2>Simple M-Pesa Test</h2>";

// Test 1: Check if we can reach Safaricom
echo "<h3>1. Testing Connection to Safaricom</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://sandbox.safaricom.co.ke');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    echo "<p style='color: green;'>✅ Connection to Safaricom successful</p>";
} else {
    echo "<p style='color: red;'>❌ Connection failed: HTTP $httpCode - $error</p>";
    echo "<p><strong>Possible solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Check your internet connection</li>";
    echo "<li>Check firewall settings (allow port 443)</li>";
    echo "<li>Try from a different network</li>";
    echo "<li>Check if your ISP blocks external HTTPS</li>";
    echo "</ul>";
    exit;
}

// Test 2: Get Access Token
echo "<h3>2. Testing Access Token</h3>";
$credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, MPESA_AUTH_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $credentials,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p><strong>Auth URL:</strong> " . MPESA_AUTH_URL . "</p>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['access_token'])) {
        echo "<p style='color: green;'>✅ Access token obtained successfully!</p>";
        echo "<p><strong>Token:</strong> " . substr($result['access_token'], 0, 20) . "...</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Response received but no token found</p>";
        echo "<p><strong>Response:</strong> $response</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Failed to get access token</p>";
    echo "<p><strong>Error:</strong> $error</p>";
    echo "<p><strong>Response:</strong> $response</p>";
    
    if ($httpCode === 401) {
        echo "<p><strong>Issue:</strong> Invalid credentials (Consumer Key/Secret)</p>";
        echo "<p><strong>Solution:</strong> Check your M-Pesa API credentials</p>";
    } elseif ($httpCode === 0) {
        echo "<p><strong>Issue:</strong> Network connectivity problem</p>";
        echo "<p><strong>Solution:</strong> Check internet connection and firewall</p>";
    }
}

// Test 3: Configuration Check
echo "<h3>3. Configuration Check</h3>";
echo "<ul>";
echo "<li><strong>Consumer Key:</strong> " . substr(MPESA_CONSUMER_KEY, 0, 10) . "...</li>";
echo "<li><strong>Consumer Secret:</strong> " . substr(MPESA_CONSUMER_SECRET, 0, 10) . "...</li>";
echo "<li><strong>Business Shortcode:</strong> " . MPESA_BUSINESS_SHORTCODE . "</li>";
echo "<li><strong>Environment:</strong> " . MPESA_ENVIRONMENT . "</li>";
echo "<li><strong>Base URL:</strong> " . MPESA_BASE_URL . "</li>";
echo "</ul>";

// Test 4: Quick Fix Suggestions
echo "<h3>4. Quick Fix Suggestions</h3>";
echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #fff3cd;'>";
echo "<h4>If you're still having issues:</h4>";
echo "<ol>";
echo "<li><strong>Use Manual Payment:</strong> The manual payment option still works</li>";
echo "<li><strong>Test on Different Network:</strong> Try from mobile hotspot</li>";
echo "<li><strong>Check XAMPP Settings:</strong> Ensure outbound connections are allowed</li>";
echo "<li><strong>Update Credentials:</strong> Verify your M-Pesa API credentials</li>";
echo "<li><strong>Contact Safaricom:</strong> If credentials are correct, contact Safaricom support</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p><a href='booking_payment.php?id=1' class='btn btn-primary'>Test Payment Page</a></p>";
echo "<p><a href='debug_mpesa_connection.php' class='btn btn-secondary'>Detailed Debug</a></p>";
?> 