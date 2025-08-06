<?php
/**
 * Auto Create Monthly Records Helper
 * Call this function after creating a new booking to automatically populate monthly_rent_payments
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/monthly_payment_tracker.php';

/**
 * Automatically create monthly payment records for a new booking
 * Call this function after creating a booking
 */
function autoCreateMonthlyRecords($bookingId) {
    global $conn;
    
    try {
        $tracker = new MonthlyPaymentTracker($conn);
        
        // This will automatically create the monthly records
        $payments = $tracker->getMonthlyPayments($bookingId);
        
        return [
            'success' => true,
            'message' => 'Monthly payment records created successfully',
            'count' => count($payments)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to create monthly records: ' . $e->getMessage()
        ];
    }
}

/**
 * Test function to manually create records for existing bookings
 */
function createRecordsForExistingBookings() {
    global $conn;
    
    echo "<h2>Create Monthly Records for Existing Bookings</h2>";
    
    // Get all confirmed bookings that don't have monthly records
    $stmt = $conn->prepare("
        SELECT rb.id, rb.start_date, rb.end_date, rb.monthly_rent, rb.security_deposit
        FROM rental_bookings rb
        LEFT JOIN monthly_rent_payments mrp ON rb.id = mrp.booking_id
        WHERE rb.status IN ('confirmed', 'paid') 
        AND mrp.booking_id IS NULL
        ORDER BY rb.id
    ");
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($bookings)) {
        echo "<p style='color: green;'>✅ All bookings already have monthly records!</p>";
        return;
    }
    
    echo "<p>Found " . count($bookings) . " bookings without monthly records:</p>";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($bookings as $booking) {
        echo "<h4>Processing Booking ID: " . $booking['id'] . "</h4>";
        echo "<ul>";
        echo "<li>Start Date: " . $booking['start_date'] . "</li>";
        echo "<li>End Date: " . $booking['end_date'] . "</li>";
        echo "<li>Monthly Rent: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
        echo "<li>Security Deposit: KSh " . number_format($booking['security_deposit'], 2) . "</li>";
        echo "</ul>";
        
        $result = autoCreateMonthlyRecords($booking['id']);
        
        if ($result['success']) {
            echo "<p style='color: green;'>✅ Created " . $result['count'] . " monthly records</p>";
            $successCount++;
        } else {
            echo "<p style='color: red;'>❌ Error: " . $result['message'] . "</p>";
            $errorCount++;
        }
        
        echo "<hr>";
    }
    
    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li>✅ Successful: $successCount</li>";
    echo "<li>❌ Errors: $errorCount</li>";
    echo "</ul>";
}

// Test the functions
if (isset($_GET['test'])) {
    echo "<h2>Test Auto Create Monthly Records</h2>";
    
    // Test with a specific booking ID
    $testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 32;
    
    echo "<h3>Testing with Booking ID: $testBookingId</h3>";
    
    $result = autoCreateMonthlyRecords($testBookingId);
    
    if ($result['success']) {
        echo "<p style='color: green;'>✅ " . $result['message'] . "</p>";
        echo "<p>Created " . $result['count'] . " monthly records</p>";
    } else {
        echo "<p style='color: red;'>❌ " . $result['message'] . "</p>";
    }
}

// Create records for all existing bookings
if (isset($_GET['create_all'])) {
    createRecordsForExistingBookings();
}

echo "<h3>Usage:</h3>";
echo "<ul>";
echo "<li><a href='?test=1&booking_id=32'>Test with Booking ID 32</a></li>";
echo "<li><a href='?create_all=1'>Create records for all existing bookings</a></li>";
echo "</ul>";

echo "<h3>Integration:</h3>";
echo "<p>To automatically create records when a new booking is made, add this to your booking creation code:</p>";
echo "<pre>";
echo "// After creating a booking
require_once 'auto_create_monthly_records.php';
autoCreateMonthlyRecords(\$newBookingId);
";
echo "</pre>";
?> 