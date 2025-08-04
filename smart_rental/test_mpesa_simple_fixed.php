<?php
/**
 * Simple M-Pesa Test - Fixed for Sandbox
 * Bypasses callback URL issues for testing
 */

require_once '../config/db.php';
require_once 'mpesa_config_fixed.php';

echo "<h2>M-Pesa Test - Fixed Version</h2>";

// Test 1: Get access token
echo "<h3>🔑 Test 1: Access Token</h3>";
$accessToken = getMpesaAccessToken();
if ($accessToken) {
    echo "<p style='color: green;'>✅ Access token obtained: " . substr($accessToken, 0, 20) . "...</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to get access token</p>";
    exit;
}

// Test 2: Prepare STK Push without callback URL
echo "<h3>📱 Test 2: STK Push (No Callback)</h3>";
$passwordData = generateMpesaPassword();
$testPhone = '712512358';
$formattedPhone = formatPhoneNumber($testPhone);
$amount = 1; // Test with 1 KSh
$reference = 'TEST' . time();

// Create STK Push data WITHOUT callback URL for testing
$stk_push_data = [
    'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
    'Password' => $passwordData['password'],
    'Timestamp' => $passwordData['timestamp'],
    'TransactionType' => MPESA_TRANSACTION_TYPE,
    'Amount' => $amount,
    'PartyA' => $formattedPhone,
    'PartyB' => MPESA_PARTYB,
    'PhoneNumber' => $formattedPhone,
    'AccountReference' => $reference,
    'TransactionDesc' => 'Test Payment'
];

// Remove callback URL for sandbox testing
// $stk_push_data['CallBackURL'] = MPESA_CALLBACK_URL;

echo "<p><strong>Business Shortcode:</strong> " . MPESA_BUSINESS_SHORTCODE . "</p>";
echo "<p><strong>Amount:</strong> $amount KSh</p>";
echo "<p><strong>Phone:</strong> $formattedPhone</p>";
echo "<p><strong>Reference:</strong> $reference</p>";
echo "<p><strong>Note:</strong> Callback URL removed for sandbox testing</p>";

// Test 3: Send STK Push request
echo "<h3>🚀 Test 3: Sending STK Push</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, MPESA_STK_PUSH_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_push_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
echo htmlspecialchars($response);
echo "</pre>";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['CheckoutRequestID'])) {
        echo "<p style='color: green;'>✅ STK Push sent successfully!</p>";
        echo "<p><strong>Checkout Request ID:</strong> " . $result['CheckoutRequestID'] . "</p>";
        echo "<p><strong>Merchant Request ID:</strong> " . $result['MerchantRequestID'] . "</p>";
        echo "<p><strong>Response Code:</strong> " . $result['ResponseCode'] . "</p>";
        echo "<p><strong>Response Description:</strong> " . $result['ResponseDescription'] . "</p>";
        
        if ($result['ResponseCode'] === '0') {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>🎉 SUCCESS!</h4>";
            echo "<p>M-Pesa STK Push is working correctly!</p>";
            echo "<p>You should receive a payment prompt on your phone.</p>";
            echo "<p><strong>Note:</strong> This is a test payment of 1 KSh</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>⚠️ Response Error</h4>";
            echo "<p>STK Push sent but returned error code: " . $result['ResponseCode'] . "</p>";
            echo "<p>Description: " . $result['ResponseDescription'] . "</p>";
            echo "</div>";
        }
    } else {
        echo "<p style='color: red;'>❌ Invalid response format</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Failed to send STK Push</p>";
    if ($error) {
        echo "<p><strong>cURL Error:</strong> $error</p>";
    }
}

// Test 4: Manual Payment Alternative
echo "<h3>💳 Test 4: Manual Payment Alternative</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>If M-Pesa doesn't work, use Manual Payment:</h4>";
echo "<ul>";
echo "<li>✅ <strong>Manual Payment:</strong> Always works</li>";
echo "<li>✅ <strong>No External Dependencies:</strong> No API calls needed</li>";
echo "<li>✅ <strong>User-Friendly:</strong> Upload payment proof</li>";
echo "<li>✅ <strong>Reliable:</strong> Works in all environments</li>";
echo "</ul>";
echo "<p><a href='booking_payment.php?id=1' class='btn btn-primary'>Test Manual Payment</a></p>";
echo "</div>";

// Test 5: Summary
echo "<h3>📊 Test 5: Summary</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Current Status:</h4>";
echo "<ul>";
echo "<li>✅ <strong>Access Token:</strong> Working</li>";
echo "<li>✅ <strong>Credentials:</strong> Valid</li>";
echo "<li>✅ <strong>Network:</strong> Connected</li>";
echo "<li>✅ <strong>Manual Payment:</strong> Available</li>";
echo "<li>❓ <strong>M-Pesa STK Push:</strong> Testing...</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><a href='booking_payment.php?id=1' class='btn btn-primary'>Test Payment Page</a></p>";
echo "<p><a href='test_mpesa_simple.php' class='btn btn-secondary'>Back to Original Test</a></p>";
?> 