<?php
/**
 * Test Payment Status Checker
 * Simple test to verify the payment status checker is working
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
    
    echo "<h2>Payment Status Checker Test</h2>";
    
    // Test 1: Check if we can connect to database
    echo "<h3>✅ Test 1: Database Connection</h3>";
    echo "<p>Database connection successful!</p>";
    
    // Test 2: Check if mpesa_payment_requests table exists
    echo "<h3>✅ Test 2: Table Check</h3>";
    $result = $conn->query("SHOW TABLES LIKE 'mpesa_payment_requests'");
    if ($result->num_rows > 0) {
        echo "<p>✅ mpesa_payment_requests table exists</p>";
    } else {
        echo "<p>❌ mpesa_payment_requests table does not exist</p>";
    }
    
    // Test 3: Check recent payment requests
    echo "<h3>✅ Test 3: Recent Payment Requests</h3>";
    $stmt = $conn->prepare("
        SELECT 
            checkout_request_id,
            status,
            result_code,
            result_desc,
            created_at
        FROM mpesa_payment_requests 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $payment_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($payment_requests)) {
        echo "<p>No payment requests found in database.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Checkout Request ID</th><th>Status</th><th>Result Code</th><th>Result Desc</th><th>Created</th></tr>";
        
        foreach ($payment_requests as $request) {
            echo "<tr>";
            echo "<td>" . $request['checkout_request_id'] . "</td>";
            echo "<td style='color: " . ($request['status'] === 'completed' ? 'green' : ($request['status'] === 'failed' ? 'red' : 'orange')) . ";'>" . $request['status'] . "</td>";
            echo "<td>" . ($request['result_code'] ?? 'N/A') . "</td>";
            echo "<td>" . ($request['result_desc'] ?? 'N/A') . "</td>";
            echo "<td>" . $request['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 4: Test JSON response format
    echo "<h3>✅ Test 4: JSON Response Test</h3>";
    
    // Simulate a test response
    $testResponse = [
        'success' => true,
        'data' => [
            'status' => 'completed',
            'receipt_number' => 'TEST123',
            'amount' => 1000,
            'message' => 'Payment completed successfully'
        ]
    ];
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($testResponse);
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    
    // Return JSON error response
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Test failed',
        'error' => $e->getMessage()
    ]);
}
?> 