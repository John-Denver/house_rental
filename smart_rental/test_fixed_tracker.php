<?php
/**
 * Test Fixed Monthly Payment Tracker
 * Verify that the bind_param fix works correctly
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Test Fixed Monthly Payment Tracker</h2>";

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

// Check current state of monthly_rent_payments
echo "<h4>Current State:</h4>";
$result = $conn->query("SELECT COUNT(*) as count FROM monthly_rent_payments WHERE booking_id = $testBookingId");
$currentRecords = $result->fetch_assoc()['count'];
echo "<p>Current records for booking $testBookingId: $currentRecords</p>";

// Test the fixed tracker
echo "<h4>Testing Fixed Tracker:</h4>";

try {
    require_once __DIR__ . '/monthly_payment_tracker.php';
    $tracker = new MonthlyPaymentTracker($conn);
    
    echo "<p style='color: blue;'>🔄 Calling getMonthlyPayments()...</p>";
    
    // This should create the monthly records
    $payments = $tracker->getMonthlyPayments($testBookingId);
    
    echo "<p style='color: green;'>✅ getMonthlyPayments() completed successfully!</p>";
    echo "<p>Payments returned: " . count($payments) . "</p>";
    
    // Check if records were created
    $result = $conn->query("SELECT COUNT(*) as count FROM monthly_rent_payments WHERE booking_id = $testBookingId");
    $newRecords = $result->fetch_assoc()['count'];
    echo "<p>Records after getMonthlyPayments(): $newRecords</p>";
    
    if ($newRecords > $currentRecords) {
        echo "<p style='color: green;'>✅ Monthly records created successfully!</p>";
        
        // Show the created records
        echo "<h4>Created Monthly Records:</h4>";
        $stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? ORDER BY month ASC");
        $stmt->bind_param('i', $testBookingId);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Month</th><th>Amount</th><th>Status</th><th>Type</th><th>First Payment</th>";
        echo "</tr>";
        foreach ($records as $record) {
            echo "<tr>";
            echo "<td>" . date('F Y', strtotime($record['month'])) . "</td>";
            echo "<td>KSh " . number_format($record['amount'], 2) . "</td>";
            echo "<td>" . $record['status'] . "</td>";
            echo "<td>" . $record['payment_type'] . "</td>";
            echo "<td>" . ($record['is_first_payment'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Test getNextPaymentDue
        echo "<h4>Testing getNextPaymentDue():</h4>";
        $nextPaymentDue = $tracker->getNextPaymentDue($testBookingId);
        if ($nextPaymentDue) {
            echo "<p style='color: green;'>✅ Next payment due: " . date('F Y', strtotime($nextPaymentDue['month'])) . " - KSh " . number_format($nextPaymentDue['amount'], 2) . "</p>";
        } else {
            echo "<p style='color: red;'>❌ No next payment due found</p>";
        }
        
        // Test getPaymentSummary
        echo "<h4>Testing getPaymentSummary():</h4>";
        $summary = $tracker->getPaymentSummary($testBookingId);
        echo "<ul>";
        echo "<li>Total Months: " . $summary['total_months'] . "</li>";
        echo "<li>Paid Months: " . $summary['paid_months'] . "</li>";
        echo "<li>Unpaid Months: " . $summary['unpaid_months'] . "</li>";
        echo "<li>Total Paid: KSh " . number_format($summary['total_paid'], 2) . "</li>";
        echo "<li>Total Unpaid: KSh " . number_format($summary['total_unpaid'], 2) . "</li>";
        echo "</ul>";
        
    } else {
        echo "<p style='color: red;'>❌ No new records were created</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='debug_monthly_payments.php?booking_id=$testBookingId'>Debug Monthly Payments</a></li>";
echo "<li><a href='test_payment_allocation.php?booking_id=$testBookingId'>Test Payment Allocation</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "<li><a href='booking_details.php?id=$testBookingId'>View Booking Details</a></li>";
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