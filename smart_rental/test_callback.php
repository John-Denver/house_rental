<?php
/**
 * Test Callback Endpoint
 * Simple test to verify the callback is accessible
 */

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Log the request
$logFile = __DIR__ . '/logs/test_callback.log';
$logDir = dirname($logFile);

// Create logs directory if it doesn't exist
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Log the request
$logEntry = date('Y-m-d H:i:s') . " - Test callback accessed:\n";
$logEntry .= "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
$logEntry .= "URL: " . $_SERVER['REQUEST_URI'] . "\n";
$logEntry .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";
$logEntry .= "Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Unknown') . "\n";
$logEntry .= "Raw Input: " . file_get_contents('php://input') . "\n";
$logEntry .= "----------------------------------------\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Return success response
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Test callback is working',
    'method' => $_SERVER['REQUEST_METHOD'],
    'timestamp' => date('Y-m-d H:i:s')
]);
?> 