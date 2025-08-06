<?php
/**
 * Test Payment Allocation
 * Verify that payments are allocated to the correct months
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Test Payment Allocation</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Include the monthly payment tracker
require_once __DIR__ . '/monthly_payment_tracker.php';
$tracker = new MonthlyPaymentTracker($conn);

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 6;

echo "<h3>Testing with Booking ID: $testBookingId</h3>";

try {
    // Get monthly payments
    $payments = $tracker->getMonthlyPayments($testBookingId);
    
    echo "<h4>Current Monthly Payments:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Month</th><th>Amount</th><th>Status</th><th>Type</th><th>Payment Date</th>";
    echo "</tr>";
    
    foreach ($payments as $payment) {
        $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
        echo "<td>" . $payment['payment_type'] . "</td>";
        echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Get next payment due
    $nextPaymentDue = $tracker->getNextPaymentDue($testBookingId);
    
    if ($nextPaymentDue) {
        echo "<h4>Next Payment Due:</h4>";
        echo "<p>" . date('F Y', strtotime($nextPaymentDue['month'])) . " - KSh " . number_format($nextPaymentDue['amount'], 2) . "</p>";
        
        // Test payment allocation
        echo "<h4>Test Payment Allocation:</h4>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='booking_id' value='$testBookingId'>";
        echo "<input type='hidden' name='amount' value='" . $nextPaymentDue['amount'] . "'>";
        echo "<input type='text' name='payment_method' placeholder='Payment Method' value='Test Payment' required>";
        echo "<button type='submit' name='test_allocate'>Test Allocate Payment</button>";
        echo "</form>";
        
        echo "<h4>Expected Result:</h4>";
        echo "<p>Payment should be allocated to <strong>" . date('F Y', strtotime($nextPaymentDue['month'])) . "</strong></p>";
    } else {
        echo "<h4>Next Payment Due:</h4>";
        echo "<p>All payments completed!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Handle test payment allocation
if (isset($_POST['test_allocate'])) {
    try {
        $bookingId = $_POST['booking_id'];
        $amount = $_POST['amount'];
        $paymentMethod = $_POST['payment_method'];
        $paymentDate = date('Y-m-d H:i:s');
        
        echo "<h4>Processing Payment...</h4>";
        echo "<p>Amount: KSh " . number_format($amount, 2) . "</p>";
        echo "<p>Method: $paymentMethod</p>";
        echo "<p>Date: $paymentDate</p>";
        
        $result = $tracker->allocatePayment($bookingId, $amount, $paymentDate, $paymentMethod);
        
        echo "<div style='color: green; margin: 10px 0; padding: 10px; border: 1px solid green; background-color: #f0fff0;'>";
        echo "<h5>✅ Payment Allocated Successfully!</h5>";
        echo "<p><strong>Message:</strong> " . $result['message'] . "</p>";
        echo "<p><strong>Allocated Month:</strong> " . date('F Y', strtotime($result['allocated_month'])) . "</p>";
        echo "<p><strong>Amount:</strong> KSh " . number_format($result['amount'], 2) . "</p>";
        echo "</div>";
        
        // Show updated payments
        echo "<h4>Updated Monthly Payments:</h4>";
        $updatedPayments = $tracker->getMonthlyPayments($bookingId);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Month</th><th>Amount</th><th>Status</th><th>Type</th><th>Payment Date</th>";
        echo "</tr>";
        
        foreach ($updatedPayments as $payment) {
            $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
            $rowStyle = ($payment['month'] === $result['allocated_month']) ? 'background-color: #e8f5e8;' : '';
            echo "<tr style='$rowStyle'>";
            echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
            echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
            echo "<td>" . $payment['payment_type'] . "</td>";
            echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show next payment due
        $nextPaymentDue = $tracker->getNextPaymentDue($bookingId);
        if ($nextPaymentDue) {
            echo "<h4>Next Payment Due:</h4>";
            echo "<p>" . date('F Y', strtotime($nextPaymentDue['month'])) . " - KSh " . number_format($nextPaymentDue['amount'], 2) . "</p>";
        } else {
            echo "<h4>Next Payment Due:</h4>";
            echo "<p>All payments completed!</p>";
        }
        
    } catch (Exception $e) {
        echo "<div style='color: red; margin: 10px 0; padding: 10px; border: 1px solid red; background-color: #fff0f0;'>";
        echo "<h5>❌ Payment Allocation Failed!</h5>";
        echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='test_new_system.php?booking_id=$testBookingId'>Test New System</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "<li><a href='booking_details.php?id=$testBookingId'>View Booking Details</a></li>";
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
</style> 