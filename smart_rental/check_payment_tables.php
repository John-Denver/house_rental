<?php
/**
 * Check Payment Tables
 * Verifies that all payment tracking tables exist and have correct structure
 */

session_start();
require_once '../config/db.php';

echo "<h2>Payment Tables Check</h2>";

try {
    // Check if monthly_rent_payments table exists
    echo "<h3>1. Checking monthly_rent_payments table</h3>";
    $tableExists = $conn->query("SHOW TABLES LIKE 'monthly_rent_payments'");
    if ($tableExists->num_rows > 0) {
        echo "<p style='color: green;'>✅ monthly_rent_payments table exists</p>";
        
        // Check table structure
        $result = $conn->query("DESCRIBE monthly_rent_payments");
        echo "<h4>Table Structure:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ monthly_rent_payments table does not exist</p>";
    }

    // Check if payment_tracking table exists
    echo "<h3>2. Checking payment_tracking table</h3>";
    $tableExists = $conn->query("SHOW TABLES LIKE 'payment_tracking'");
    if ($tableExists->num_rows > 0) {
        echo "<p style='color: green;'>✅ payment_tracking table exists</p>";
        
        // Check table structure
        $result = $conn->query("DESCRIBE payment_tracking");
        echo "<h4>Table Structure:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ payment_tracking table does not exist</p>";
    }

    // Check if booking_payments table exists
    echo "<h3>3. Checking booking_payments table</h3>";
    $tableExists = $conn->query("SHOW TABLES LIKE 'booking_payments'");
    if ($tableExists->num_rows > 0) {
        echo "<p style='color: green;'>✅ booking_payments table exists</p>";
        
        // Check table structure
        $result = $conn->query("DESCRIBE booking_payments");
        echo "<h4>Table Structure:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ booking_payments table does not exist</p>";
    }

    // Check database functions
    echo "<h3>4. Checking Database Functions</h3>";
    
    // Check get_next_unpaid_month function
    try {
        $result = $conn->query("SELECT get_next_unpaid_month(1) as test");
        echo "<p style='color: green;'>✅ get_next_unpaid_month function exists</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ get_next_unpaid_month function does not exist or has error</p>";
    }
    
    // Check has_first_payment_been_made function
    try {
        $result = $conn->query("SELECT has_first_payment_been_made(1) as test");
        echo "<p style='color: green;'>✅ has_first_payment_been_made function exists</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ has_first_payment_been_made function does not exist or has error</p>";
    }

    // Sample data check
    echo "<h3>5. Sample Data Check</h3>";
    
    // Check monthly_rent_payments data
    $result = $conn->query("SELECT COUNT(*) as count FROM monthly_rent_payments");
    $count = $result->fetch_assoc()['count'];
    echo "<p>monthly_rent_payments records: $count</p>";
    
    if ($count > 0) {
        $result = $conn->query("SELECT * FROM monthly_rent_payments LIMIT 3");
        echo "<h4>Sample Records:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>Booking ID</th><th>Month</th><th>Status</th><th>Amount</th><th>Payment Type</th>";
        echo "</tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['booking_id'] . "</td>";
            echo "<td>" . $row['month'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['amount'] . "</td>";
            echo "<td>" . $row['payment_type'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<h3>6. Recommendations</h3>";
    echo "<ul>";
    echo "<li>If any tables are missing, run the setup scripts</li>";
    echo "<li>If functions are missing, run the database setup</li>";
    echo "<li>Test with a specific booking ID using the test scripts</li>";
    echo "</ul>";

    echo "<h3>7. Test Links</h3>";
    echo "<ul>";
    echo "<li><a href='setup_payment_tracking.php'>Setup Payment Tracking</a></li>";
    echo "<li><a href='setup_monthly_payments.php'>Setup Monthly Payments</a></li>";
    echo "<li><a href='test_simple_payment.php?booking_id=1'>Test Simple Payment</a></li>";
    echo "<li><a href='debug_month_detection.php?booking_id=1'>Debug Month Detection</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 