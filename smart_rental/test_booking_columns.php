<?php
// Test to verify booking process works with correct column names
require_once '../config/db.php';

echo "<h2>Testing Booking Process with Correct Column Names</h2>";

try {
    // Test 1: Check users table structure
    echo "<h3>Test 1: Users Table Structure</h3>";
    
    $stmt = $conn->prepare("DESCRIBE users");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Users table columns:</p>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>" . $column['Field'] . " (" . $column['Type'] . ")</li>";
    }
    echo "</ul>";
    
    // Test 2: Check if we can query users with correct columns
    echo "<h3>Test 2: Users Query</h3>";
    
    $stmt = $conn->prepare("SELECT id, name, username, phone_number FROM users LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<p>✅ Users query works with correct columns</p>";
        echo "<p>Sample user: " . $user['name'] . " (" . $user['username'] . ")</p>";
    } else {
        echo "<p>❌ Users query failed</p>";
        exit;
    }
    
    // Test 3: Check if we can create BookingController
    echo "<h3>Test 3: BookingController</h3>";
    
    require_once 'controllers/BookingController.php';
    if (class_exists('BookingController')) {
        $bookingController = new BookingController($conn);
        echo "<p>✅ BookingController created successfully</p>";
    } else {
        echo "<p>❌ BookingController class not found</p>";
        exit;
    }
    
    // Test 4: Check if we can prepare the booking details query
    echo "<h3>Test 4: Booking Details Query</h3>";
    
    $stmt = $conn->prepare("
        SELECT 
            b.*, 
            h.house_no as property_name,
            h.price as property_price,
            h.location as property_location,
            u.name as tenant_name,
            u.username as tenant_email,
            u.phone_number as tenant_phone
        FROM rental_bookings b
        JOIN houses h ON b.house_id = h.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ");
    
    if ($stmt) {
        echo "<p>✅ Booking details query prepared successfully</p>";
    } else {
        echo "<p>❌ Failed to prepare booking details query</p>";
        exit;
    }
    
    echo "<h3>✅ All Tests Passed!</h3>";
    echo "<p>The booking process should work correctly now with the correct column names.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
?> 