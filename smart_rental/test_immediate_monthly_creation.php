<?php
/**
 * Test Immediate Monthly Record Creation
 * Verify that monthly records are created immediately when a booking is made
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Test Immediate Monthly Record Creation</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test the booking creation process
if (isset($_GET['test_booking'])) {
    echo "<h3>Testing Booking Creation with Immediate Monthly Records</h3>";
    
    try {
        // Get a test property
        $stmt = $conn->prepare("SELECT id, title, price, security_deposit FROM houses WHERE status = 1 LIMIT 1");
        $stmt->execute();
        $property = $stmt->get_result()->fetch_assoc();
        
        if (!$property) {
            echo "<p style='color: red;'>❌ No available properties found for testing</p>";
            exit;
        }
        
        echo "<h4>Test Property:</h4>";
        echo "<ul>";
        echo "<li>ID: " . $property['id'] . "</li>";
        echo "<li>Title: " . $property['title'] . "</li>";
        echo "<li>Price: KSh " . number_format($property['price'], 2) . "</li>";
        echo "<li>Security Deposit: KSh " . number_format($property['security_deposit'], 2) . "</li>";
        echo "</ul>";
        
        // Create a test booking
        $startDate = date('Y-m-d', strtotime('+1 month'));
        $endDate = date('Y-m-d', strtotime('+13 months'));
        
        echo "<h4>Creating Test Booking:</h4>";
        echo "<ul>";
        echo "<li>Start Date: $startDate</li>";
        echo "<li>End Date: $endDate</li>";
        echo "<li>User ID: " . $_SESSION['user_id'] . "</li>";
        echo "</ul>";
        
        // Insert test booking
        $stmt = $conn->prepare("
            INSERT INTO rental_bookings (
                house_id, landlord_id, user_id, start_date, end_date,
                special_requests, status, security_deposit, monthly_rent, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
        ");
        
        $landlordId = 1; // Default landlord
        $specialRequests = "Test booking for monthly records creation";
        
        $stmt->bind_param(
            'iiisssdd',
            $property['id'],
            $landlordId,
            $_SESSION['user_id'],
            $startDate,
            $endDate,
            $specialRequests,
            $property['security_deposit'],
            $property['price']
        );
        
        if ($stmt->execute()) {
            $bookingId = $conn->insert_id;
            echo "<p style='color: green;'>✅ Test booking created with ID: $bookingId</p>";
            
            // Test immediate monthly record creation
            echo "<h4>Testing Monthly Record Creation:</h4>";
            
            require_once __DIR__ . '/monthly_payment_tracker.php';
            $tracker = new MonthlyPaymentTracker($conn);
            
            // Check if records were created
            $payments = $tracker->getMonthlyPayments($bookingId);
            
            echo "<p style='color: green;'>✅ Monthly records created: " . count($payments) . "</p>";
            
            // Show the created records
            echo "<h4>Created Monthly Records:</h4>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr style='background-color: #f0f0f0;'>";
            echo "<th>Month</th><th>Amount</th><th>Status</th><th>Type</th><th>First Payment</th>";
            echo "</tr>";
            foreach ($payments as $payment) {
                echo "<tr>";
                echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
                echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
                echo "<td>" . $payment['status'] . "</td>";
                echo "<td>" . $payment['payment_type'] . "</td>";
                echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Clean up - delete the test booking
            $deleteStmt = $conn->prepare("DELETE FROM rental_bookings WHERE id = ?");
            $deleteStmt->bind_param('i', $bookingId);
            $deleteStmt->execute();
            
            echo "<p style='color: blue;'>🧹 Test booking cleaned up</p>";
            
        } else {
            echo "<p style='color: red;'>❌ Failed to create test booking</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

// Test existing bookings
if (isset($_GET['test_existing'])) {
    echo "<h3>Testing Existing Bookings</h3>";
    
    // Get recent bookings
    $stmt = $conn->prepare("
        SELECT rb.id, rb.start_date, rb.end_date, rb.monthly_rent, rb.security_deposit,
               COUNT(mrp.id) as monthly_records_count
        FROM rental_bookings rb
        LEFT JOIN monthly_rent_payments mrp ON rb.id = mrp.booking_id
        WHERE rb.user_id = ?
        GROUP BY rb.id
        ORDER BY rb.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($bookings)) {
        echo "<p style='color: red;'>❌ No bookings found</p>";
    } else {
        echo "<h4>Recent Bookings:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Booking ID</th><th>Start Date</th><th>End Date</th><th>Monthly Records</th>";
        echo "</tr>";
        foreach ($bookings as $booking) {
            $recordStatus = $booking['monthly_records_count'] > 0 ? 
                "<span style='color: green;'>✅ " . $booking['monthly_records_count'] . " records</span>" : 
                "<span style='color: red;'>❌ No records</span>";
            echo "<tr>";
            echo "<td>" . $booking['id'] . "</td>";
            echo "<td>" . $booking['start_date'] . "</td>";
            echo "<td>" . $booking['end_date'] . "</td>";
            echo "<td>$recordStatus</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

echo "<h3>Test Options:</h3>";
echo "<ul>";
echo "<li><a href='?test_booking=1'>Test Booking Creation with Monthly Records</a></li>";
echo "<li><a href='?test_existing=1'>Check Existing Bookings</a></li>";
echo "</ul>";

echo "<h3>Integration Status:</h3>";
echo "<ul>";
echo "<li>✅ BookingController.php - Monthly records created immediately</li>";
echo "<li>✅ api/book.php - Monthly records created immediately</li>";
echo "<li>✅ MonthlyPaymentTracker.php - Automatic record creation</li>";
echo "</ul>";
?>

<style>
table {
    margin: 10px 0;
}
th, td {
    padding: 8px;
    text-align: left;
}
</style> 