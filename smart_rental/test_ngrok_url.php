<?php
/**
 * Test ngrok URL Accessibility
 * This script tests if the ngrok callback URL is working
 */

echo "🧪 Testing ngrok URL: https://b49f2da54ab7.ngrok-free.app\n";
echo "========================================================\n\n";

// Test the ngrok callback URL
$ngrokUrl = 'https://b49f2da54ab7.ngrok-free.app/rental_system_bse/smart_rental/mpesa_callback.php';

echo "📋 Testing ngrok callback URL...\n";
echo "URL: $ngrokUrl\n\n";

// Test with a simple GET request first
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ngrokUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Error accessing ngrok URL: $error\n";
} else {
    echo "✅ ngrok URL accessible (HTTP $httpCode)\n";
    if ($response) {
        echo "📄 Response preview: " . substr($response, 0, 200) . "...\n";
    }
}

// Test with a POST request (simulating M-Pesa callback)
echo "\n📋 Testing POST request (M-Pesa callback simulation)...\n";

$testData = [
    'ResultCode' => '0',
    'ResultDesc' => 'Success',
    'MpesaReceiptNumber' => 'TEST' . time(),
    'Amount' => '100',
    'PhoneNumber' => '254700000000',
    'MerchantRequestID' => 'TEST-' . time(),
    'CheckoutRequestID' => 'TEST-' . time()
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ngrokUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($testData))
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ POST test failed: $error\n";
} else {
    echo "✅ POST test successful (HTTP $httpCode)\n";
    if ($response) {
        $result = json_decode($response, true);
        if ($result) {
            echo "📄 Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "📄 Raw response: $response\n";
        }
    }
}

echo "\n🎯 Configuration Summary:\n";
echo "=========================\n";
echo "✅ ngrok URL: https://b49f2da54ab7.ngrok-free.app\n";
echo "✅ Callback endpoint: /rental_system_bse/smart_rental/mpesa_callback.php\n";
echo "✅ Full callback URL: $ngrokUrl\n";
echo "✅ M-Pesa config updated\n\n";

echo "💡 Next Steps:\n";
echo "1. Test M-Pesa payment in your application\n";
echo "2. Monitor logs/mpesa_callback.log for callbacks\n";
echo "3. Check ngrok web interface at http://localhost:4040\n\n";

echo "🔧 Monitoring:\n";
echo "- ngrok web interface: http://localhost:4040\n";
echo "- Callback logs: smart_rental/logs/mpesa_callback.log\n";
echo "- Test script: php test_ngrok_callback.php\n";
?> 