<?php
/**
 * Debug Payment Data
 * Check payment data across all tables
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

echo "<h2>Debug Payment Data</h2>";

// Get booking ID from URL parameter
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;

if (!$bookingId) {
    echo "<p>Please provide a booking_id parameter: ?booking_id=X</p>";
    exit;
}

echo "<h3>Booking ID: $bookingId</h3>";

// Check rental_bookings table
echo "<h4>1. Rental Bookings Table:</h4>";
$stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if ($booking) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Field</th><th>Value</th>";
    echo "</tr>";
    foreach ($booking as $field => $value) {
        echo "<tr>";
        echo "<td>$field</td>";
        echo "<td>" . htmlspecialchars($value) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Booking not found</p>";
}

// Check booking_payments table
echo "<h4>2. Booking Payments Table:</h4>";
$stmt = $conn->prepare("SELECT * FROM booking_payments WHERE booking_id = ?");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$bookingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($bookingPayments) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Amount</th><th>Payment Method</th><th>Status</th><th>Payment Date</th><th>Transaction ID</th>";
    echo "</tr>";
    foreach ($bookingPayments as $payment) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['payment_method'] . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . $payment['payment_date'] . "</td>";
        echo "<td>" . $payment['transaction_id'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No booking payments found</p>";
}

// Check monthly_rent_payments table
echo "<h4>3. Monthly Rent Payments Table:</h4>";
$stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ?");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$monthlyPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($monthlyPayments) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Is First Payment</th><th>Payment Type</th>";
    echo "</tr>";
    foreach ($monthlyPayments as $payment) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>" . $payment['month'] . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . $payment['payment_date'] . "</td>";
        echo "<td>" . ($payment['is_first_payment'] ?? 'NULL') . "</td>";
        echo "<td>" . ($payment['payment_type'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No monthly rent payments found</p>";
}

// Check mpesa_payment_requests table
echo "<h4>4. M-Pesa Payment Requests Table:</h4>";
$stmt = $conn->prepare("SELECT * FROM mpesa_payment_requests WHERE booking_id = ?");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$mpesaPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($mpesaPayments) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Checkout Request ID</th><th>Amount</th><th>Status</th><th>Result Code</th><th>Created</th>";
    echo "</tr>";
    foreach ($mpesaPayments as $payment) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>" . $payment['checkout_request_id'] . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['result_code'] ?? 'NULL') . "</td>";
        echo "<td>" . $payment['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No M-Pesa payment requests found</p>";
}

// Check payment_tracking table
echo "<h4>5. Payment Tracking Table:</h4>";
$stmt = $conn->prepare("SELECT * FROM payment_tracking WHERE booking_id = ?");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$paymentTracking = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($paymentTracking) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Payment Type</th><th>Amount</th><th>Status</th><th>Month</th><th>Is First Payment</th>";
    echo "</tr>";
    foreach ($paymentTracking as $payment) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>" . $payment['payment_type'] . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . $payment['month'] . "</td>";
        echo "<td>" . $payment['is_first_payment'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No payment tracking records found</p>";
}

echo "<h4>6. Summary:</h4>";
echo "<ul>";
echo "<li>Booking Payments: " . count($bookingPayments) . " records</li>";
echo "<li>Monthly Rent Payments: " . count($monthlyPayments) . " records</li>";
echo "<li>M-Pesa Payment Requests: " . count($mpesaPayments) . " records</li>";
echo "<li>Payment Tracking: " . count($paymentTracking) . " records</li>";
echo "</ul>";

// Test the get_monthly_payments.php endpoint
echo "<h4>7. Test get_monthly_payments.php:</h4>";
echo "<button onclick='testMonthlyPayments()'>Test Monthly Payments API</button>";
echo "<div id='apiResult'></div>";

?>

<script>
async function testMonthlyPayments() {
    const resultDiv = document.getElementById('apiResult');
    resultDiv.innerHTML = '<p>Testing...</p>';
    
    try {
        const response = await fetch('get_monthly_payments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'booking_id=<?php echo $bookingId; ?>'
        });
        
        const result = await response.json();
        resultDiv.innerHTML = '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
    } catch (error) {
        resultDiv.innerHTML = '<p>Error: ' + error.message + '</p>';
    }
}
</script> 