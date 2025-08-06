<?php
/**
 * Check Payment Result
 * Check if the recent payment was allocated to monthly_rent_payments table
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Check Payment Result</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 38;

echo "<h3>Checking Payment Result for Booking ID: $testBookingId</h3>";

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

// Check recent booking_payments
echo "<h4>Recent Booking Payments:</h4>";
$stmt = $conn->prepare("SELECT * FROM booking_payments WHERE booking_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('i', $testBookingId);
$stmt->execute();
$bookingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($bookingPayments) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Transaction ID</th>";
    echo "</tr>";
    foreach ($bookingPayments as $payment) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['payment_method'] . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . date('M d, Y H:i:s', strtotime($payment['created_at'])) . "</td>";
        echo "<td>" . ($payment['transaction_id'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No records in booking_payments table</p>";
}

// Check monthly_rent_payments table
echo "<h4>Monthly Rent Payments:</h4>";
$stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? ORDER BY month ASC");
$stmt->bind_param('i', $testBookingId);
$stmt->execute();
$monthlyPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($monthlyPayments) {
    echo "<p>Found " . count($monthlyPayments) . " monthly payment records</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Method</th><th>Transaction ID</th>";
    echo "</tr>";
    foreach ($monthlyPayments as $payment) {
        $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['payment_date'] ? date('M d, Y H:i:s', strtotime($payment['payment_date'])) : '-') . "</td>";
        echo "<td>" . ($payment['payment_method'] ?: '-') . "</td>";
        echo "<td>" . ($payment['transaction_id'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if any payments were made recently
    $recentPayments = array_filter($monthlyPayments, function($payment) {
        return $payment['status'] === 'paid' && 
               $payment['payment_date'] && 
               strtotime($payment['payment_date']) > strtotime('-1 hour');
    });
    
    if ($recentPayments) {
        echo "<p style='color: green;'>✅ Recent payments found in monthly_rent_payments table!</p>";
        foreach ($recentPayments as $payment) {
            echo "<p>✅ " . date('F Y', strtotime($payment['month'])) . " - Paid on " . date('M d, Y H:i:s', strtotime($payment['payment_date'])) . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ No recent payments found in monthly_rent_payments table</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ No monthly payment records found!</p>";
}

// Check mpesa_payment_requests table
echo "<h4>M-Pesa Payment Requests:</h4>";
$stmt = $conn->prepare("SELECT * FROM mpesa_payment_requests WHERE booking_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('i', $testBookingId);
$stmt->execute();
$mpesaPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($mpesaPayments) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Amount</th><th>Status</th><th>Date</th><th>Checkout Request ID</th>";
    echo "</tr>";
    foreach ($mpesaPayments as $payment) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . date('M d, Y H:i:s', strtotime($payment['created_at'])) . "</td>";
        echo "<td>" . ($payment['checkout_request_id'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No records in mpesa_payment_requests table</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='check_payment_logs.php?booking_id=$testBookingId'>Check Payment Logs</a></li>";
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