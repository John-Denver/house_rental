<?php
/**
 * Test Monthly Payments API
 * This script tests the get_monthly_payments.php endpoint
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Test Monthly Payments API</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    echo "<p>Session data: " . json_encode($_SESSION) . "</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Get user's bookings
$stmt = $conn->prepare("
    SELECT id, house_id, start_date, end_date, status, payment_status
    FROM rental_bookings 
    WHERE user_id = ?
    ORDER BY id DESC
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<h3>User's Bookings:</h3>";
if (empty($bookings)) {
    echo "<p>No bookings found for this user.</p>";
    exit;
}

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Booking ID</th><th>House ID</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Payment Status</th><th>Action</th>";
echo "</tr>";

foreach ($bookings as $booking) {
    echo "<tr>";
    echo "<td>" . $booking['id'] . "</td>";
    echo "<td>" . $booking['house_id'] . "</td>";
    echo "<td>" . $booking['start_date'] . "</td>";
    echo "<td>" . $booking['end_date'] . "</td>";
    echo "<td>" . $booking['status'] . "</td>";
    echo "<td>" . $booking['payment_status'] . "</td>";
    echo "<td><button onclick='testMonthlyPayments(" . $booking['id'] . ")'>Test API</button></td>";
    echo "</tr>";
}
echo "</table>";

// Check monthly_rent_payments table
echo "<h3>Monthly Rent Payments Table Status:</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM monthly_rent_payments");
$totalRecords = $result->fetch_assoc()['count'];
echo "<p>Total records in monthly_rent_payments: $totalRecords</p>";

if ($totalRecords > 0) {
    echo "<h4>Sample Records:</h4>";
    $sampleRecords = $conn->query("SELECT * FROM monthly_rent_payments ORDER BY id DESC LIMIT 5");
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Booking ID</th><th>Month</th><th>Amount</th><th>Status</th><th>Type</th>";
    echo "</tr>";
    while ($row = $sampleRecords->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['booking_id'] . "</td>";
        echo "<td>" . date('F Y', strtotime($row['month'])) . "</td>";
        echo "<td>KSh " . number_format($row['amount'], 2) . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['payment_type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test the API directly
echo "<h3>Direct API Test:</h3>";
if (!empty($bookings)) {
    $testBookingId = $bookings[0]['id'];
    echo "<p>Testing with booking ID: $testBookingId</p>";
    
    // Simulate the AJAX call
    $_POST['booking_id'] = $testBookingId;
    
    // Capture output
    ob_start();
    include 'get_monthly_payments.php';
    $apiResponse = ob_get_clean();
    
    echo "<h4>API Response:</h4>";
    echo "<pre>" . htmlspecialchars($apiResponse) . "</pre>";
    
    // Try to decode JSON
    $decoded = json_decode($apiResponse, true);
    if ($decoded) {
        echo "<h4>Decoded Response:</h4>";
        echo "<pre>" . print_r($decoded, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ Failed to decode JSON response</p>";
    }
}
?>

<script>
function testMonthlyPayments(bookingId) {
    console.log('Testing monthly payments for booking:', bookingId);
    
    // Simulate the AJAX call that my_bookings.php makes
    fetch('get_monthly_payments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'booking_id=' + bookingId
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(data => {
        console.log('Response data:', data);
        try {
            const jsonData = JSON.parse(data);
            alert('API Response: ' + JSON.stringify(jsonData, null, 2));
        } catch (e) {
            alert('Error parsing JSON: ' + data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}
</script>

<style>
table {
    margin: 10px 0;
}
th, td {
    padding: 8px;
    text-align: left;
}
pre {
    background-color: #f5f5f5;
    padding: 10px;
    border: 1px solid #ddd;
    overflow-x: auto;
}
</style> 