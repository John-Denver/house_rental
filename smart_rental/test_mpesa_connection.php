<?php
session_start();
require_once 'mpesa_config.php';

echo "<h1>M-Pesa Connection Test</h1>";

// Test 1: Check if we can reach M-Pesa API
echo "<h2>1. Testing M-Pesa API Connectivity</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, MPESA_AUTH_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<p><strong>Auth URL:</strong> " . MPESA_AUTH_URL . "</p>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>cURL Error:</strong> " . ($curlError ?: 'None') . "</p>";
echo "<p><strong>Response:</strong> " . htmlspecialchars($response) . "</p>";

// Test 2: Test access token generation
echo "<h2>2. Testing Access Token Generation</h2>";
$access_token = getMpesaAccessToken();
if ($access_token) {
    echo "<p style='color: green;'>✅ Access token obtained successfully!</p>";
    echo "<p><strong>Token:</strong> " . substr($access_token, 0, 50) . "...</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to get access token</p>";
}

// Test 3: Test STK Push (without actually sending)
echo "<h2>3. Testing STK Push Configuration</h2>";
echo "<p><strong>Business Shortcode:</strong> " . MPESA_BUSINESS_SHORTCODE . "</p>";
echo "<p><strong>Passkey:</strong> " . substr(MPESA_PASSKEY, 0, 20) . "...</p>";
echo "<p><strong>Transaction Type:</strong> " . MPESA_TRANSACTION_TYPE . "</p>";
echo "<p><strong>Callback URL:</strong> " . MPESA_CALLBACK_URL . "</p>";

// Test 4: Test callback URL accessibility
echo "<h2>4. Testing Callback URL Accessibility</h2>";
$callbackUrl = MPESA_CALLBACK_URL;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $callbackUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<p><strong>Callback URL:</strong> $callbackUrl</p>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>cURL Error:</strong> " . ($curlError ?: 'None') . "</p>";

if ($httpCode === 200) {
    echo "<p style='color: green;'>✅ Callback URL is accessible</p>";
} else {
    echo "<p style='color: red;'>❌ Callback URL is not accessible (HTTP $httpCode)</p>";
}

// Test 5: Test database connection
echo "<h2>5. Testing Database Connection</h2>";
require_once '../config/db.php';
if ($conn->connect_error) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Check if mpesa_payment_requests table exists
    $result = $conn->query("SHOW TABLES LIKE 'mpesa_payment_requests'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✅ mpesa_payment_requests table exists</p>";
    } else {
        echo "<p style='color: red;'>❌ mpesa_payment_requests table does not exist</p>";
    }
}

// Test 6: Environment check
echo "<h2>6. Environment Check</h2>";
echo "<p><strong>Current Host:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</p>";
echo "<p><strong>Is Localhost:</strong> " . (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Environment:</strong> " . MPESA_ENVIRONMENT . "</p>";

// Test 7: Test STK Push (simulation)
echo "<h2>7. Test STK Push (Simulation)</h2>";
if ($access_token) {
    $phone = "254700000000"; // Test phone number
    $amount = 1; // Test amount
    $reference = "TEST" . time();
    
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_BUSINESS_SHORTCODE . MPESA_PASSKEY . $timestamp);
    
    $stk_push_data = [
        'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => MPESA_TRANSACTION_TYPE,
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => MPESA_PARTYB,
        'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $reference,
        'TransactionDesc' => 'Test Payment'
    ];
    
    echo "<p><strong>STK Push Data:</strong></p>";
    echo "<pre>" . json_encode($stk_push_data, JSON_PRETTY_PRINT) . "</pre>";
    
    echo "<p><strong>Note:</strong> This is a simulation. To actually test, use the payment page.</p>";
} else {
    echo "<p style='color: red;'>❌ Cannot test STK Push without access token</p>";
}

echo "<h2>8. Recommendations</h2>";
echo "<ul>";
echo "<li><strong>For Localhost Testing:</strong> Use the manual status update page at <code>manual_payment_status.php</code></li>";
echo "<li><strong>For Ngrok Testing:</strong> Ensure ngrok is running and the callback URL is correct</li>";
echo "<li><strong>For Production:</strong> Use your domain URL in the callback configuration</li>";
echo "</ul>";

echo "<h2>9. Quick Actions</h2>";
echo "<p><a href='manual_payment_status.php' class='btn btn-primary'>Manual Payment Status</a></p>";
echo "<p><a href='booking_payment.php?id=6&debug=1' class='btn btn-secondary'>Test Payment Page</a></p>";
echo "<p><a href='mpesa_stk_push.php' class='btn btn-info'>Test STK Push</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
.btn { display: inline-block; padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; }
.btn-primary { background: #007bff; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-info { background: #17a2b8; color: white; }
</style> 