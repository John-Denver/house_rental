<?php
/**
 * Test New Monthly Payment System
 * This page tests the fresh monthly payment tracking system
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Test New Monthly Payment System</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    echo "<p>Session data: " . json_encode($_SESSION) . "</p>";
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
    
    echo "<h4>Monthly Payments:</h4>";
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
    
    // Get payment summary
    $summary = $tracker->getPaymentSummary($testBookingId);
    echo "<h4>Payment Summary:</h4>";
    echo "<ul>";
    echo "<li>Total Months: " . $summary['total_months'] . "</li>";
    echo "<li>Paid Months: " . $summary['paid_months'] . "</li>";
    echo "<li>Unpaid Months: " . $summary['unpaid_months'] . "</li>";
    echo "<li>Total Paid: KSh " . number_format($summary['total_paid'], 2) . "</li>";
    echo "<li>Total Unpaid: KSh " . number_format($summary['total_unpaid'], 2) . "</li>";
    echo "</ul>";
    
    // Get next payment due
    $nextPayment = $tracker->getNextPaymentDue($testBookingId);
    if ($nextPayment) {
        echo "<h4>Next Payment Due:</h4>";
        echo "<p>" . date('F Y', strtotime($nextPayment['month'])) . " - KSh " . number_format($nextPayment['amount'], 2) . "</p>";
        
        // Test payment allocation
        echo "<h4>Test Payment Allocation:</h4>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='booking_id' value='$testBookingId'>";
        echo "<input type='hidden' name='amount' value='" . $nextPayment['amount'] . "'>";
        echo "<input type='text' name='payment_method' placeholder='Payment Method' value='Test Payment' required>";
        echo "<button type='submit' name='test_allocate'>Test Allocate Payment</button>";
        echo "</form>";
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
        
        $result = $tracker->allocatePayment($bookingId, $amount, $paymentDate, $paymentMethod);
        
        echo "<div style='color: green; margin: 10px 0;'>";
        echo "✅ " . $result['message'];
        echo "</div>";
        
        // Redirect to refresh the page
        echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
        
    } catch (Exception $e) {
        echo "<div style='color: red; margin: 10px 0;'>";
        echo "❌ Error: " . $e->getMessage();
        echo "</div>";
    }
}

// Test the new API endpoint
echo "<h3>Test New API Endpoint:</h3>";
echo "<button onclick='testNewAPI()'>Test get_monthly_payments_new.php</button>";
echo "<div id='apiResult'></div>";

?>

<script>
function testNewAPI() {
    const bookingId = <?php echo $testBookingId; ?>;
    
    fetch('get_monthly_payments_new.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'booking_id=' + bookingId
    })
    .then(response => response.json())
    .then(data => {
        console.log('API Response:', data);
        document.getElementById('apiResult').innerHTML = `
            <h4>API Response:</h4>
            <pre>${JSON.stringify(data, null, 2)}</pre>
        `;
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('apiResult').innerHTML = `
            <h4>API Error:</h4>
            <pre>${error.message}</pre>
        `;
    });
}
</script>

<style>
table {
    margin: 10px 0;
}
th, td {
    padding: 8px;
    text-align: left;
}
pre {
    background-color: #f5f5f5;
    padding: 10px;
    border: 1px solid #ddd;
    overflow-x: auto;
}
</style> 