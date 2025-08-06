<?php
/**
 * Test Payment Update Process
 * Show how payments update the monthly_rent_payments table
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Test Payment Update Process</h2>";

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
    
    // Get current monthly payments
    $payments = $tracker->getMonthlyPayments($testBookingId);
    
    echo "<h5>Current Monthly Payments (Before Payment):</h5>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Method</th>";
    echo "</tr>";
    foreach ($payments as $payment) {
        $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
        echo "<td>" . ($payment['payment_method'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Get next payment due
    $nextPaymentDue = $tracker->getNextPaymentDue($testBookingId);
    
    if ($nextPaymentDue) {
        echo "<h5>Next Payment Due:</h5>";
        echo "<p>" . date('F Y', strtotime($nextPaymentDue['month'])) . " - KSh " . number_format($nextPaymentDue['amount'], 2) . "</p>";
        
        // Test payment allocation
        if (isset($_GET['test_payment'])) {
            echo "<h5>Testing Payment Allocation:</h5>";
            
            $paymentAmount = $nextPaymentDue['amount'];
            $paymentDate = date('Y-m-d H:i:s');
            $paymentMethod = 'Test Payment';
            $transactionId = 'TEST_' . time();
            
            echo "<p>Processing payment:</p>";
            echo "<ul>";
            echo "<li>Amount: KSh " . number_format($paymentAmount, 2) . "</li>";
            echo "<li>Date: " . $paymentDate . "</li>";
            echo "<li>Method: " . $paymentMethod . "</li>";
            echo "<li>Transaction ID: " . $transactionId . "</li>";
            echo "</ul>";
            
            // Allocate the payment
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
                
                // Show updated payments
                echo "<h5>Updated Monthly Payments (After Payment):</h5>";
                $updatedPayments = $tracker->getMonthlyPayments($testBookingId);
                
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr style='background-color: #f0f0f0;'>";
                echo "<th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Method</th><th>Transaction ID</th>";
                echo "</tr>";
                foreach ($updatedPayments as $payment) {
                    $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
                    echo "<tr>";
                    echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
                    echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
                    echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
                    echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
                    echo "<td>" . ($payment['payment_method'] ?: '-') . "</td>";
                    echo "<td>" . ($payment['transaction_id'] ?: '-') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // Show what changed
                echo "<h5>What Changed:</h5>";
                echo "<ul>";
                echo "<li>✅ Status changed from 'unpaid' to 'paid' for " . date('F Y', strtotime($nextPaymentDue['month'])) . "</li>";
                echo "<li>✅ Payment date added: " . date('M d, Y', strtotime($paymentDate)) . "</li>";
                echo "<li>✅ Payment method recorded: " . $paymentMethod . "</li>";
                echo "<li>✅ Transaction ID recorded: " . $transactionId . "</li>";
                echo "<li>✅ Notes updated with payment information</li>";
                echo "</ul>";
                
            } else {
                echo "<p style='color: red;'>❌ Payment failed: " . $result['message'] . "</p>";
            }
        } else {
            echo "<p><a href='?booking_id=$testBookingId&test_payment=1' class='btn btn-primary'>Test Payment Allocation</a></p>";
        }
        
    } else {
        echo "<p style='color: green;'>✅ All payments completed!</p>";
    }
    
    // Show payment summary
    echo "<h5>Payment Summary:</h5>";
    $summary = $tracker->getPaymentSummary($testBookingId);
    echo "<ul>";
    echo "<li>Total Months: " . $summary['total_months'] . "</li>";
    echo "<li>Paid Months: " . $summary['paid_months'] . "</li>";
    echo "<li>Unpaid Months: " . $summary['unpaid_months'] . "</li>";
    echo "<li>Total Paid: KSh " . number_format($summary['total_paid'], 2) . "</li>";
    echo "<li>Total Unpaid: KSh " . number_format($summary['total_unpaid'], 2) . "</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>How Payment Updates Work:</h3>";
echo "<ol>";
echo "<li><strong>User makes payment</strong> → Payment form submitted to booking_payment.php</li>";
echo "<li><strong>Payment processed</strong> → MonthlyPaymentTracker::allocatePayment() called</li>";
echo "<li><strong>Next unpaid month found</strong> → getNextUnpaidMonth() finds earliest unpaid month</li>";
echo "<li><strong>Table updated</strong> → UPDATE monthly_rent_payments SET status='paid', payment_date=?, etc.</li>";
echo "<li><strong>Success response</strong> → User redirected to my_bookings.php with success message</li>";
echo "</ol>";

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "<li><a href='booking_details.php?id=$testBookingId'>View Booking Details</a></li>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
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
</style> 