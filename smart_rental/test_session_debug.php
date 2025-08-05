<?php
/**
 * Test Session Debug
 * Check if session is working correctly
 */

session_start();

echo "<h2>Session Debug</h2>";

echo "<h3>Session Information:</h3>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "</p>";
echo "<p>Session Data: " . json_encode($_SESSION) . "</p>";

echo "<h3>Test Payment Status Check:</h3>";
echo "<form method='POST'>";
echo "<input type='text' name='checkout_id' placeholder='Enter Checkout Request ID' style='width: 300px; padding: 5px;'>";
echo "<button type='submit' style='margin-left: 10px; padding: 5px 10px;'>Test Status</button>";
echo "</form>";

if ($_POST && isset($_POST['checkout_id'])) {
    $checkout_id = $_POST['checkout_id'];
    
    echo "<h4>Testing Payment Status for: $checkout_id</h4>";
    
    // Simulate the payment status check
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/rental_system_bse/smart_rental/mpesa_payment_status.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['checkout_request_id' => $checkout_id]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cookie: ' . session_name() . '=' . session_id()
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>HTTP Code: $httpCode</p>";
    echo "<p>Response:</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $result = json_decode($response, true);
    if ($result) {
        echo "<p>Parsed Response:</p>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    }
}

// Check database for recent payments
echo "<h3>Recent Payments in Database:</h3>";

$host = "localhost";
$dbname = "house_rental";
$username = "root";
$password = "";

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $query = "SELECT pr.*, rb.user_id as booking_user_id FROM mpesa_payment_requests pr 
              JOIN rental_bookings rb ON pr.booking_id = rb.id 
              ORDER BY pr.created_at DESC LIMIT 5";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>Checkout Request ID</th><th>Status</th><th>Result Code</th><th>Booking User ID</th><th>Session User ID</th><th>Match</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            $sessionUserId = $_SESSION['user_id'] ?? 'NOT SET';
            $bookingUserId = $row['booking_user_id'];
            $match = ($sessionUserId == $bookingUserId) ? '✅' : '❌';
            
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['checkout_request_id'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . ($row['result_code'] ?? 'NULL') . "</td>";
            echo "<td>" . $bookingUserId . "</td>";
            echo "<td>" . $sessionUserId . "</td>";
            echo "<td>" . $match . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No payments found.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?> 