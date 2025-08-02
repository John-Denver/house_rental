<?php
/**
 * Test JSON Response
 * Simple test to verify JSON output works correctly
 */

// Prevent any output before JSON response
ob_start();

// Disable error display but keep logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/db.php';

// Clear any output buffer
ob_clean();

// Set JSON content type
header('Content-Type: application/json');

try {
    // Test database connection
    if ($conn->ping()) {
        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?> 