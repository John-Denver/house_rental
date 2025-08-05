<?php
/**
 * Debug Payment Status Checker
 * Simplified version to identify 500 errors
 */

// Start output buffering
ob_start();

// Database connection
$host = "localhost";
$dbname = "house_rental";
$username = "root";
$password = "";

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Database Connection: ✅ Success</h2>";
} catch (Exception $e) {
    echo "<h2>Database Connection: ❌ Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    exit;
}

// Start session
session_start();

echo "<h2>Session Check</h2>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Request Received</h2>";
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    echo "<p>Input: " . json_encode($input) . "</p>";
    
    if (!$input || !isset($input['checkout_request_id'])) {
        echo "<p>❌ Missing checkout request ID</p>";
        exit;
    }
    
    $checkout_request_id = $input['checkout_request_id'];
    echo "<p>Checkout Request ID: $checkout_request_id</p>";
    
    try {
        // Check if mpesa_payment_requests table exists
        $result = $conn->query("SHOW TABLES LIKE 'mpesa_payment_requests'");
        if ($result->num_rows === 0) {
            echo "<p>❌ mpesa_payment_requests table does not exist</p>";
            exit;
        }
        echo "<p>✅ mpesa_payment_requests table exists</p>";
        
        // Check if rental_bookings table exists
        $result = $conn->query("SHOW TABLES LIKE 'rental_bookings'");
        if ($result->num_rows === 0) {
            echo "<p>❌ rental_bookings table does not exist</p>";
            exit;
        }
        echo "<p>✅ rental_bookings table exists</p>";
        
        // Get payment request details
        $stmt = $conn->prepare("
            SELECT pr.*, rb.house_id, rb.user_id, rb.status as booking_status
            FROM mpesa_payment_requests pr
            JOIN rental_bookings rb ON pr.booking_id = rb.id
            WHERE pr.checkout_request_id = ? AND rb.user_id = ?
        ");
        
        if (!$stmt) {
            echo "<p>❌ Failed to prepare statement: " . $conn->error . "</p>";
            exit;
        }
        
        $stmt->bind_param('si', $checkout_request_id, $_SESSION['user_id']);
        $stmt->execute();
        $payment_request = $stmt->get_result()->fetch_assoc();
        
        echo "<p>Payment Request Found: " . ($payment_request ? 'Yes' : 'No') . "</p>";
        
        if ($payment_request) {
            echo "<p>Status: " . $payment_request['status'] . "</p>";
            echo "<p>Result Code: " . $payment_request['result_code'] . "</p>";
            echo "<p>Result Desc: " . $payment_request['result_desc'] . "</p>";
        }
        
        // Test M-Pesa config inclusion
        echo "<h2>Testing M-Pesa Config</h2>";
        try {
            require_once 'mpesa_config.php';
            echo "<p>✅ mpesa_config.php loaded successfully</p>";
            
            // Test access token generation
            $access_token = getMpesaAccessToken();
            if ($access_token) {
                echo "<p>✅ Access token generated: " . substr($access_token, 0, 20) . "...</p>";
            } else {
                echo "<p>❌ Failed to get access token</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ Error loading mpesa_config.php: " . $e->getMessage() . "</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Error: " . $e->getMessage() . "</p>";
        echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
    }
} else {
    echo "<h2>GET Request - No POST data</h2>";
    echo "<p>This page expects a POST request with checkout_request_id</p>";
}

echo "<h2>Test Complete</h2>";
?> 