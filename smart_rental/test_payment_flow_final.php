<?php
/**
 * Test Payment Flow - Final
 * Verify the complete payment flow works correctly
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Test Payment Flow - Final</h2>";

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
        
        // Test the payment button
        echo "<h4>Payment Button Test:</h4>";
        echo "<p>Click the button below to test the payment flow:</p>";
        echo "<button type='button' class='btn btn-primary btn-lg' onclick='testPaymentFlow()'>";
        echo "Pay " . date('F Y', strtotime($nextPaymentDue['month'])) . " - KSh " . number_format($nextPaymentDue['amount'], 2);
        echo "</button>";
        
        echo "<h4>Direct Links:</h4>";
        echo "<ul>";
        echo "<li><a href='booking_payment.php?id=$testBookingId' target='_blank'>Direct Payment Page</a></li>";
        echo "<li><a href='booking_payment.php?id=$testBookingId&amount=" . $nextPaymentDue['amount'] . "' target='_blank'>Payment Page with Amount</a></li>";
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

<script>
function testPaymentFlow() {
    console.log('Testing payment flow...');
    
    // Get the next payment due amount
    const amount = <?php echo $nextPaymentDue ? $nextPaymentDue['amount'] : 0; ?>;
    const bookingId = <?php echo $testBookingId; ?>;
    
    if (amount > 0) {
        console.log('Redirecting to payment page...');
        window.location.href = `booking_payment.php?id=${bookingId}&amount=${amount}`;
    } else {
        alert('No payment due');
    }
}
</script>

<style>
.btn {
    margin: 10px 0;
}
</style> 