<?php
/**
 * Debug Payment Process
 * See what's happening when a payment is processed
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Debug Payment Process</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 32;

echo "<h3>Testing with Booking ID: $testBookingId</h3>";

// Get booking details first
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

// Test the monthly payment tracker
echo "<h4>Testing Monthly Payment Tracker:</h4>";

try {
    require_once __DIR__ . '/monthly_payment_tracker.php';
    $tracker = new MonthlyPaymentTracker($conn);
    
    // Get next payment due
    $nextPaymentDue = $tracker->getNextPaymentDue($testBookingId);
    
    if ($nextPaymentDue) {
        echo "<p style='color: green;'>✅ Next payment due: " . date('F Y', strtotime($nextPaymentDue['month'])) . " - KSh " . number_format($nextPaymentDue['amount'], 2) . "</p>";
        
        // Simulate the payment process
        echo "<h5>Simulating Payment Process:</h5>";
        
        $paymentAmount = $nextPaymentDue['amount'];
        $paymentDate = date('Y-m-d H:i:s');
        $paymentMethod = 'Test Payment';
        $transactionId = 'TEST_' . time();
        
        echo "<p>Payment Details:</p>";
        echo "<ul>";
        echo "<li>Amount: KSh " . number_format($paymentAmount, 2) . "</li>";
        echo "<li>Date: " . $paymentDate . "</li>";
        echo "<li>Method: " . $paymentMethod . "</li>";
        echo "<li>Transaction ID: " . $transactionId . "</li>";
        echo "</ul>";
        
        // Test the allocatePayment method directly
        echo "<h5>Testing allocatePayment Method:</h5>";
        
        $result = $tracker->allocatePayment(
            $testBookingId,
            $paymentAmount,
            $paymentDate,
            $paymentMethod,
            $transactionId
        );
        
        if ($result['success']) {
            echo "<p style='color: green;'>✅ Payment allocated successfully!</p>";
            echo "<p>Message: " . $result['message'] . "</p>";
            echo "<p>Allocated Month: " . $result['allocated_month'] . "</p>";
            
            // Check if the database was actually updated
            echo "<h5>Verifying Database Update:</h5>";
            
            $stmt = $conn->prepare("
                SELECT * FROM monthly_rent_payments 
                WHERE booking_id = ? AND month = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->bind_param('is', $testBookingId, $result['allocated_month']);
            $stmt->execute();
            $updatedRecord = $stmt->get_result()->fetch_assoc();
            
            if ($updatedRecord) {
                echo "<p style='color: green;'>✅ Database record updated successfully!</p>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr style='background-color: #f0f0f0;'>";
                echo "<th>Field</th><th>Value</th>";
                echo "</tr>";
                echo "<tr><td>Status</td><td>" . $updatedRecord['status'] . "</td></tr>";
                echo "<tr><td>Payment Date</td><td>" . ($updatedRecord['payment_date'] ?: 'NULL') . "</td></tr>";
                echo "<tr><td>Payment Method</td><td>" . ($updatedRecord['payment_method'] ?: 'NULL') . "</td></tr>";
                echo "<tr><td>Transaction ID</td><td>" . ($updatedRecord['transaction_id'] ?: 'NULL') . "</td></tr>";
                echo "<tr><td>Notes</td><td>" . ($updatedRecord['notes'] ?: 'NULL') . "</td></tr>";
                echo "</table>";
            } else {
                echo "<p style='color: red;'>❌ Database record not found after update!</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ Payment failed: " . $result['message'] . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ No next payment due found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}

// Test the booking_payment.php logic
echo "<h4>Testing booking_payment.php Logic:</h4>";

// Simulate the payment type determination
$additionalFees = $booking['additional_fees'] ?? 0;
$monthlyRent = floatval($booking['monthly_rent']);
$securityDeposit = floatval($booking['security_deposit'] ?? 0);

if ($nextPaymentDue) {
    $totalAmount = $nextPaymentDue['amount'] + $additionalFees;
    $paymentType = 'monthly_payment';
    $paymentDescription = 'Payment for ' . date('F Y', strtotime($nextPaymentDue['month']));
} else {
    $totalAmount = 0;
    $paymentType = 'completed';
    $paymentDescription = 'All payments completed';
}

echo "<p>Payment Type: $paymentType</p>";
echo "<p>Payment Description: $paymentDescription</p>";
echo "<p>Total Amount: KSh " . number_format($totalAmount, 2) . "</p>";

// Test form submission simulation
echo "<h4>Testing Form Submission:</h4>";

// Simulate POST data
$_POST = [
    'amount' => $totalAmount,
    'payment_method' => 'Test Payment',
    'transaction_id' => 'TEST_' . time(),
    'notes' => 'Debug test payment',
    'payment_type' => $paymentType
];

echo "<p>Simulated POST data:</p>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

// Test the payment processing logic
if ($paymentType === 'monthly_payment') {
    echo "<p style='color: green;'>✅ Would process as monthly_payment</p>";
} elseif ($paymentType === 'completed') {
    echo "<p style='color: red;'>❌ Would throw 'All payments completed' error</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Would fall back to regular payment processing</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "<li><a href='test_payment_update.php?booking_id=$testBookingId'>Test Payment Update</a></li>";
echo "</ul>";
?>

<style>
table {
    margin: 10px 0;
}
th, td {
    padding: 8px;
    text-align: left;
}
pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 5px;
    overflow-x: auto;
}
</style> 