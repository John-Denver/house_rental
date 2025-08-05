<?php
/**
 * Test Frontend Request
 * Simulate the frontend request to mpesa_stk_push.php
 */

// Simulate the frontend request
$testData = [
    'booking_id' => 1,
    'phone_number' => '254700000000',
    'amount' => 100
];

echo "=== Testing Frontend Request ===\n\n";

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Set session
session_start();
$_SESSION['user_id'] = 1; // Assuming user ID 1 exists

// Set the input data
$input = json_encode($testData);
file_put_contents('php://input', $input);

echo "Test Data:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

echo "=== Response from mpesa_stk_push.php ===\n";

// Capture output
ob_start();

// Include the STK push file
include 'mpesa_stk_push.php';

$response = ob_get_clean();

echo $response . "\n";

// Try to parse the response
$parsedResponse = json_decode($response, true);

echo "\n=== Parsed Response ===\n";
if ($parsedResponse) {
    echo "Success: " . ($parsedResponse['success'] ? 'true' : 'false') . "\n";
    if (isset($parsedResponse['message'])) {
        echo "Message: " . $parsedResponse['message'] . "\n";
    }
    if (isset($parsedResponse['data'])) {
        echo "Data: " . json_encode($parsedResponse['data'], JSON_PRETTY_PRINT) . "\n";
    }
    if (isset($parsedResponse['error'])) {
        echo "Error: " . json_encode($parsedResponse['error'], JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "Failed to parse JSON response\n";
    echo "Raw response: " . $response . "\n";
}

echo "\n=== Test Complete ===\n";
?> 