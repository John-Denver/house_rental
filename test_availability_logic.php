<?php
/**
 * Test script to verify availability logic is working
 * Run this script to check if unit automation is functioning correctly
 */

require_once 'config/db.php';
require_once 'smart_rental/controllers/BookingController.php';

echo "<h1>Availability Logic Test</h1>\n";

try {
    $bookingController = new BookingController($conn);
    
    // Get a property with multiple units for testing
    $stmt = $conn->prepare("
        SELECT id, house_no, total_units, available_units 
        FROM houses 
        WHERE total_units > 1 AND available_units > 0 
        LIMIT 1
    ");
    $stmt->execute();
    $property = $stmt->get_result()->fetch_assoc();
    
    if (!$property) {
        echo "<p style='color: red;'>❌ No suitable property found for testing (need property with multiple units and available units)</p>\n";
        exit;
    }
    
    echo "<h2>Testing Property: {$property['house_no']}</h2>\n";
    echo "<p>Initial state: {$property['available_units']}/{$property['total_units']} units available</p>\n";
    
    // Get a pending booking for this property
    $stmt = $conn->prepare("
        SELECT id, status, user_id 
        FROM rental_bookings 
        WHERE house_id = ? AND status = 'pending' 
        LIMIT 1
    ");
    $stmt->bind_param('i', $property['id']);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        echo "<p style='color: orange;'>⚠️ No pending bookings found for this property. Creating a test booking...</p>\n";
        
        // Create a test booking
        $testUserId = 1; // Use a test user ID
        $stmt = $conn->prepare("
            INSERT INTO rental_bookings (house_id, user_id, start_date, end_date, status, created_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 MONTH), DATE_ADD(NOW(), INTERVAL 13 MONTH), 'pending', NOW())
        ");
        $stmt->bind_param('ii', $property['id'], $testUserId);
        $stmt->execute();
        $bookingId = $conn->insert_id;
        
        echo "<p>✅ Created test booking #$bookingId</p>\n";
        
        // Get the booking details
        $stmt = $conn->prepare("SELECT id, status FROM rental_bookings WHERE id = ?");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
    }
    
    echo "<h3>Test 1: Confirm Booking (Should Decrement Units)</h3>\n";
    
    // Get units before confirmation
    $stmt = $conn->prepare("SELECT available_units FROM houses WHERE id = ?");
    $stmt->bind_param('i', $property['id']);
    $stmt->execute();
    $beforeUnits = $stmt->get_result()->fetch_assoc()['available_units'];
    
    echo "<p>Units before confirmation: $beforeUnits</p>\n";
    
    // Confirm the booking
    try {
        $bookingController->updateBookingStatus($booking['id'], 'confirmed', 'Test automation', 1);
        echo "<p style='color: green;'>✅ Booking confirmed successfully</p>\n";
        
        // Get units after confirmation
        $stmt = $conn->prepare("SELECT available_units FROM houses WHERE id = ?");
        $stmt->bind_param('i', $property['id']);
        $stmt->execute();
        $afterUnits = $stmt->get_result()->fetch_assoc()['available_units'];
        
        echo "<p>Units after confirmation: $afterUnits</p>\n";
        
        if ($afterUnits == $beforeUnits - 1) {
            echo "<p style='color: green;'>✅ Unit decrement working correctly!</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Unit decrement failed! Expected: " . ($beforeUnits - 1) . ", Got: $afterUnits</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Failed to confirm booking: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h3>Test 2: Cancel Booking (Should Increment Units)</h3>\n";
    
    // Get units before cancellation
    $stmt = $conn->prepare("SELECT available_units FROM houses WHERE id = ?");
    $stmt->bind_param('i', $property['id']);
    $stmt->execute();
    $beforeUnits = $stmt->get_result()->fetch_assoc()['available_units'];
    
    echo "<p>Units before cancellation: $beforeUnits</p>\n";
    
    // Cancel the booking
    try {
        $bookingController->updateBookingStatus($booking['id'], 'cancelled', 'Test automation', 1);
        echo "<p style='color: green;'>✅ Booking cancelled successfully</p>\n";
        
        // Get units after cancellation
        $stmt = $conn->prepare("SELECT available_units FROM houses WHERE id = ?");
        $stmt->bind_param('i', $property['id']);
        $stmt->execute();
        $afterUnits = $stmt->get_result()->fetch_assoc()['available_units'];
        
        echo "<p>Units after cancellation: $afterUnits</p>\n";
        
        if ($afterUnits == $beforeUnits + 1) {
            echo "<p style='color: green;'>✅ Unit increment working correctly!</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Unit increment failed! Expected: " . ($beforeUnits + 1) . ", Got: $afterUnits</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Failed to cancel booking: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h3>Test Summary</h3>\n";
    echo "<p>✅ Availability logic test completed. Check the results above.</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Test failed: " . $e->getMessage() . "</p>\n";
}
?> 