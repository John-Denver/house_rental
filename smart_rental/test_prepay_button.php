<?php
/**
 * Test Pre-Pay Button Functionality
 * Debug script to test why the pre-pay button refreshes instead of navigating
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

echo "<h2>Test Pre-Pay Button Functionality</h2>";

// Get booking ID from URL parameter
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 29; // Default to 29

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

// Check if first payment is completed
$firstPaymentCompleted = false;
if ($booking['payment_status'] === 'paid' || $booking['payment_status'] === 'completed') {
    $firstPaymentCompleted = true;
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
}

echo "<h4>First Payment Status:</h4>";
echo "<p>First Payment Completed: " . ($firstPaymentCompleted ? 'Yes' : 'No') . "</p>";

// Test get_next_unpaid_month function
echo "<h4>Testing get_next_unpaid_month function:</h4>";
$nextUnpaidMonth = null;
$stmt = $conn->prepare("SELECT get_next_unpaid_month(?) as next_month");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$nextUnpaidMonth = $result['next_month'];

echo "<p>Next Unpaid Month: " . ($nextUnpaidMonth ? date('F Y', strtotime($nextUnpaidMonth)) : 'None') . "</p>";

// Test button conditions
echo "<h4>Button Conditions:</h4>";
$shouldShowButton = $firstPaymentCompleted && ($booking['status'] === 'confirmed' || $booking['status'] === 'paid');
echo "<p>Should Show Pre-Payment Button: " . ($shouldShowButton ? 'Yes' : 'No') . "</p>";

if ($shouldShowButton && $nextUnpaidMonth) {
    $monthName = date('F Y', strtotime($nextUnpaidMonth));
    echo "<p>Button Text: Pre-Pay $monthName</p>";
    echo "<p>Button URL: booking_payment.php?id=$bookingId&type=prepayment</p>";
    
    // Test the actual button
    echo "<h4>Test Button:</h4>";
    echo "<button type='button' class='btn btn-primary' onclick='testNavigation()'>";
    echo "<i class='fas fa-calendar-plus me-1'></i> Pre-Pay $monthName";
    echo "</button>";
    
    echo "<div id='test-result' style='margin-top: 10px; padding: 10px; border: 1px solid #ccc; display: none;'></div>";
}

// Show current payment status
echo "<h4>Current Payment Status:</h4>";
$stmt = $conn->prepare("
    SELECT month, status, payment_date, amount, is_first_payment, payment_type
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
    echo "<th>Month</th><th>Status</th><th>Payment Date</th><th>Amount</th><th>First Payment</th><th>Type</th>";
    echo "</tr>";
    foreach ($payments as $payment) {
        $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . $payment['payment_type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment records found.</p>";
}

echo "<h4>Test Links:</h4>";
echo "<ul>";
echo "<li><a href='booking_details.php?id=$bookingId'>View Booking Details</a></li>";
echo "<li><a href='booking_payment.php?id=$bookingId&type=prepayment'>Direct Link to Pre-Payment</a></li>";
echo "<li><a href='my_bookings.php'>View My Bookings</a></li>";
echo "</ul>";
?>

<script>
function testNavigation() {
    const resultDiv = document.getElementById('test-result');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<strong>Testing navigation...</strong>';
    
    console.log('Testing navigation to booking_payment.php?id=<?php echo $bookingId; ?>&type=prepayment');
    
    // Test if the URL is accessible
    fetch('booking_payment.php?id=<?php echo $bookingId; ?>&type=prepayment', {
        method: 'GET',
        headers: {
            'Accept': 'text/html'
        }
    })
    .then(response => {
        if (response.ok) {
            resultDiv.innerHTML = '<strong style="color: green;">✅ URL is accessible!</strong><br>Response status: ' + response.status;
            console.log('URL is accessible, status:', response.status);
        } else {
            resultDiv.innerHTML = '<strong style="color: red;">❌ URL returned error!</strong><br>Response status: ' + response.status;
            console.log('URL returned error, status:', response.status);
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<strong style="color: red;">❌ Navigation failed!</strong><br>Error: ' + error.message;
        console.error('Navigation error:', error);
    });
}

// Test the onclick function directly
console.log('Pre-pay button test loaded');
console.log('Expected URL: booking_payment.php?id=<?php echo $bookingId; ?>&type=prepayment');
</script> 