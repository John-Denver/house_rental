<?php
/**
 * Test M-Pesa STK Push Payment
 * Tests the actual payment functionality
 */

require_once '../config/db.php';
require_once 'mpesa_config.php';

echo "<h2>M-Pesa STK Push Test</h2>";

// Test 1: Check if we can get access token
echo "<h3>🔑 Test 1: Access Token</h3>";
$accessToken = getMpesaAccessToken();
if ($accessToken) {
    echo "<p style='color: green;'>✅ Access token obtained: " . substr($accessToken, 0, 20) . "...</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to get access token</p>";
    exit;
}

// Test 2: Generate password
echo "<h3>🔐 Test 2: Password Generation</h3>";
$passwordData = generateMpesaPassword();
echo "<p><strong>Timestamp:</strong> " . $passwordData['timestamp'] . "</p>";
echo "<p><strong>Password:</strong> " . substr($passwordData['password'], 0, 20) . "...</p>";

// Test 3: Format phone number
echo "<h3>📱 Test 3: Phone Number Formatting</h3>";
$testPhone = '712512358';
$formattedPhone = formatPhoneNumber($testPhone);
echo "<p><strong>Original:</strong> $testPhone</p>";
echo "<p><strong>Formatted:</strong> $formattedPhone</p>";

// Test 4: Prepare STK Push data
echo "<h3>📋 Test 4: STK Push Data Preparation</h3>";
$amount = 1; // Test with 1 KSh
$reference = 'TEST' . time();
$stk_push_data = [
    'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
    'Password' => $passwordData['password'],
    'Timestamp' => $passwordData['timestamp'],
    'TransactionType' => MPESA_TRANSACTION_TYPE,
    'Amount' => $amount,
    'PartyA' => $formattedPhone,
    'PartyB' => MPESA_PARTYB,
    'PhoneNumber' => $formattedPhone,
    'CallBackURL' => MPESA_CALLBACK_URL,
    'AccountReference' => $reference,
    'TransactionDesc' => 'Test Payment'
];

echo "<p><strong>Business Shortcode:</strong> " . MPESA_BUSINESS_SHORTCODE . "</p>";
echo "<p><strong>Amount:</strong> $amount</p>";
echo "<p><strong>Phone:</strong> $formattedPhone</p>";
echo "<p><strong>Reference:</strong> $reference</p>";

// Test 5: Send STK Push request
echo "<h3>🚀 Test 5: Sending STK Push Request</h3>";
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

// Test 6: Manual Payment Test
echo "<h3>💳 Test 6: Manual Payment Test</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Alternative: Manual Payment</h4>";
echo "<p>If M-Pesa is not working, you can still use manual payment:</p>";
echo "<ul>";
echo "<li>Go to: <a href='booking_payment.php?id=1' target='_blank'>booking_payment.php?id=1</a></li>";
echo "<li>Use the 'Other Payment Methods' section</li>";
echo "<li>Submit payment proof manually</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><a href='booking_payment.php?id=1' class='btn btn-primary'>Test Payment Page</a></p>";
echo "<p><a href='test_mpesa_simple.php' class='btn btn-secondary'>Back to Simple Test</a></p>";
?> 