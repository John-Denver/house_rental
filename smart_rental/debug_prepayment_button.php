<?php
/**
 * Debug Pre-Payment Button
 * Test the logic that determines when to show the pre-payment button
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

echo "<h2>Debug Pre-Payment Button</h2>";

// Get booking ID from URL parameter
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;

if (!$bookingId) {
    echo "<p>Please provide a booking_id parameter: ?booking_id=X</p>";
    exit;
}

echo "<h3>Booking ID: $bookingId</h3>";

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
echo "<li>Status: " . $booking['status'] . "</li>";
echo "<li>Payment Status: " . $booking['payment_status'] . "</li>";
echo "<li>Start Date: " . $booking['start_date'] . "</li>";
echo "</ul>";

// Check if first payment has been made
$firstPaymentCompleted = false;
if ($booking['payment_status'] === 'paid' || $booking['payment_status'] === 'completed') {
    $firstPaymentCompleted = true;
    echo "<p>✅ First payment completed (based on payment_status)</p>";
} else {
    // Check if initial payment exists in monthly_rent_payments
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM monthly_rent_payments 
        WHERE booking_id = ? AND is_first_payment = 1 AND status = 'paid'
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $firstPaymentCompleted = ($result['count'] > 0);
    
    if ($firstPaymentCompleted) {
        echo "<p>✅ First payment completed (based on monthly_rent_payments)</p>";
    } else {
        echo "<p>❌ First payment not completed</p>";
    }
}

// Check if pre-payment button should be shown
$shouldShowPrePayment = $firstPaymentCompleted && ($booking['status'] === 'confirmed' || $booking['status'] === 'paid');
echo "<h4>Pre-Payment Button Logic:</h4>";
echo "<ul>";
echo "<li>First Payment Completed: " . ($firstPaymentCompleted ? 'Yes' : 'No') . "</li>";
echo "<li>Status is confirmed/paid: " . (($booking['status'] === 'confirmed' || $booking['status'] === 'paid') ? 'Yes' : 'No') . "</li>";
echo "<li>Should Show Pre-Payment Button: " . ($shouldShowPrePayment ? 'Yes' : 'No') . "</li>";
echo "</ul>";

if ($shouldShowPrePayment) {
    // Test get_next_unpaid_month function
    echo "<h4>Testing get_next_unpaid_month function:</h4>";
    
    try {
        $stmt = $conn->prepare("
            SELECT get_next_unpaid_month(?) as next_month
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $nextUnpaidMonth = $result['next_month'];
        
        if ($nextUnpaidMonth) {
            $monthName = date('F Y', strtotime($nextUnpaidMonth));
            echo "<p>✅ Next unpaid month: $nextUnpaidMonth ($monthName)</p>";
            
            // Show the button that should be generated
            echo "<h4>Generated Button:</h4>";
            echo '<a href="booking_payment.php?id=' . $bookingId . '&type=prepayment" class="btn btn-primary w-100 mt-3" onclick="console.log(\'Pre-Pay button clicked - navigating to booking_payment.php?id=' . $bookingId . '&type=prepayment\');">';
            echo '<i class="fas fa-calendar-plus me-1"></i> Pre-Pay ' . $monthName;
            echo '</a>';
            
            echo "<h4>Test the button:</h4>";
            echo "<p>Click the button above to test if it navigates correctly.</p>";
            
        } else {
            echo "<p>❌ No unpaid months found</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Error calling get_next_unpaid_month: " . $e->getMessage() . "</p>";
    }
}

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

echo "<h4>Test Links:</h4>";
echo "<ul>";
echo "<li><a href='booking_details.php?id=$bookingId'>View Booking Details</a></li>";
echo "<li><a href='booking_payment.php?id=$bookingId&type=prepayment'>Test Pre-Payment Page</a></li>";
echo "<li><a href='my_bookings.php'>View My Bookings</a></li>";
echo "</ul>";
?> 