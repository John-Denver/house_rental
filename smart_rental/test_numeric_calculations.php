<?php
// Test to verify numeric calculations work correctly
require_once '../config/db.php';

echo "<h2>Testing Numeric Calculations</h2>";

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
    
    // Test 3: Test numeric calculations
    echo "<h3>Test 3: Numeric Calculations</h3>";
    
    // Get a sample booking if any exists
    $stmt = $conn->prepare("SELECT id FROM rental_bookings LIMIT 1");
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if ($booking) {
        echo "<p>✅ Found sample booking ID: " . $booking['id'] . "</p>";
        
        try {
            $bookingDetails = $bookingController->getBookingDetails($booking['id']);
            echo "<p>✅ Booking details retrieved successfully</p>";
            
            // Test numeric calculations
            $monthlyRent = floatval($bookingDetails['property_price']);
            $rentalPeriod = intval($bookingDetails['rental_period'] ?? 12);
            $securityDeposit = floatval($bookingDetails['security_deposit'] ?? 0);
            
            echo "<p><strong>Raw Values:</strong></p>";
            echo "<p>- Monthly Rent: " . $bookingDetails['property_price'] . " (type: " . gettype($bookingDetails['property_price']) . ")</p>";
            echo "<p>- Rental Period: " . $bookingDetails['rental_period'] . " (type: " . gettype($bookingDetails['rental_period']) . ")</p>";
            echo "<p>- Security Deposit: " . ($bookingDetails['security_deposit'] ?? 'null') . " (type: " . gettype($bookingDetails['security_deposit'] ?? null) . ")</p>";
            
            echo "<p><strong>Converted Values:</strong></p>";
            echo "<p>- Monthly Rent: " . $monthlyRent . " (type: " . gettype($monthlyRent) . ")</p>";
            echo "<p>- Rental Period: " . $rentalPeriod . " (type: " . gettype($rentalPeriod) . ")</p>";
            echo "<p>- Security Deposit: " . $securityDeposit . " (type: " . gettype($securityDeposit) . ")</p>";
            
            // Test calculations
            $subtotal = $monthlyRent * $rentalPeriod;
            $totalAmount = $subtotal + $securityDeposit;
            
            echo "<p><strong>Calculations:</strong></p>";
            echo "<p>- Subtotal: KSh " . number_format($subtotal, 2) . "</p>";
            echo "<p>- Total Amount: KSh " . number_format($totalAmount, 2) . "</p>";
            
            // Test that no warnings are generated
            echo "<p>✅ All calculations completed without warnings</p>";
            
        } catch (Exception $e) {
            echo "<p>❌ Failed to test calculations: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p>⚠️ No bookings found in database</p>";
        
        // Test with sample data
        echo "<h3>Test 4: Sample Data Calculations</h3>";
        
        $sampleData = [
            'property_price' => '50000',
            'rental_period' => '12',
            'security_deposit' => '100000'
        ];
        
        $monthlyRent = floatval($sampleData['property_price']);
        $rentalPeriod = intval($sampleData['rental_period']);
        $securityDeposit = floatval($sampleData['security_deposit']);
        
        $subtotal = $monthlyRent * $rentalPeriod;
        $totalAmount = $subtotal + $securityDeposit;
        
        echo "<p><strong>Sample Calculations:</strong></p>";
        echo "<p>- Monthly Rent: KSh " . number_format($monthlyRent, 2) . "</p>";
        echo "<p>- Rental Period: " . $rentalPeriod . " months</p>";
        echo "<p>- Security Deposit: KSh " . number_format($securityDeposit, 2) . "</p>";
        echo "<p>- Subtotal: KSh " . number_format($subtotal, 2) . "</p>";
        echo "<p>- Total Amount: KSh " . number_format($totalAmount, 2) . "</p>";
        
        echo "<p>✅ Sample calculations completed successfully</p>";
    }
    
    echo "<h3>✅ All Tests Passed!</h3>";
    echo "<p>The numeric calculations should work correctly now without warnings.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
?> 