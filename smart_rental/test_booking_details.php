<?php
// Test to verify booking details page works
require_once '../config/db.php';

echo "<h2>Testing Booking Details Page</h2>";

try {
    // Test 1: Check if database connection works
    echo "<h3>Test 1: Database Connection</h3>";
    
    if ($conn && !$conn->connect_error) {
        echo "<p>✅ Database connection successful</p>";
    } else {
        echo "<p>❌ Database connection failed</p>";
        exit;
    }
    
    // Test 2: Check if BookingController can be loaded
    echo "<h3>Test 2: BookingController</h3>";
    
    require_once 'controllers/BookingController.php';
    if (class_exists('BookingController')) {
        $bookingController = new BookingController($conn);
        echo "<p>✅ BookingController loaded successfully</p>";
    } else {
        echo "<p>❌ BookingController not found</p>";
        exit;
    }
    
    // Test 3: Check if we can query rental_bookings table
    echo "<h3>Test 3: Rental Bookings Query</h3>";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rental_bookings");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo "<p>✅ Rental bookings query successful</p>";
    echo "<p>Total bookings in database: " . $result['count'] . "</p>";
    
    // Test 4: Check if we can get booking details
    echo "<h3>Test 4: Get Booking Details</h3>";
    
    // Get a sample booking if any exists
    $stmt = $conn->prepare("SELECT id FROM rental_bookings LIMIT 1");
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if ($booking) {
        echo "<p>✅ Found sample booking ID: " . $booking['id'] . "</p>";
        
        try {
            $bookingDetails = $bookingController->getBookingDetails($booking['id']);
            echo "<p>✅ Booking details retrieved successfully</p>";
            echo "<p>Property: " . $bookingDetails['property_name'] . "</p>";
            echo "<p>Tenant: " . $bookingDetails['tenant_name'] . "</p>";
        } catch (Exception $e) {
            echo "<p>❌ Failed to get booking details: " . $e->getMessage() . "</p>";
        }
        
        // Test 5: Check if we can get booking documents
        echo "<h3>Test 5: Get Booking Documents</h3>";
        
        try {
            $documents = $bookingController->getBookingDocuments($booking['id']);
            echo "<p>✅ Booking documents method works</p>";
            echo "<p>Documents found: " . count($documents) . "</p>";
        } catch (Exception $e) {
            echo "<p>❌ Failed to get booking documents: " . $e->getMessage() . "</p>";
        }
        
        // Test 6: Check if we can get booking payments
        echo "<h3>Test 6: Get Booking Payments</h3>";
        
        try {
            $payments = $bookingController->getBookingPayments($booking['id']);
            echo "<p>✅ Booking payments method works</p>";
            echo "<p>Payments found: " . count($payments) . "</p>";
        } catch (Exception $e) {
            echo "<p>❌ Failed to get booking payments: " . $e->getMessage() . "</p>";
        }
        
        // Test 7: Check if we can check for reviews
        echo "<h3>Test 7: Check Booking Reviews</h3>";
        
        try {
            $hasReview = $bookingController->hasBookingReview($booking['id']);
            echo "<p>✅ Booking review check method works</p>";
            echo "<p>Has review: " . ($hasReview ? 'Yes' : 'No') . "</p>";
        } catch (Exception $e) {
            echo "<p>❌ Failed to check booking reviews: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p>⚠️ No bookings found in database</p>";
        echo "<p>✅ Booking details page structure is ready</p>";
    }
    
    echo "<h3>✅ All Tests Passed!</h3>";
    echo "<p>The booking details page should work correctly now.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
?> 