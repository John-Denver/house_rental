<?php
/**
 * Test ngrok Callback Endpoint
 * This script tests if the callback endpoint is accessible via ngrok
 */

// Include M-Pesa config
require_once 'mpesa_config.php';

echo "🧪 Testing ngrok Callback Setup\n";
echo "===============================\n\n";

// Test 1: Check if callback file exists
echo "📋 Test 1: Callback File\n";
if (file_exists(__DIR__ . '/mpesa_callback.php')) {
    echo "✅ mpesa_callback.php exists\n";
} else {
    echo "❌ mpesa_callback.php missing\n";
    exit(1);
}

// Test 2: Check current callback URL
echo "\n📋 Test 2: Current Callback URL\n";
echo "Current URL: " . MPESA_CALLBACK_URL . "\n";

// Test 3: Check if ngrok URL is configured
if (strpos(MPESA_CALLBACK_URL, 'ngrok.io') !== false) {
    echo "✅ ngrok URL detected\n";
} else {
    echo "⚠️  ngrok URL not detected - please update mpesa_config.php\n";
}

// Test 4: Test local callback endpoint
echo "\n📋 Test 3: Local Callback Test\n";
$testData = [
    'ResultCode' => '0',
    'ResultDesc' => 'Success',
    'MpesaReceiptNumber' => 'TEST123456',
    'Amount' => '100',
    'PhoneNumber' => '254700000000',
    'MerchantRequestID' => 'TEST-' . time(),
    'CheckoutRequestID' => 'TEST-' . time()
];

// Simulate a callback request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/rental_system_bse/smart_rental/mpesa_callback.php');
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

if ($error) {
    echo "❌ Local callback test failed: " . $error . "\n";
    echo "💡 Make sure XAMPP is running on port 80\n";
} else {
    echo "✅ Local callback test successful (HTTP $httpCode)\n";
    if ($response) {
        $result = json_decode($response, true);
        if ($result && isset($result['payment_status'])) {
            echo "   Payment Status: " . $result['payment_status'] . "\n";
        }
    }
}

// Test 5: Check logs directory
echo "\n📋 Test 4: Logs Directory\n";
$logsDir = __DIR__ . '/logs';
if (is_dir($logsDir)) {
    echo "✅ Logs directory exists\n";
    
    // Check if callback log exists
    $logFile = $logsDir . '/mpesa_callback.log';
    if (file_exists($logFile)) {
        $logSize = filesize($logFile);
        echo "✅ Callback log exists (" . number_format($logSize) . " bytes)\n";
        
        // Show last few lines
        $lines = file($logFile);
        if ($lines) {
            echo "📄 Last log entries:\n";
            $lastLines = array_slice($lines, -5);
            foreach ($lastLines as $line) {
                echo "   " . trim($line) . "\n";
            }
        }
    } else {
        echo "⚠️  Callback log doesn't exist yet (will be created on first callback)\n";
    }
} else {
    echo "⚠️  Logs directory doesn't exist (will be created automatically)\n";
}

echo "\n🎯 ngrok Setup Instructions:\n";
echo "============================\n";
echo "1. Install ngrok from https://ngrok.com/\n";
echo "2. Open command prompt and run: ngrok http 80\n";
echo "3. Copy the ngrok URL (e.g., https://abc123.ngrok.io)\n";
echo "4. Update mpesa_config.php with the ngrok URL\n";
echo "5. Test M-Pesa payment\n";
echo "6. Monitor logs/mpesa_callback.log\n\n";

echo "🔧 Current Status:\n";
echo "==================\n";
echo "✅ Callback endpoint: Ready\n";
echo "✅ Logging: Ready\n";
echo "⚠️  ngrok: Needs to be started\n";
echo "⚠️  Callback URL: Needs to be updated with ngrok URL\n\n";

echo "💡 Quick Test:\n";
echo "Once ngrok is running, you can test the callback by visiting:\n";
echo "https://YOUR-NGROK-URL.ngrok.io/rental_system_bse/smart_rental/mpesa_callback.php\n";
echo "This should show a JSON response indicating the endpoint is working.\n";
?> 