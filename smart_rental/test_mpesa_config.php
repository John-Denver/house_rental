<?php
/**
 * Test M-Pesa Configuration
 * Simple test to verify M-Pesa setup
 */

require_once 'mpesa_config.php';

echo "=== M-Pesa Configuration Test ===\n\n";

// Test 1: Check configuration constants
echo "1. Checking configuration constants:\n";
echo "   MPESA_BASE_URL: " . MPESA_BASE_URL . "\n";
echo "   MPESA_BUSINESS_SHORTCODE: " . MPESA_BUSINESS_SHORTCODE . "\n";
echo "   MPESA_TRANSACTION_TYPE: " . MPESA_TRANSACTION_TYPE . "\n";
echo "   MPESA_CALLBACK_URL: " . MPESA_CALLBACK_URL . "\n";
echo "   MPESA_ENVIRONMENT: " . MPESA_ENVIRONMENT . "\n\n";

// Test 2: Test access token generation
echo "2. Testing access token generation:\n";
$access_token = getMpesaAccessToken();
if ($access_token) {
    echo "   ✅ Access token obtained successfully\n";
    echo "   Token (first 20 chars): " . substr($access_token, 0, 20) . "...\n";
} else {
    echo "   ❌ Failed to get access token\n";
}
echo "\n";

// Test 3: Test password generation
echo "3. Testing password generation:\n";
$password_data = generateMpesaPassword();
echo "   Timestamp: " . $password_data['timestamp'] . "\n";
echo "   Password (first 20 chars): " . substr($password_data['password'], 0, 20) . "...\n\n";

// Test 4: Test phone number formatting
echo "4. Testing phone number formatting:\n";
$test_numbers = ['0700000000', '254700000000', '+254700000000', '700000000'];
foreach ($test_numbers as $number) {
    $formatted = formatPhoneNumber($number);
    echo "   '$number' -> '$formatted'\n";
}
echo "\n";

// Test 5: Test error message function
echo "5. Testing error message function:\n";
$test_codes = ['0', '1', '1032', '1037', '9999'];
foreach ($test_codes as $code) {
    $message = getMpesaErrorMessage($code);
    echo "   Code '$code': $message\n";
}
echo "\n";

echo "=== Configuration Test Complete ===\n";

// Test 6: Check if callback URL is accessible
echo "6. Testing callback URL accessibility:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, MPESA_CALLBACK_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✅ Callback URL is accessible\n";
} else {
    echo "   ❌ Callback URL returned HTTP code: $httpCode\n";
    echo "   This might be causing the STK push to fail\n";
}
echo "\n";

echo "=== Test Complete ===\n";
?> 