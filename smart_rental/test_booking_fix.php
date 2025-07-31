<?php
// Test to verify booking process works after fixes
require_once '../config/db.php';

echo "<h2>Testing Booking Process After Fixes</h2>";

try {
    // Test 1: Check if we can create a BookingController instance
    echo "<h3>Test 1: BookingController</h3>";
    
    require_once 'controllers/BookingController.php';
    if (class_exists('BookingController')) {
        $bookingController = new BookingController($conn);
        echo "<p>✅ BookingController created successfully</p>";
    } else {
        echo "<p>❌ BookingController class not found</p>";
        exit;
    }
    
    // Test 2: Check if we can prepare the booking data
    echo "<h3>Test 2: Booking Data Preparation</h3>";
    
    // Get a sample house
    $stmt = $conn->prepare("SELECT id, price, landlord_id FROM houses WHERE status = 1 LIMIT 1");
    $stmt->execute();
    $house = $stmt->fetch();
    
    if ($house) {
        echo "<p>✅ Found sample house: ID " . $house['id'] . "</p>";
        
        // Test booking data preparation
        $bookingData = [
            'house_id' => $house['id'],
            'start_date' => '2025-02-01',
            'special_requests' => 'Test request'
        ];
        
        echo "<p>✅ Booking data prepared successfully</p>";
        echo "<pre>" . print_r($bookingData, true) . "</pre>";
        
        // Test 3: Check if we can validate the data
        echo "<h3>Test 3: Data Validation</h3>";
        
        try {
            // Use reflection to access private method for testing
            $reflection = new ReflectionClass($bookingController);
            $validateMethod = $reflection->getMethod('validateBookingData');
            $validateMethod->setAccessible(true);
            
            $validateMethod->invoke($bookingController, $bookingData);
            echo "<p>✅ Data validation passed</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Data validation failed: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p>❌ No available houses found for testing</p>";
    }
    
    // Test 4: Check if we can prepare the SQL statement
    echo "<h3>Test 4: SQL Statement Preparation</h3>";
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO rental_bookings (
                house_id, landlord_id, user_id, start_date, end_date,
                special_requests, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        if ($stmt) {
            echo "<p>✅ SQL statement prepared successfully</p>";
        } else {
            echo "<p>❌ Failed to prepare SQL statement</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ SQL preparation error: " . $e->getMessage() . "</p>";
    }
    
    // Test 5: Check PHP version compatibility
    echo "<h3>Test 5: PHP Version Compatibility</h3>";
    echo "<p>PHP Version: " . PHP_VERSION . "</p>";
    
    if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
        echo "<p>✅ PHP version supports FILTER_SANITIZE_FULL_SPECIAL_CHARS</p>";
    } else {
        echo "<p>⚠️ PHP version may have compatibility issues</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>If all tests pass, the booking process should work correctly.</p>";
echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
?> 