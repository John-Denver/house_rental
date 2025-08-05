<?php
session_start();
require_once '../config/db.php';
require_once 'mpesa_config.php';

echo "<h1>Simple Payment Flow Test</h1>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
    exit();
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test 1: Check if we can get access token
echo "<h2>1. Testing Access Token</h2>";
$access_token = getMpesaAccessToken();
if ($access_token) {
    echo "<p style='color: green;'>✅ Access token obtained successfully!</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to get access token</p>";
    exit();
}

// Test 2: Check if callback URL is accessible
echo "<h2>2. Testing Callback URL</h2>";
$callbackUrl = MPESA_CALLBACK_URL;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $callbackUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<p><strong>Callback URL:</strong> $callbackUrl</p>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>cURL Error:</strong> " . ($curlError ?: 'None') . "</p>";

if ($httpCode === 200) {
    echo "<p style='color: green;'>✅ Callback URL is accessible</p>";
} else {
    echo "<p style='color: red;'>❌ Callback URL is not accessible (HTTP $httpCode)</p>";
}

// Test 3: Check if we have any pending payments
echo "<h2>3. Checking Pending Payments</h2>";
$stmt = $conn->prepare("
    SELECT pr.*, rb.house_id, h.house_no
    FROM mpesa_payment_requests pr
    JOIN rental_bookings rb ON pr.booking_id = rb.id
    JOIN houses h ON rb.house_id = h.id
    WHERE pr.status = 'pending' AND rb.user_id = ?
    ORDER BY pr.created_at DESC
    LIMIT 5
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$pendingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($pendingPayments)) {
    echo "<p>No pending payments found.</p>";
} else {
    echo "<p><strong>Found " . count($pendingPayments) . " pending payment(s):</strong></p>";
    echo "<ul>";
    foreach ($pendingPayments as $payment) {
        echo "<li>Checkout ID: " . $payment['checkout_request_id'] . " - Amount: KSh " . number_format($payment['amount'], 2) . "</li>";
    }
    echo "</ul>";
}

// Test 4: Simulate STK Push (without actually sending)
echo "<h2>4. Simulating STK Push</h2>";
$phone = "254700000000"; // Test phone number
$amount = 1; // Test amount
$reference = "TEST" . time();

$timestamp = date('YmdHis');
$password = base64_encode(MPESA_BUSINESS_SHORTCODE . MPESA_PASSKEY . $timestamp);

$stk_push_data = [
    'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => MPESA_TRANSACTION_TYPE,
    'Amount' => $amount,
    'PartyA' => $phone,
    'PartyB' => MPESA_PARTYB,
    'PhoneNumber' => $phone,
    'CallBackURL' => MPESA_CALLBACK_URL,
    'AccountReference' => $reference,
    'TransactionDesc' => 'Test Payment'
];

echo "<p><strong>STK Push Data:</strong></p>";
echo "<pre>" . json_encode($stk_push_data, JSON_PRETTY_PRINT) . "</pre>";

echo "<h2>5. Quick Actions</h2>";
echo "<p><a href='manual_payment_status.php' class='btn btn-primary'>Manual Payment Status</a></p>";
echo "<p><a href='test_mpesa_status.php' class='btn btn-secondary'>Test Payment Status</a></p>";
echo "<p><a href='booking_payment.php?id=6' class='btn btn-info'>Test Payment Page</a></p>";

echo "<h2>6. Summary</h2>";
echo "<ul>";
echo "<li><strong>Access Token:</strong> " . ($access_token ? '✅ Working' : '❌ Failed') . "</li>";
echo "<li><strong>Callback URL:</strong> " . ($httpCode === 200 ? '✅ Accessible' : '❌ Not Accessible') . "</li>";
echo "<li><strong>Pending Payments:</strong> " . count($pendingPayments) . " found</li>";
echo "</ul>";

if ($access_token && $httpCode === 200) {
    echo "<p style='color: green; font-weight: bold;'>✅ Payment system is ready for testing!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Payment system needs configuration fixes.</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
.btn { display: inline-block; padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; }
.btn-primary { background: #007bff; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-info { background: #17a2b8; color: white; }
</style> 