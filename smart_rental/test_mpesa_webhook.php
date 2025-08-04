<?php
/**
 * M-Pesa Test with webhook.site callback
 * Quick test using webhook.site for callback URL
 */

require_once '../config/db.php';
require_once 'mpesa_config.php';

echo "<h2>M-Pesa Test with webhook.site</h2>";

// Instructions for webhook.site
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>📋 Setup Instructions:</h4>";
echo "<ol>";
echo "<li>Go to <a href='https://webhook.site/' target='_blank'>https://webhook.site/</a></li>";
echo "<li>Copy your unique webhook URL</li>";
echo "<li>Replace the callback URL in the config</li>";
echo "<li>Run this test</li>";
echo "</ol>";
echo "</div>";

// Test 1: Get access token
echo "<h3>🔑 Test 1: Access Token</h3>";
$accessToken = getMpesaAccessToken();
if ($accessToken) {
    echo "<p style='color: green;'>✅ Access token obtained: " . substr($accessToken, 0, 20) . "...</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to get access token</p>";
    exit;
}

// Test 2: Prepare STK Push with webhook callback
echo "<h3>📱 Test 2: STK Push with webhook.site</h3>";
$passwordData = generateMpesaPassword();
$testPhone = '700000000';
$formattedPhone = formatPhoneNumber($testPhone);
$amount = 1; // Test with 1 KSh
$reference = 'TEST' . time();

// Create STK Push data WITH webhook callback
$stk_push_data = [
    'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
    'Password' => $passwordData['password'],
    'Timestamp' => $passwordData['timestamp'],
    'TransactionType' => MPESA_TRANSACTION_TYPE,
    'Amount' => $amount,
    'PartyA' => $formattedPhone,
    'PartyB' => MPESA_PARTYB,
    'PhoneNumber' => $formattedPhone,
    'CallBackURL' => MPESA_CALLBACK_URL, // This should be your webhook.site URL
    'AccountReference' => $reference,
    'TransactionDesc' => 'Test Payment'
];

echo "<p><strong>Business Shortcode:</strong> " . MPESA_BUSINESS_SHORTCODE . "</p>";
echo "<p><strong>Amount:</strong> $amount KSh</p>";
echo "<p><strong>Phone:</strong> $formattedPhone</p>";
echo "<p><strong>Reference:</strong> $reference</p>";
echo "<p><strong>Callback URL:</strong> " . MPESA_CALLBACK_URL . "</p>";

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
            echo "<p><strong>Note:</strong> Check your webhook.site page for callback data</p>";
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

echo "<hr>";
echo "<p><a href='https://webhook.site/' target='_blank' class='btn btn-primary'>Get webhook.site URL</a></p>";
echo "<p><a href='booking_payment.php?id=1' class='btn btn-secondary'>Test Payment Page</a></p>";
?> 