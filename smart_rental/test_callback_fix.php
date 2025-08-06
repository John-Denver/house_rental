<?php
/**
 * Test M-Pesa Callback Fix
 * Manually triggers the callback logic to test if the monthly_rent_payments table gets updated
 */

require_once '../config/db.php';
require_once 'monthly_payment_tracker.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Test M-Pesa Callback Fix</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 43;

echo "<h3>Testing with Booking ID: $testBookingId</h3>";

try {
    // Get the most recent completed M-Pesa payment
    $completedPayment = $conn->query("
        SELECT * FROM mpesa_payment_requests 
        WHERE booking_id = $testBookingId AND status = 'completed' 
        ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();
    
    if (!$completedPayment) {
        echo "<p style='color: red;'>❌ No completed M-Pesa payment found for this booking.</p>";
        exit;
    }
    
    echo "<h4>Found Completed Payment:</h4>";
    echo "<ul>";
    echo "<li><strong>Payment ID:</strong> " . $completedPayment['id'] . "</li>";
    echo "<li><strong>Amount:</strong> KSh " . number_format($completedPayment['amount'], 2) . "</li>";
    echo "<li><strong>Payment Type:</strong> " . ($completedPayment['payment_type'] ?? 'NULL') . "</li>";
    echo "<li><strong>Checkout Request ID:</strong> " . $completedPayment['checkout_request_id'] . "</li>";
    echo "<li><strong>Status:</strong> " . $completedPayment['status'] . "</li>";
    echo "</ul>";
    
    // Show current monthly payments BEFORE test
    echo "<h4>Monthly Payments BEFORE Test:</h4>";
    $tracker = new MonthlyPaymentTracker($conn);
    $monthlyPaymentsBefore = $tracker->getMonthlyPayments($testBookingId);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Method</th></tr>";
    
    foreach ($monthlyPaymentsBefore as $payment) {
        echo "<tr>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['payment_date'] ?: '-') . "</td>";
        echo "<td>" . ($payment['payment_method'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test the callback logic
    if (isset($_GET['test_callback'])) {
        echo "<h4>Testing Callback Logic...</h4>";
        
        // Simulate the callback processing
        $paymentType = $completedPayment['payment_type'] ?? 'initial';
        $paymentAmount = floatval($completedPayment['amount']);
        $checkoutRequestId = $completedPayment['checkout_request_id'];
        $mpesaReceiptNumber = 'TEST_RECEIPT_' . time();
        $transactionId = 'MPESA_' . time();
        
        echo "<p><strong>Processing payment:</strong></p>";
        echo "<ul>";
        echo "<li>Payment Type: $paymentType</li>";
        echo "<li>Amount: KSh " . number_format($paymentAmount, 2) . "</li>";
        echo "<li>Checkout Request ID: $checkoutRequestId</li>";
        echo "<li>Transaction ID: $transactionId</li>";
        echo "<li>Receipt Number: $mpesaReceiptNumber</li>";
        echo "</ul>";
        
        if ($paymentType === 'initial') {
            // Get booking details
            $bookingStmt = $conn->prepare("
                SELECT monthly_rent, security_deposit 
                FROM rental_bookings 
                WHERE id = ?
            ");
            $bookingStmt->bind_param('i', $testBookingId);
            $bookingStmt->execute();
            $booking = $bookingStmt->get_result()->fetch_assoc();
            
            if ($booking) {
                $monthlyRent = floatval($booking['monthly_rent']);
                $securityDeposit = floatval($booking['security_deposit']);
                $totalExpected = $monthlyRent + $securityDeposit;
                
                echo "<p><strong>Payment breakdown:</strong></p>";
                echo "<ul>";
                echo "<li>Monthly Rent: KSh " . number_format($monthlyRent, 2) . "</li>";
                echo "<li>Security Deposit: KSh " . number_format($securityDeposit, 2) . "</li>";
                echo "<li>Total Expected: KSh " . number_format($totalExpected, 2) . "</li>";
                echo "</ul>";
                
                // Verify the payment amount matches expected
                if (abs($paymentAmount - $totalExpected) < 0.01) {
                    // Allocate only the monthly rent portion to the first month
                    $result = $tracker->allocatePayment(
                        $testBookingId,
                        $monthlyRent, // Only allocate the monthly rent portion
                        date('Y-m-d H:i:s'),
                        'M-Pesa',
                        $transactionId,
                        $mpesaReceiptNumber
                    );
                    
                    if ($result['success']) {
                        echo "<p style='color: green;'>✅ Initial payment allocated successfully!</p>";
                        echo "<p>Message: " . $result['message'] . "</p>";
                        echo "<p>Allocated to: " . date('F Y', strtotime($result['allocated_month'])) . "</p>";
                        echo "<p><strong>Note:</strong> Only the monthly rent portion (KSh " . number_format($monthlyRent, 2) . ") was allocated to the monthly payments table.</p>";
                    } else {
                        echo "<p style='color: red;'>❌ Initial payment allocation failed: " . $result['message'] . "</p>";
                    }
                } else {
                    echo "<p style='color: red;'>❌ Amount mismatch. Expected: $totalExpected, Received: $paymentAmount</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ Could not get booking details</p>";
            }
        } else {
            // For monthly payments, allocate the full amount
            $result = $tracker->allocatePayment(
                $testBookingId,
                $paymentAmount,
                date('Y-m-d H:i:s'),
                'M-Pesa',
                $transactionId,
                $mpesaReceiptNumber
            );
            
            if ($result['success']) {
                echo "<p style='color: green;'>✅ Monthly payment allocated successfully!</p>";
                echo "<p>Message: " . $result['message'] . "</p>";
                echo "<p>Allocated to: " . date('F Y', strtotime($result['allocated_month'])) . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Monthly payment allocation failed: " . $result['message'] . "</p>";
            }
        }
        
        // Show updated monthly payments AFTER test
        echo "<h4>Monthly Payments AFTER Test:</h4>";
        $monthlyPaymentsAfter = $tracker->getMonthlyPayments($testBookingId);
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Method</th></tr>";
        
        foreach ($monthlyPaymentsAfter as $payment) {
            echo "<tr>";
            echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
            echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
            echo "<td>" . $payment['status'] . "</td>";
            echo "<td>" . ($payment['payment_date'] ?: '-') . "</td>";
            echo "<td>" . ($payment['payment_method'] ?: '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p><a href='?booking_id=$testBookingId&test_callback=1' class='btn btn-primary'>Test Callback Logic</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='test_mpesa_callback.php?booking_id=$testBookingId'>M-Pesa Callback Test</a></li>";
echo "<li><a href='debug_initial_payment.php?booking_id=$testBookingId'>Initial Payment Debug</a></li>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "</ul>";
?> 