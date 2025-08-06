<?php
/**
 * Test script to verify payment_type column in mpesa_payment_requests table
 */

require_once '../config/db.php';

echo "<h2>Testing Payment Type Column</h2>";

try {
    // Check if payment_type column exists
    $result = $conn->query("DESCRIBE mpesa_payment_requests");
    
    if ($result) {
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if payment_type column exists
        $hasPaymentType = false;
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] === 'payment_type') {
                $hasPaymentType = true;
                break;
            }
        }
        
        if ($hasPaymentType) {
            echo "<p style='color: green;'>✅ payment_type column exists!</p>";
        } else {
            echo "<p style='color: red;'>❌ payment_type column does not exist</p>";
            echo "<p>You need to run the add_payment_type_column.php script first.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Failed to describe table: " . $conn->error . "</p>";
    }
    
    // Show some sample data
    echo "<h3>Sample Data:</h3>";
    $dataResult = $conn->query("SELECT id, booking_id, amount, payment_type, status, created_at FROM mpesa_payment_requests ORDER BY id DESC LIMIT 5");
    
    if ($dataResult && $dataResult->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Booking ID</th><th>Amount</th><th>Payment Type</th><th>Status</th><th>Created</th></tr>";
        
        while ($row = $dataResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['booking_id'] . "</td>";
            echo "<td>KSh " . number_format($row['amount'], 2) . "</td>";
            echo "<td>" . ($row['payment_type'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No data found in mpesa_payment_requests table</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 