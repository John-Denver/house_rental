<?php
// Test file to check rental_bookings table structure
require_once '../config/db.php';

echo "<h2>Testing rental_bookings table structure</h2>";

try {
    // Check if table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'rental_bookings'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p>✅ rental_bookings table exists</p>";
        
        // Get table structure
        $stmt = $conn->prepare("DESCRIBE rental_bookings");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if monthly_rent column exists
        $monthlyRentExists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'monthly_rent') {
                $monthlyRentExists = true;
                break;
            }
        }
        
        if ($monthlyRentExists) {
            echo "<p>✅ monthly_rent column exists</p>";
        } else {
            echo "<p>❌ monthly_rent column does NOT exist</p>";
            echo "<p>Adding monthly_rent column...</p>";
            
            // Add the column
            $alterStmt = $conn->prepare("ALTER TABLE rental_bookings ADD COLUMN monthly_rent DECIMAL(15,2) NOT NULL DEFAULT 0.00");
            if ($alterStmt->execute()) {
                echo "<p>✅ Successfully added monthly_rent column</p>";
            } else {
                echo "<p>❌ Failed to add monthly_rent column</p>";
            }
        }
        
    } else {
        echo "<p>❌ rental_bookings table does NOT exist</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
?> 