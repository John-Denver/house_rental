<?php
/**
 * Test Bind Param Fix
 * Verify the bind_param issue in monthly payment tracker
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Test Bind Param Fix</h2>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 6;

echo "<h3>Testing with Booking ID: $testBookingId</h3>";

// Get booking details
$stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
$stmt->bind_param('i', $testBookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<p style='color: red;'>❌ Booking not found!</p>";
    exit;
}

echo "<h4>Booking Details:</h4>";
echo "<ul>";
echo "<li>ID: " . $booking['id'] . "</li>";
echo "<li>Start Date: " . $booking['start_date'] . "</li>";
echo "<li>End Date: " . $booking['end_date'] . "</li>";
echo "<li>Monthly Rent: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
echo "<li>Security Deposit: KSh " . number_format($booking['security_deposit'], 2) . "</li>";
echo "</ul>";

// Test the SQL statement
echo "<h4>Testing SQL Statement:</h4>";

$sql = "
    INSERT INTO monthly_rent_payments 
    (booking_id, month, amount, status, payment_type, is_first_payment, notes)
    VALUES (?, ?, ?, 'unpaid', ?, ?, ?)
";

echo "<p><strong>SQL:</strong> " . htmlspecialchars($sql) . "</p>";

// Count placeholders
$placeholderCount = substr_count($sql, '?');
echo "<p><strong>Placeholders (?):</strong> $placeholderCount</p>";

// Test bind_param with correct type string
echo "<h4>Testing Bind Param:</h4>";

try {
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        echo "<p style='color: green;'>✅ SQL prepared successfully</p>";
        
        // Test with dummy values
        $bookingId = $booking['id'];
        $month = '2025-08-01';
        $amount = $booking['monthly_rent'] + $booking['security_deposit'];
        $paymentType = 'initial_payment';
        $isFirstPayment = 1;
        $notes = 'First month rent + security deposit';
        
        // The type definition should be 'isdsisss' (6 characters for 6 placeholders)
        $typeDef = 'isdsisss'; // This is WRONG - has 8 characters for 6 placeholders
        echo "<p><strong>Current type definition:</strong> '$typeDef' (length: " . strlen($typeDef) . ")</p>";
        echo "<p><strong>Expected:</strong> 6 parameters</p>";
        
        // This will fail
        $result = $stmt->bind_param($typeDef, 
            $bookingId, 
            $month, 
            $amount,
            $paymentType,
            $isFirstPayment,
            $notes
        );
        
        if ($result) {
            echo "<p style='color: green;'>✅ bind_param executed successfully</p>";
        } else {
            echo "<p style='color: red;'>❌ bind_param failed: " . $stmt->error . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ SQL preparation failed: " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Show the fix
echo "<h4>The Fix:</h4>";
echo "<p>The type definition should be <strong>'isdsisss'</strong> (6 characters) instead of <strong>'isdsisss'</strong> (8 characters)</p>";

echo "<h4>Corrected Code:</h4>";
echo "<pre>";
echo "// SQL has 6 placeholders: ?, ?, ?, ?, ?, ?
// So type definition should be 6 characters: 'isdsisss'

\$stmt->bind_param('isdsisss', 
    \$booking['id'],      // i (integer)
    \$month,              // s (string)
    \$amount,             // d (decimal)
    \$paymentType,        // s (string)
    \$isFirstPayment,     // i (integer)
    \$notes               // s (string)
);
";
echo "</pre>";

echo "<h4>Manual Fix Required:</h4>";
echo "<p>Please manually edit <code>monthly_payment_tracker.php</code> line 119 and change:</p>";
echo "<p><strong>From:</strong> <code>'isdsisss'</code> (8 characters)</p>";
echo "<p><strong>To:</strong> <code>'isdsisss'</code> (6 characters)</p>";

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='debug_monthly_payments.php?booking_id=$testBookingId'>Debug Monthly Payments</a></li>";
echo "<li><a href='test_payment_allocation.php?booking_id=$testBookingId'>Test Payment Allocation</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "</ul>";
?>

<style>
pre {
    background-color: #f5f5f5;
    padding: 10px;
    border: 1px solid #ddd;
    overflow-x: auto;
}
</style> 