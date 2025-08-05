<?php
session_start();
require_once 'mpesa_config.php';
require_once '../config/db.php';

echo "<h1>M-Pesa Payment Status Test</h1>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
    exit();
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Get pending payment requests for this user
$stmt = $conn->prepare("
    SELECT pr.*, rb.house_id, h.house_no
    FROM mpesa_payment_requests pr
    JOIN rental_bookings rb ON pr.booking_id = rb.id
    JOIN houses h ON rb.house_id = h.id
    WHERE pr.status = 'pending' AND rb.user_id = ?
    ORDER BY pr.created_at DESC
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$pendingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<h2>Your Pending Payments</h2>";
if (empty($pendingPayments)) {
    echo "<p>No pending payments found.</p>";
} else {
    echo "<div class='table-responsive'>";
    echo "<table class='table'>";
    echo "<thead><tr><th>Checkout ID</th><th>Property</th><th>Amount</th><th>Phone</th><th>Created</th><th>Action</th></tr></thead>";
    echo "<tbody>";
    foreach ($pendingPayments as $payment) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($payment['checkout_request_id']) . "</td>";
        echo "<td>" . htmlspecialchars($payment['house_no']) . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . htmlspecialchars($payment['phone_number']) . "</td>";
        echo "<td>" . date('M d, Y H:i', strtotime($payment['created_at'])) . "</td>";
        echo "<td>";
        echo "<button onclick='testStatus(\"" . $payment['checkout_request_id'] . "\")' class='btn btn-primary btn-sm'>Test Status</button>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";
}

// Test specific checkout request ID
if (isset($_GET['checkout_id'])) {
    $checkout_id = $_GET['checkout_id'];
    echo "<h2>Testing Status for: $checkout_id</h2>";
    
    // Get access token
    $access_token = getMpesaAccessToken();
    if (!$access_token) {
        echo "<p style='color: red;'>❌ Failed to get access token</p>";
    } else {
        echo "<p style='color: green;'>✅ Access token obtained</p>";
        
        // Generate password
        $password_data = generateMpesaPassword();
        
        // Prepare STK Query request
        $stk_query_data = [
            'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
            'Password' => $password_data['password'],
            'Timestamp' => $password_data['timestamp'],
            'CheckoutRequestID' => $checkout_id
        ];
        
        echo "<h3>Request Data:</h3>";
        echo "<pre>" . json_encode($stk_query_data, JSON_PRETTY_PRINT) . "</pre>";
        
        // Make STK Query request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, MPESA_STK_QUERY_URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_query_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        echo "<h3>Response:</h3>";
        echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
        echo "<p><strong>cURL Error:</strong> " . ($curlError ?: 'None') . "</p>";
        echo "<p><strong>Raw Response:</strong></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            echo "<p><strong>Parsed Response:</strong></p>";
            echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
            
            if (isset($result['ResultCode'])) {
                echo "<p style='color: green;'>✅ ResultCode found: " . $result['ResultCode'] . "</p>";
                echo "<p><strong>ResultDesc:</strong> " . ($result['ResultDesc'] ?? 'Not provided') . "</p>";
                
                if ($result['ResultCode'] === 0) {
                    echo "<p style='color: green;'>✅ Payment Successful!</p>";
                } else {
                    echo "<p style='color: red;'>❌ Payment Failed (Code: " . $result['ResultCode'] . ")</p>";
                }
            } else {
                echo "<p style='color: orange;'>⚠️ No ResultCode in response - payment may still be pending</p>";
                
                // Check for error information
                if (isset($result['errorCode'])) {
                    echo "<p style='color: red;'>❌ API Error Code: " . $result['errorCode'] . "</p>";
                }
                if (isset($result['errorMessage'])) {
                    echo "<p style='color: red;'>❌ API Error Message: " . $result['errorMessage'] . "</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ HTTP request failed</p>";
        }
    }
}

echo "<h2>Manual Test</h2>";
echo "<p>Enter a checkout request ID to test:</p>";
echo "<form method='GET'>";
echo "<input type='text' name='checkout_id' placeholder='Checkout Request ID' style='width: 300px; padding: 5px;'>";
echo "<button type='submit' style='margin-left: 10px; padding: 5px 15px;'>Test Status</button>";
echo "</form>";

echo "<h2>Quick Actions</h2>";
echo "<p><a href='manual_payment_status.php' class='btn btn-primary'>Manual Payment Status</a></p>";
echo "<p><a href='test_mpesa_connection.php' class='btn btn-secondary'>M-Pesa Connection Test</a></p>";
?>

<script>
function testStatus(checkoutId) {
    window.location.href = 'test_mpesa_status.php?checkout_id=' + checkoutId;
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
.btn { display: inline-block; padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; }
.btn-primary { background: #007bff; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.table { width: 100%; border-collapse: collapse; margin: 20px 0; }
.table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
.table th { background-color: #f2f2f2; }
</style> 