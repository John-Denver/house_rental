<?php
/**
 * Test Pre-Payment Logic
 * Verify that pre-payments are recorded in the correct future months
 */

session_start();

// Database connection
$host = "localhost";
$dbname = "house_rental";
$username = "root";
$password = "";

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    echo "<h2>Database Connection: ❌ Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Test Pre-Payment Logic</h2>";

// Get booking ID from URL parameter
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;

if (!$bookingId) {
    echo "<p>Please provide a booking_id parameter: ?booking_id=X</p>";
    exit;
}

echo "<h3>Booking ID: $bookingId</h3>";

// Include payment tracking helper
require_once 'includes/payment_tracking_helper.php';

// Check booking details
$stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<p>❌ Booking not found</p>";
    exit;
}

echo "<h4>Booking Details:</h4>";
echo "<ul>";
echo "<li>Start Date: " . $booking['start_date'] . "</li>";
echo "<li>End Date: " . $booking['end_date'] . "</li>";
echo "<li>Status: " . $booking['status'] . "</li>";
echo "<li>Payment Status: " . $booking['payment_status'] . "</li>";
echo "</ul>";

// Check if first payment has been made
$hasFirstPayment = hasFirstPaymentBeenMade($conn, $bookingId);
echo "<h4>First Payment Status:</h4>";
echo "<p>" . ($hasFirstPayment ? "✅ First payment completed" : "❌ First payment not completed") . "</p>";

if (!$hasFirstPayment) {
    echo "<div class='alert alert-warning'>";
    echo "<strong>Note:</strong> First payment must be completed before testing pre-payments.";
    echo "</div>";
    exit;
}

// Get next unpaid month
$nextUnpaidMonth = getNextUnpaidMonth($conn, $bookingId);
echo "<h4>Next Unpaid Month:</h4>";
echo "<p>" . ($nextUnpaidMonth ? $nextUnpaidMonth . " (" . date('F Y', strtotime($nextUnpaidMonth)) . ")" : "No unpaid months found") . "</p>";

// Show current payment status
echo "<h4>Current Payment Status:</h4>";
$stmt = $conn->prepare("
    SELECT month, status, payment_date, amount 
    FROM monthly_rent_payments 
    WHERE booking_id = ? 
    ORDER BY month ASC
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($payments) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Month</th><th>Status</th><th>Payment Date</th><th>Amount</th>";
    echo "</tr>";
    foreach ($payments as $payment) {
        $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment records found.</p>";
}

// Test pre-payment simulation
echo "<h4>Test Pre-Payment:</h4>";
echo "<form method='POST'>";
echo "<button type='submit' name='test_prepayment' class='btn btn-primary'>Simulate Pre-Payment</button>";
echo "</form>";

if (isset($_POST['test_prepayment'])) {
    try {
        // Simulate a pre-payment
        $result = recordPrePayment(
            $conn,
            $bookingId,
            50000, // Amount
            'Test Payment',
            'TEST_' . time(),
            null,
            'Test pre-payment simulation'
        );
        
        if ($result['success']) {
            echo "<div class='alert alert-success'>";
            echo "<strong>✅ Pre-payment recorded successfully!</strong><br>";
            echo "Month paid: " . $result['month_paid'] . " (" . date('F Y', strtotime($result['month_paid'])) . ")<br>";
            echo "Message: " . $result['message'];
            echo "</div>";
            
            // Refresh the page to show updated status
            echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
        } else {
            echo "<div class='alert alert-danger'>";
            echo "<strong>❌ Pre-payment failed:</strong> " . $result['message'];
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>❌ Error:</strong> " . $e->getMessage();
        echo "</div>";
    }
}

echo "<h4>Payment Flow Summary:</h4>";
echo "<ol>";
echo "<li><strong>Initial Payment:</strong> Security deposit + first month rent (move-in month)</li>";
echo "<li><strong>Pre-Payment 1:</strong> Applied to next unpaid month (e.g., September if move-in is August)</li>";
echo "<li><strong>Pre-Payment 2:</strong> Applied to next unpaid month (e.g., October)</li>";
echo "<li><strong>Pre-Payment 3:</strong> Applied to next unpaid month (e.g., November)</li>";
echo "</ol>";

echo "<h4>Test Links:</h4>";
echo "<ul>";
echo "<li><a href='booking_details.php?id=$bookingId'>View Booking Details</a></li>";
echo "<li><a href='booking_payment.php?id=$bookingId&type=prepayment'>Test Pre-Payment Page</a></li>";
echo "<li><a href='my_bookings.php'>View My Bookings</a></li>";
echo "</ul>";
?> 