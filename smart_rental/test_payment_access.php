<?php
session_start();

echo "<h1>Payment Page Access Test</h1>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
    exit();
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Check if booking ID is provided
if (!isset($_GET['id'])) {
    echo "<p style='color: red;'>❌ No booking ID provided</p>";
    echo "<p>Add ?id=6 to the URL to test with booking ID 6</p>";
    exit();
}

$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$bookingId) {
    echo "<p style='color: red;'>❌ Invalid booking ID</p>";
    exit();
}

echo "<p style='color: green;'>✅ Booking ID: $bookingId</p>";

// Test database connection
require_once '../config/db.php';

if ($conn->connect_error) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $conn->connect_error . "</p>";
    exit();
}

echo "<p style='color: green;'>✅ Database connection successful</p>";

// Test booking access
$stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<p style='color: red;'>❌ Booking not found</p>";
    exit();
}

echo "<p style='color: green;'>✅ Booking found</p>";
echo "<p><strong>Booking Status:</strong> " . $booking['status'] . "</p>";
echo "<p><strong>Payment Status:</strong> " . $booking['payment_status'] . "</p>";
echo "<p><strong>User ID:</strong> " . $booking['user_id'] . "</p>";

// Check if user owns the booking
if ($booking['user_id'] != $_SESSION['user_id']) {
    echo "<p style='color: red;'>❌ Unauthorized access to this booking</p>";
    exit();
}

echo "<p style='color: green;'>✅ User authorized for this booking</p>";

// Test payment page access
echo "<h2>Test Payment Page Access</h2>";
echo "<p><a href='booking_payment.php?id=$bookingId' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Payment Page</a></p>";

echo "<h2>Direct URL Test</h2>";
echo "<p>Try this URL directly: <code>booking_payment.php?id=$bookingId</code></p>";

echo "<h2>Back to Booking Details</h2>";
echo "<p><a href='booking_details.php?id=$bookingId'>Back to Booking Details</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
</style> 