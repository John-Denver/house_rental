<?php
// Simple test page to verify callback URL accessibility
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$headers = getallheaders();
$body = file_get_contents('php://input');

$response = [
    'status' => 'success',
    'message' => 'Callback URL is accessible',
    'method' => $method,
    'headers' => $headers,
    'body' => $body,
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
];

echo json_encode($response, JSON_PRETTY_PRINT);
?> 