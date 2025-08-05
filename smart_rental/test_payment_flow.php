<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "User not logged in";
    exit();
}

// Check if booking ID is provided
if (!isset($_GET['id'])) {
    echo "No booking ID provided";
    exit();
}

$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$bookingId) {
    echo "Invalid booking ID";
    exit();
}

echo "<h1>Payment Flow Test</h1>";
echo "<p>Booking ID: $bookingId</p>";
echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";

// Get booking details
$stmt = $conn->prepare("
    SELECT 
        b.*, 
        h.house_no,
        h.price as property_price,
        h.security_deposit,
        u.name as tenant_name
    FROM rental_bookings b
    JOIN houses h ON b.house_id = h.id
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
");

$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<p style='color: red;'>Booking not found</p>";
    exit();
}

echo "<h2>Booking Details</h2>";
echo "<pre>" . print_r($booking, true) . "</pre>";

// Check if user owns the booking
if ($booking['user_id'] != $_SESSION['user_id']) {
    echo "<p style='color: red;'>Unauthorized access to this booking</p>";
    exit();
}

// Check if booking is already paid
if ($booking['payment_status'] === 'paid' || $booking['payment_status'] === 'partial') {
    echo "<p style='color: orange;'>This booking has already been paid.</p>";
    exit();
}

// Check if booking is in a valid state for payment
if ($booking['status'] !== 'pending' && $booking['status'] !== 'confirmed') {
    echo "<p style='color: red;'>This booking is not in a valid state for payment. Current status: " . $booking['status'] . "</p>";
    exit();
}

echo "<p style='color: green;'>✅ All checks passed! Booking is ready for payment.</p>";

echo "<h2>Payment Options</h2>";
echo "<p><strong>Amount to Pay:</strong> KSh " . number_format($booking['property_price'] + $booking['security_deposit'], 2) . "</p>";
echo "<p><strong>Monthly Rent:</strong> KSh " . number_format($booking['property_price'], 2) . "</p>";
echo "<p><strong>Security Deposit:</strong> KSh " . number_format($booking['security_deposit'], 2) . "</p>";

echo "<h3>Test Links</h3>";
echo "<p><a href='booking_payment.php?id=$bookingId' class='btn btn-primary'>Go to Payment Page</a></p>";
echo "<p><a href='booking_details.php?id=$bookingId' class='btn btn-secondary'>Back to Booking Details</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.btn { display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; }
.btn-primary { background: #007bff; color: white; }
.btn-secondary { background: #6c757d; color: white; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style> 