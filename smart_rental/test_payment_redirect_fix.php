<?php
/**
 * Test Payment Redirect Fix
 * Verify that the payment page no longer redirects to booking details
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Test Payment Redirect Fix</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 32;

echo "<h3>Testing with Booking ID: $testBookingId</h3>";

// Get booking details first
$stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
$stmt->bind_param('i', $testBookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<p style='color: red;'>❌ Booking not found!</p>";
    exit;
}

echo "<h4>Booking Details:</h4>";
echo "<ul>";
echo "<li>ID: " . $booking['id'] . "</li>";
echo "<li>Status: " . $booking['status'] . "</li>";
echo "<li>Payment Status: " . $booking['payment_status'] . "</li>";
echo "<li>Start Date: " . $booking['start_date'] . "</li>";
echo "<li>End Date: " . $booking['end_date'] . "</li>";
echo "<li>Monthly Rent: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
echo "<li>Security Deposit: KSh " . number_format($booking['security_deposit'], 2) . "</li>";
echo "</ul>";

// Test the monthly payment tracker
echo "<h4>Testing Monthly Payment Tracker:</h4>";

try {
    require_once __DIR__ . '/monthly_payment_tracker.php';
    $tracker = new MonthlyPaymentTracker($conn);
    
    // Get next payment due
    $nextPaymentDue = $tracker->getNextPaymentDue($testBookingId);
    
    if ($nextPaymentDue) {
        echo "<p style='color: green;'>✅ Next payment due: " . date('F Y', strtotime($nextPaymentDue['month'])) . " - KSh " . number_format($nextPaymentDue['amount'], 2) . "</p>";
        
        // Test the payment page access
        echo "<h4>Payment Page Access Test:</h4>";
        echo "<p>Click the links below to test if the payment page loads correctly:</p>";
        echo "<ul>";
        echo "<li><a href='booking_payment.php?id=$testBookingId' target='_blank'>Payment Page (No Type)</a></li>";
        echo "<li><a href='booking_payment.php?id=$testBookingId&type=monthly_payment' target='_blank'>Payment Page (Monthly Payment)</a></li>";
        echo "<li><a href='booking_payment.php?id=$testBookingId&type=initial' target='_blank'>Payment Page (Initial Payment)</a></li>";
        echo "</ul>";
        
        echo "<h4>Expected Behavior:</h4>";
        echo "<ul>";
        echo "<li>✅ Payment page should load (not redirect to booking details)</li>";
        echo "<li>✅ Should show payment form for " . date('F Y', strtotime($nextPaymentDue['month'])) . "</li>";
        echo "<li>✅ Amount should be KSh " . number_format($nextPaymentDue['amount'], 2) . "</li>";
        echo "</ul>";
        
    } else {
        echo "<p style='color: red;'>❌ No next payment due found</p>";
        
        // Check if all payments are completed
        $summary = $tracker->getPaymentSummary($testBookingId);
        if ($summary['paid_months'] == $summary['total_months']) {
            echo "<p style='color: green;'>✅ All payments completed!</p>";
        } else {
            echo "<p style='color: red;'>❌ Issue: No next payment due but not all payments completed</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "<li><a href='booking_details.php?id=$testBookingId'>View Booking Details</a></li>";
echo "<li><a href='test_final_fix.php?booking_id=$testBookingId'>Test Fixed Tracker</a></li>";
echo "</ul>";
?>

<style>
.btn {
    margin: 10px 0;
}
</style> 