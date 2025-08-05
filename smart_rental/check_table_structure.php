<?php
/**
 * Check Table Structure
 * Check the structure of mpesa_payment_requests table
 */

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
} catch (Exception $e) {
    echo "<h2>Database Connection: ❌ Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Table Structure Check</h2>";

// Check mpesa_payment_requests table structure
$query = "DESCRIBE mpesa_payment_requests";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<h3>mpesa_payment_requests Table Structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Could not get table structure.</p>";
}

// Check if callback_data column exists
$query = "SHOW COLUMNS FROM mpesa_payment_requests LIKE 'callback_data'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✅ callback_data column exists</p>";
} else {
    echo "<p style='color: red;'>❌ callback_data column does not exist</p>";
    echo "<p>This is why the callback is failing!</p>";
    
    // Show how to add the column
    echo "<h3>To fix this, run this SQL:</h3>";
    echo "<pre>ALTER TABLE mpesa_payment_requests ADD COLUMN callback_data TEXT NULL;</pre>";
}

// Check recent payment requests
echo "<h3>Recent Payment Requests:</h3>";
$query = "SELECT * FROM mpesa_payment_requests ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Checkout Request ID</th><th>Status</th><th>Result Code</th><th>Result Desc</th><th>Created</th><th>Updated</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $statusColor = $row['status'] === 'completed' ? 'green' : ($row['status'] === 'processing' ? 'orange' : 'red');
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['checkout_request_id'] . "</td>";
        echo "<td style='font-weight: bold; color: $statusColor;'>" . $row['status'] . "</td>";
        echo "<td>" . ($row['result_code'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['result_desc'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>" . $row['updated_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment requests found.</p>";
}
?> 