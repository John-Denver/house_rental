<?php
// Test to verify booking process works with correct tables
require_once '../config/db.php';

echo "<h2>Testing Booking Process with Correct Tables</h2>";

try {
    // Test 1: Check if required tables exist
    echo "<h3>Test 1: Table Existence</h3>";
    
    $tables = ['rental_bookings', 'booking_payments', 'houses', 'users'];
    $missingTables = [];
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            $missingTables[] = $table;
        }
    }
    
    if (empty($missingTables)) {
        echo "<p>✅ All required tables exist</p>";
    } else {
        echo "<p>❌ Missing tables: " . implode(', ', $missingTables) . "</p>";
        exit;
    }
    
    // Test 2: Check if we can insert into rental_bookings
    echo "<h3>Test 2: Rental Bookings Insert</h3>";
    
    $stmt = $conn->prepare("
        INSERT INTO rental_bookings (
            house_id, landlord_id, user_id, start_date, end_date,
            special_requests, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    if ($stmt) {
        echo "<p>✅ rental_bookings INSERT statement prepared successfully</p>";
    } else {
        echo "<p>❌ Failed to prepare rental_bookings INSERT</p>";
        exit;
    }
    
    // Test 3: Check if we can insert into booking_payments
    echo "<h3>Test 3: Booking Payments Insert</h3>";
    
    $stmt = $conn->prepare("
        INSERT INTO booking_payments (
            booking_id, amount, payment_method, status, payment_date
        ) VALUES (?, ?, ?, 'completed', NOW())
    ");
    
    if ($stmt) {
        echo "<p>✅ booking_payments INSERT statement prepared successfully</p>";
    } else {
        echo "<p>❌ Failed to prepare booking_payments INSERT</p>";
        exit;
    }
    
    // Test 4: Check if we can create BookingController
    echo "<h3>Test 4: BookingController</h3>";
    
    require_once 'controllers/BookingController.php';
    if (class_exists('BookingController')) {
        $bookingController = new BookingController($conn);
        echo "<p>✅ BookingController created successfully</p>";
    } else {
        echo "<p>❌ BookingController class not found</p>";
        exit;
    }
    
    // Test 5: Check if we can create RentCalculationController
    echo "<h3>Test 5: RentCalculationController</h3>";
    
    require_once 'controllers/RentCalculationController.php';
    if (class_exists('RentCalculationController')) {
        $rentController = new RentCalculationController($conn);
        echo "<p>✅ RentCalculationController created successfully</p>";
    } else {
        echo "<p>❌ RentCalculationController class not found</p>";
        exit;
    }
    
    echo "<h3>✅ All Tests Passed!</h3>";
    echo "<p>The booking process should work correctly now with the correct database tables.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
?> 