<?php
/**
 * Check Payment Processing Logs
 * See what's happening during payment processing
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Check Payment Processing Logs</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 36;

echo "<h3>Testing with Booking ID: $testBookingId</h3>";

// Get booking details
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

// Check current monthly payments
echo "<h4>Current Monthly Payments:</h4>";

try {
    require_once __DIR__ . '/monthly_payment_tracker.php';
    $tracker = new MonthlyPaymentTracker($conn);
    
    $payments = $tracker->getMonthlyPayments($testBookingId);
    
    if ($payments) {
        echo "<p>Found " . count($payments) . " monthly payment records</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Method</th>";
        echo "</tr>";
        foreach ($payments as $payment) {
            $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
            echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
            echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
            echo "<td>" . ($payment['payment_method'] ?: '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ No monthly payment records found!</p>";
    }
    
    // Get next payment due
    $nextPaymentDue = $tracker->getNextPaymentDue($testBookingId);
    
    if ($nextPaymentDue) {
        echo "<h4>Next Payment Due:</h4>";
        echo "<p>" . date('F Y', strtotime($nextPaymentDue['month'])) . " - KSh " . number_format($nextPaymentDue['amount'], 2) . "</p>";
    } else {
        echo "<h4>Next Payment Due:</h4>";
        echo "<p style='color: green;'>✅ All payments completed!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Check recent payment records in other tables
echo "<h4>Recent Payment Records:</h4>";

// Check booking_payments table
$stmt = $conn->prepare("SELECT * FROM booking_payments WHERE booking_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('i', $testBookingId);
$stmt->execute();
$bookingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($bookingPayments) {
    echo "<h5>Booking Payments:</h5>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th>";
    echo "</tr>";
    foreach ($bookingPayments as $payment) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['payment_method'] . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . date('M d, Y', strtotime($payment['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No records in booking_payments table</p>";
}

// Check mpesa_payment_requests table
$stmt = $conn->prepare("SELECT * FROM mpesa_payment_requests WHERE booking_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('i', $testBookingId);
$stmt->execute();
$mpesaPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($mpesaPayments) {
    echo "<h5>M-Pesa Payment Requests:</h5>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Amount</th><th>Status</th><th>Date</th>";
    echo "</tr>";
    foreach ($mpesaPayments as $payment) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . date('M d, Y', strtotime($payment['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No records in mpesa_payment_requests table</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='booking_details.php?id=$testBookingId'>Go to Booking Details</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
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