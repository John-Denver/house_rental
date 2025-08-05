<?php
/**
 * Check Payment Status
 * Simple page to check payment status in database
 */

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

echo "<h2>Payment Status Check</h2>";

// Get recent payment requests
$query = "SELECT * FROM mpesa_payment_requests ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Booking ID</th><th>Checkout Request ID</th><th>Status</th><th>Result Code</th><th>Result Desc</th><th>Created</th><th>Updated</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['booking_id'] . "</td>";
        echo "<td>" . $row['checkout_request_id'] . "</td>";
        echo "<td style='font-weight: bold; color: " . ($row['status'] === 'completed' ? 'green' : ($row['status'] === 'processing' ? 'orange' : 'red')) . "'>" . $row['status'] . "</td>";
        echo "<td>" . $row['result_code'] . "</td>";
        echo "<td>" . $row['result_desc'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>" . $row['updated_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment requests found.</p>";
}

// Check booking status
echo "<h3>Recent Bookings</h3>";
$query = "SELECT id, user_id, house_id, status, payment_status, created_at FROM rental_bookings ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>User ID</th><th>House ID</th><th>Status</th><th>Payment Status</th><th>Created</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['house_id'] . "</td>";
        echo "<td style='font-weight: bold; color: " . ($row['status'] === 'confirmed' ? 'green' : 'orange') . "'>" . $row['status'] . "</td>";
        echo "<td style='font-weight: bold; color: " . ($row['payment_status'] === 'paid' ? 'green' : 'red') . "'>" . $row['payment_status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No bookings found.</p>";
}

echo "<h3>Test Payment Status Check</h3>";
echo "<form method='POST'>";
echo "<input type='text' name='checkout_id' placeholder='Enter Checkout Request ID' style='width: 300px; padding: 5px;'>";
echo "<button type='submit' style='margin-left: 10px; padding: 5px 10px;'>Check Status</button>";
echo "</form>";

if ($_POST && isset($_POST['checkout_id'])) {
    $checkout_id = $_POST['checkout_id'];
    
    $stmt = $conn->prepare("SELECT * FROM mpesa_payment_requests WHERE checkout_request_id = ?");
    $stmt->bind_param('s', $checkout_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if ($payment) {
        echo "<h4>Payment Details for: $checkout_id</h4>";
        echo "<p><strong>Status:</strong> " . $payment['status'] . "</p>";
        echo "<p><strong>Result Code:</strong> " . $payment['result_code'] . "</p>";
        echo "<p><strong>Result Description:</strong> " . $payment['result_desc'] . "</p>";
        echo "<p><strong>Created:</strong> " . $payment['created_at'] . "</p>";
        echo "<p><strong>Updated:</strong> " . $payment['updated_at'] . "</p>";
    } else {
        echo "<p>Payment request not found for: $checkout_id</p>";
    }
}
?> 