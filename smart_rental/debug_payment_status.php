<?php
/**
 * Debug Payment Status
 * Check the status of payment requests and see what's happening
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
    
    echo "<h2>Payment Status Debug</h2>";
    
    // Get recent payment requests
    $stmt = $conn->prepare("
        SELECT 
            pr.*,
            rb.house_id,
            rb.user_id,
            rb.status as booking_status
        FROM mpesa_payment_requests pr
        JOIN rental_bookings rb ON pr.booking_id = rb.id
        ORDER BY pr.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $payment_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h3>Recent Payment Requests:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Checkout Request ID</th>";
    echo "<th>Booking ID</th>";
    echo "<th>Amount</th>";
    echo "<th>Status</th>";
    echo "<th>Result Code</th>";
    echo "<th>Result Desc</th>";
    echo "<th>Receipt Number</th>";
    echo "<th>Created</th>";
    echo "<th>Updated</th>";
    echo "</tr>";
    
    foreach ($payment_requests as $request) {
        echo "<tr>";
        echo "<td>" . $request['id'] . "</td>";
        echo "<td>" . $request['checkout_request_id'] . "</td>";
        echo "<td>" . $request['booking_id'] . "</td>";
        echo "<td>KSh " . number_format($request['amount'], 2) . "</td>";
        echo "<td style='color: " . ($request['status'] === 'completed' ? 'green' : ($request['status'] === 'failed' ? 'red' : 'orange')) . ";'>" . $request['status'] . "</td>";
        echo "<td>" . ($request['result_code'] ?? 'N/A') . "</td>";
        echo "<td>" . ($request['result_desc'] ?? 'N/A') . "</td>";
        echo "<td>" . ($request['mpesa_receipt_number'] ?? 'N/A') . "</td>";
        echo "<td>" . $request['created_at'] . "</td>";
        echo "<td>" . $request['updated_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check monthly payments
    echo "<h3>Monthly Rent Payments:</h3>";
    $stmt = $conn->prepare("
        SELECT 
            mrp.*,
            rb.house_id,
            rb.user_id
        FROM monthly_rent_payments mrp
        JOIN rental_bookings rb ON mrp.booking_id = rb.id
        ORDER BY mrp.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $monthly_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Booking ID</th>";
    echo "<th>Month</th>";
    echo "<th>Amount</th>";
    echo "<th>Status</th>";
    echo "<th>Payment Type</th>";
    echo "<th>Is First Payment</th>";
    echo "<th>Payment Date</th>";
    echo "<th>Receipt Number</th>";
    echo "</tr>";
    
    foreach ($monthly_payments as $payment) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>" . $payment['booking_id'] . "</td>";
        echo "<td>" . $payment['month'] . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td style='color: " . ($payment['status'] === 'paid' ? 'green' : ($payment['status'] === 'unpaid' ? 'orange' : 'red')) . ";'>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['payment_type'] ?? 'N/A') . "</td>";
        echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . ($payment['payment_date'] ?? 'N/A') . "</td>";
        echo "<td>" . ($payment['mpesa_receipt_number'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check payment tracking
    echo "<h3>Payment Tracking:</h3>";
    $stmt = $conn->prepare("
        SELECT 
            pt.*,
            rb.house_id,
            rb.user_id
        FROM payment_tracking pt
        JOIN rental_bookings rb ON pt.booking_id = rb.id
        ORDER BY pt.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $payment_tracking = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($payment_tracking)) {
        echo "<p>No payment tracking records found.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Booking ID</th>";
        echo "<th>Payment Type</th>";
        echo "<th>Amount</th>";
        echo "<th>Status</th>";
        echo "<th>Is First Payment</th>";
        echo "<th>Payment Date</th>";
        echo "<th>Receipt Number</th>";
        echo "</tr>";
        
        foreach ($payment_tracking as $tracking) {
            echo "<tr>";
            echo "<td>" . $tracking['id'] . "</td>";
            echo "<td>" . $tracking['booking_id'] . "</td>";
            echo "<td>" . $tracking['payment_type'] . "</td>";
            echo "<td>KSh " . number_format($tracking['amount'], 2) . "</td>";
            echo "<td style='color: " . ($tracking['status'] === 'completed' ? 'green' : ($tracking['status'] === 'pending' ? 'orange' : 'red')) . ";'>" . $tracking['status'] . "</td>";
            echo "<td>" . ($tracking['is_first_payment'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($tracking['payment_date'] ?? 'N/A') . "</td>";
            echo "<td>" . ($tracking['mpesa_receipt_number'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>Debug Information:</h3>";
    echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
    echo "<p><strong>Server Timezone:</strong> " . date_default_timezone_get() . "</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 