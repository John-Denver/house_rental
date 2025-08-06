<?php
/**
 * Debug script to test payment flow and payment type determination
 */

require_once '../config/db.php';
require_once 'monthly_payment_tracker.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Payment Flow Debug</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 38;

echo "<h3>Testing with Booking ID: $testBookingId</h3>";

try {
    $tracker = new MonthlyPaymentTracker($conn);
    
    // Get monthly payments
    $monthlyPayments = $tracker->getMonthlyPayments($testBookingId);
    
    echo "<h4>Monthly Payments:</h4>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Method</th></tr>";
    
    $hasAnyPaidPayments = false;
    foreach ($monthlyPayments as $payment) {
        echo "<tr>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['payment_date'] ?: '-') . "</td>";
        echo "<td>" . ($payment['payment_method'] ?: '-') . "</td>";
        echo "</tr>";
        
        if ($payment['status'] === 'paid') {
            $hasAnyPaidPayments = true;
        }
    }
    echo "</table>";
    
    echo "<p><strong>Has any paid payments:</strong> " . ($hasAnyPaidPayments ? 'Yes' : 'No') . "</p>";
    
    // Get next payment due
    $nextPaymentDue = $tracker->getNextPaymentDue($testBookingId);
    
    if ($nextPaymentDue) {
        echo "<h4>Next Payment Due:</h4>";
        echo "<ul>";
        echo "<li><strong>Month:</strong> " . date('F Y', strtotime($nextPaymentDue['month'])) . "</li>";
        echo "<li><strong>Amount:</strong> KSh " . number_format($nextPaymentDue['amount'], 2) . "</li>";
        echo "</ul>";
        
        // Determine payment type
        if ($hasAnyPaidPayments) {
            $paymentType = 'monthly_payment';
            $paymentDescription = 'Payment for ' . date('F Y', strtotime($nextPaymentDue['month']));
            echo "<p style='color: blue;'>📋 <strong>Payment Type:</strong> $paymentType</p>";
            echo "<p style='color: blue;'>📋 <strong>Description:</strong> $paymentDescription</p>";
        } else {
            $paymentType = 'initial';
            $paymentDescription = 'Initial payment (first month + security deposit) for ' . date('F Y', strtotime($nextPaymentDue['month']));
            echo "<p style='color: orange;'>📋 <strong>Payment Type:</strong> $paymentType</p>";
            echo "<p style='color: orange;'>📋 <strong>Description:</strong> $paymentDescription</p>";
        }
        
        // Test payment allocation
        if (isset($_GET['test_allocation'])) {
            echo "<h4>Testing Payment Allocation:</h4>";
            
            $paymentAmount = $nextPaymentDue['amount'];
            $paymentDate = date('Y-m-d H:i:s');
            $paymentMethod = 'Test Payment';
            $transactionId = 'TEST_' . time();
            
            echo "<p>Allocating payment:</p>";
            echo "<ul>";
            echo "<li>Amount: KSh " . number_format($paymentAmount, 2) . "</li>";
            echo "<li>Date: " . $paymentDate . "</li>";
            echo "<li>Method: " . $paymentMethod . "</li>";
            echo "<li>Transaction ID: " . $transactionId . "</li>";
            echo "<li>Payment Type: " . $paymentType . "</li>";
            echo "</ul>";
            
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
                echo "<p>Allocated to: " . date('F Y', strtotime($result['allocated_month'])) . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Payment failed: " . $result['message'] . "</p>";
            }
        } else {
            echo "<p><a href='?booking_id=$testBookingId&test_allocation=1' class='btn btn-primary'>Test Payment Allocation</a></p>";
        }
        
    } else {
        echo "<p style='color: green;'>✅ All payments completed!</p>";
    }
    
    // Show M-Pesa payment requests
    echo "<h4>Recent M-Pesa Payment Requests:</h4>";
    $mpesaResult = $conn->query("
        SELECT id, booking_id, amount, payment_type, status, created_at 
        FROM mpesa_payment_requests 
        WHERE booking_id = $testBookingId 
        ORDER BY id DESC 
        LIMIT 5
    ");
    
    if ($mpesaResult && $mpesaResult->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Amount</th><th>Payment Type</th><th>Status</th><th>Created</th></tr>";
        
        while ($row = $mpesaResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>KSh " . number_format($row['amount'], 2) . "</td>";
            echo "<td>" . ($row['payment_type'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No M-Pesa payment requests found for this booking.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='check_payment_result.php?booking_id=$testBookingId'>Check Payment Result</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "</ul>";
?> 