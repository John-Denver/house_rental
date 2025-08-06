<?php
/**
 * Debug Payment Processing
 * See exactly what happens when a payment is processed
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Debug Payment Processing</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 36;

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

// Test the payment type determination (same logic as booking_payment.php)
echo "<h4>Testing Payment Type Determination:</h4>";

try {
    require_once __DIR__ . '/monthly_payment_tracker.php';
    $tracker = new MonthlyPaymentTracker($conn);
    
    // Get next payment due
    $nextPaymentDue = $tracker->getNextPaymentDue($testBookingId);
    
    $additionalFees = $booking['additional_fees'] ?? 0;
    $monthlyRent = floatval($booking['monthly_rent']);
    $securityDeposit = floatval($booking['security_deposit'] ?? 0);
    
    if ($nextPaymentDue) {
        $totalAmount = $nextPaymentDue['amount'] + $additionalFees;
        $paymentType = 'monthly_payment';
        $paymentDescription = 'Payment for ' . date('F Y', strtotime($nextPaymentDue['month']));
        echo "<p style='color: green;'>✅ Next payment due: " . date('F Y', strtotime($nextPaymentDue['month'])) . " - Amount: KSh " . number_format($totalAmount, 2) . "</p>";
        echo "<p>Payment Type: $paymentType</p>";
        echo "<p>Payment Description: $paymentDescription</p>";
    } else {
        $totalAmount = 0;
        $paymentType = 'completed';
        $paymentDescription = 'All payments completed';
        echo "<p style='color: green;'>✅ All payments completed!</p>";
        echo "<p>Payment Type: $paymentType</p>";
    }
    
    // Simulate form submission
    if (isset($_GET['test_submission'])) {
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
            
            // Test the actual allocation
            $paymentDate = date('Y-m-d H:i:s');
            $result = $tracker->allocatePayment(
                $testBookingId,
                $totalAmount,
                $paymentDate,
                'Test Payment',
                'TEST_' . time()
            );
            
            if ($result['success']) {
                echo "<p style='color: green;'>✅ Payment allocated successfully!</p>";
                echo "<p>Message: " . $result['message'] . "</p>";
                echo "<p>Allocated Month: " . $result['allocated_month'] . "</p>";
                
                // Check if the database was actually updated
                $stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? AND month = ?");
                $stmt->bind_param('is', $testBookingId, $result['allocated_month']);
                $stmt->execute();
                $updatedRecord = $stmt->get_result()->fetch_assoc();
                
                if ($updatedRecord && $updatedRecord['status'] === 'paid') {
                    echo "<p style='color: green;'>✅ Database record updated successfully!</p>";
                    echo "<p>Status: " . $updatedRecord['status'] . "</p>";
                    echo "<p>Payment Date: " . ($updatedRecord['payment_date'] ?: 'NULL') . "</p>";
                    echo "<p>Payment Method: " . ($updatedRecord['payment_method'] ?: 'NULL') . "</p>";
                } else {
                    echo "<p style='color: red;'>❌ Database record not updated!</p>";
                }
                
            } else {
                echo "<p style='color: red;'>❌ Payment failed: " . $result['message'] . "</p>";
            }
            
        } elseif ($paymentType === 'completed') {
            echo "<p style='color: red;'>❌ Would throw 'All payments completed' error</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Would fall back to regular payment processing</p>";
        }
    } else {
        echo "<p><a href='?booking_id=$testBookingId&test_submission=1' class='btn btn-primary'>Test Form Submission</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='check_monthly_table.php?booking_id=$testBookingId'>Check Monthly Table</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
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
.btn {
    margin: 10px 0;
}
pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 5px;
    overflow-x: auto;
}
</style> 