<?php
/**
 * Test script to verify initial payment logic
 */

require_once '../config/db.php';
require_once 'monthly_payment_tracker.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Initial Payment Test</h2>";

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
    
    // Get booking details
    $bookingStmt = $conn->prepare("
        SELECT monthly_rent, security_deposit, start_date, end_date
        FROM rental_bookings 
        WHERE id = ?
    ");
    $bookingStmt->bind_param('i', $testBookingId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc();
    
    if ($booking) {
        echo "<h4>Booking Details:</h4>";
        echo "<ul>";
        echo "<li><strong>Monthly Rent:</strong> KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
        echo "<li><strong>Security Deposit:</strong> KSh " . number_format($booking['security_deposit'], 2) . "</li>";
        echo "<li><strong>Start Date:</strong> " . $booking['start_date'] . "</li>";
        echo "<li><strong>End Date:</strong> " . $booking['end_date'] . "</li>";
        echo "</ul>";
        
        $monthlyRent = floatval($booking['monthly_rent']);
        $securityDeposit = floatval($booking['security_deposit']);
        $totalExpected = $monthlyRent + $securityDeposit;
        
        echo "<p><strong>Expected Initial Payment:</strong> KSh " . number_format($totalExpected, 2) . "</p>";
        
        // Get monthly payments
        $monthlyPayments = $tracker->getMonthlyPayments($testBookingId);
        
        echo "<h4>Current Monthly Payments:</h4>";
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
        
        // Test initial payment allocation
        if (isset($_GET['test_initial']) && !$hasAnyPaidPayments) {
            echo "<h4>Testing Initial Payment Allocation:</h4>";
            
            $paymentAmount = $totalExpected; // Full initial payment amount
            $paymentDate = date('Y-m-d H:i:s');
            $paymentMethod = 'Test Initial Payment';
            $transactionId = 'TEST_INITIAL_' . time();
            
            echo "<p>Allocating initial payment:</p>";
            echo "<ul>";
            echo "<li>Total Amount: KSh " . number_format($paymentAmount, 2) . "</li>";
            echo "<li>Monthly Rent Portion: KSh " . number_format($monthlyRent, 2) . "</li>";
            echo "<li>Security Deposit Portion: KSh " . number_format($securityDeposit, 2) . "</li>";
            echo "<li>Date: " . $paymentDate . "</li>";
            echo "<li>Method: " . $paymentMethod . "</li>";
            echo "<li>Transaction ID: " . $transactionId . "</li>";
            echo "</ul>";
            
            // Allocate only the monthly rent portion to the first month
            $result = $tracker->allocatePayment(
                $testBookingId,
                $monthlyRent, // Only allocate the monthly rent portion
                $paymentDate,
                $paymentMethod,
                $transactionId
            );
            
            if ($result['success']) {
                echo "<p style='color: green;'>✅ Initial payment allocated successfully!</p>";
                echo "<p>Message: " . $result['message'] . "</p>";
                echo "<p>Allocated to: " . date('F Y', strtotime($result['allocated_month'])) . "</p>";
                echo "<p><strong>Note:</strong> Only the monthly rent portion (KSh " . number_format($monthlyRent, 2) . ") was allocated to the monthly payments table. The security deposit (KSh " . number_format($securityDeposit, 2) . ") is handled separately.</p>";
            } else {
                echo "<p style='color: red;'>❌ Initial payment failed: " . $result['message'] . "</p>";
            }
        } elseif (isset($_GET['test_initial']) && $hasAnyPaidPayments) {
            echo "<p style='color: orange;'>⚠️ Cannot test initial payment - booking already has paid payments.</p>";
        } else {
            if (!$hasAnyPaidPayments) {
                echo "<p><a href='?booking_id=$testBookingId&test_initial=1' class='btn btn-primary'>Test Initial Payment Allocation</a></p>";
            }
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
        
    } else {
        echo "<p style='color: red;'>❌ Booking not found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='check_payment_result.php?booking_id=$testBookingId'>Check Payment Result</a></li>";
echo "<li><a href='test_payment_flow_debug.php?booking_id=$testBookingId'>Payment Flow Debug</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "</ul>";
?> 