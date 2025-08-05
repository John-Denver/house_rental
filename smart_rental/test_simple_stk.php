<?php
/**
 * Simple STK Push Test
 * Test the actual response format
 */

require_once '../config/db.php';
require_once 'mpesa_config.php';

// Simulate a successful STK push response
$successResponse = [
    'success' => true,
    'message' => 'STK Push sent successfully',
    'data' => [
        'checkout_request_id' => 'ws_CO_040820252351002700000000',
        'merchant_request_id' => 'd2c6-4205-830c-9d5d446e2e8011550',
        'reference' => 'RENTAL_1_1754340659',
        'amount' => 100,
        'phone_number' => '254700000000'
    ]
];

echo "=== Testing Response Format ===\n\n";

echo "Success Response:\n";
echo json_encode($successResponse, JSON_PRETTY_PRINT) . "\n\n";

// Test if the frontend would recognize this as success
$isSuccess = isset($successResponse['success']) && $successResponse['success'] === true;
$hasCheckoutId = isset($successResponse['data']['checkout_request_id']);

echo "Frontend would recognize as success: " . ($isSuccess ? 'YES' : 'NO') . "\n";
echo "Has checkout request ID: " . ($hasCheckoutId ? 'YES' : 'NO') . "\n";

if ($isSuccess && $hasCheckoutId) {
    echo "✅ This response should work correctly in the frontend\n";
} else {
    echo "❌ This response would cause issues in the frontend\n";
}

echo "\n=== Test Complete ===\n";
?> 