<?php
// Test the booking process
require_once '../config/db.php';

echo "<h2>Testing Booking Process</h2>";

try {
    // Test 1: Check if rental_bookings table exists
    echo "<h3>Test 1: Table Structure</h3>";
    $stmt = $conn->prepare("SHOW TABLES LIKE 'rental_bookings'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p>✅ rental_bookings table exists</p>";
        
        // Check table structure
        $stmt = $conn->prepare("DESCRIBE rental_bookings");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Columns in rental_bookings table:</p>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li>" . $column['Field'] . " (" . $column['Type'] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ rental_bookings table does NOT exist</p>";
    }
    
    // Test 2: Check if houses table has required columns
    echo "<h3>Test 2: Houses Table</h3>";
    $stmt = $conn->prepare("DESCRIBE houses");
    $stmt->execute();
    $houseColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['id', 'price', 'landlord_id', 'status'];
    $missingColumns = [];
    
    foreach ($requiredColumns as $col) {
        $found = false;
        foreach ($houseColumns as $column) {
            if ($column['Field'] === $col) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missingColumns[] = $col;
        }
    }
    
    if (empty($missingColumns)) {
        echo "<p>✅ All required columns exist in houses table</p>";
    } else {
        echo "<p>❌ Missing columns in houses table: " . implode(', ', $missingColumns) . "</p>";
    }
    
    // Test 3: Check if we can insert a test booking
    echo "<h3>Test 3: Test Booking Insert</h3>";
    
    // Get a sample house
    $stmt = $conn->prepare("SELECT id, price, landlord_id FROM houses WHERE status = 1 LIMIT 1");
    $stmt->execute();
    $house = $stmt->fetch();
    
    if ($house) {
        echo "<p>✅ Found sample house: ID " . $house['id'] . ", Price: " . $house['price'] . "</p>";
        
        // Try to insert a test booking
        $testStmt = $conn->prepare("
            INSERT INTO rental_bookings (
                house_id, user_id, landlord_id, start_date, end_date, 
                special_requests, status, created_at
            ) VALUES (?, 1, ?, '2025-02-01', '2026-02-01', 'Test booking', 'pending', NOW())
        ");
        
        if ($testStmt->execute([$house['id'], $house['landlord_id']])) {
            $testBookingId = $conn->lastInsertId();
            echo "<p>✅ Successfully inserted test booking with ID: " . $testBookingId . "</p>";
            
            // Clean up - delete the test booking
            $deleteStmt = $conn->prepare("DELETE FROM rental_bookings WHERE id = ?");
            $deleteStmt->execute([$testBookingId]);
            echo "<p>✅ Test booking cleaned up</p>";
        } else {
            echo "<p>❌ Failed to insert test booking</p>";
        }
    } else {
        echo "<p>❌ No available houses found for testing</p>";
    }
    
    // Test 4: Check API endpoint
    echo "<h3>Test 4: API Endpoint</h3>";
    $apiFile = __DIR__ . '/api/book.php';
    if (file_exists($apiFile)) {
        echo "<p>✅ API file exists: " . $apiFile . "</p>";
    } else {
        echo "<p>❌ API file not found: " . $apiFile . "</p>";
    }
    
    // Test 5: Check BookingController
    echo "<h3>Test 5: BookingController</h3>";
    $controllerFile = __DIR__ . '/controllers/BookingController.php';
    if (file_exists($controllerFile)) {
        echo "<p>✅ BookingController file exists</p>";
        
        // Check if class can be loaded
        require_once $controllerFile;
        if (class_exists('BookingController')) {
            echo "<p>✅ BookingController class can be loaded</p>";
        } else {
            echo "<p>❌ BookingController class cannot be loaded</p>";
        }
    } else {
        echo "<p>❌ BookingController file not found</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>If all tests pass, the booking process should work correctly.</p>";
echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
?> 