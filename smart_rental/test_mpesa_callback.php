<?php
/**
 * Test script to check M-Pesa callback and manually trigger payment allocation
 */

require_once '../config/db.php';
require_once 'monthly_payment_tracker.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>M-Pesa Callback Test</h2>";

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
    // Get recent M-Pesa payment requests
    echo "<h4>Recent M-Pesa Payment Requests:</h4>";
    $mpesaResult = $conn->query("
        SELECT id, booking_id, amount, payment_type, status, checkout_request_id, created_at 
        FROM mpesa_payment_requests 
        WHERE booking_id = $testBookingId 
        ORDER BY id DESC 
        LIMIT 5
    ");
    
    if ($mpesaResult && $mpesaResult->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Amount</th><th>Payment Type</th><th>Status</th><th>Checkout Request ID</th><th>Created</th></tr>";
        
        while ($row = $mpesaResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>KSh " . number_format($row['amount'], 2) . "</td>";
            echo "<td>" . ($row['payment_type'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . ($row['checkout_request_id'] ?: '-') . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Get the most recent completed payment
        $completedPayment = $conn->query("
            SELECT * FROM mpesa_payment_requests 
            WHERE booking_id = $testBookingId AND status = 'completed' 
            ORDER BY id DESC LIMIT 1
        ")->fetch_assoc();
        
        if ($completedPayment) {
            echo "<h4>Most Recent Completed Payment:</h4>";
            echo "<ul>";
            echo "<li><strong>ID:</strong> " . $completedPayment['id'] . "</li>";
            echo "<li><strong>Amount:</strong> KSh " . number_format($completedPayment['amount'], 2) . "</li>";
            echo "<li><strong>Payment Type:</strong> " . ($completedPayment['payment_type'] ?? 'NULL') . "</li>";
            echo "<li><strong>Status:</strong> " . $completedPayment['status'] . "</li>";
            echo "<li><strong>Checkout Request ID:</strong> " . $completedPayment['checkout_request_id'] . "</li>";
            echo "</ul>";
            
            // Test manual payment allocation
            if (isset($_GET['test_allocation'])) {
                echo "<h4>Testing Manual Payment Allocation:</h4>";
                
                $tracker = new MonthlyPaymentTracker($conn);
                
                $paymentAmount = floatval($completedPayment['amount']);
                $paymentDate = date('Y-m-d H:i:s');
                $paymentMethod = 'M-Pesa';
                $transactionId = 'MPESA_' . time();
                $paymentType = $completedPayment['payment_type'] ?? 'initial';
                
                echo "<p>Allocating payment manually:</p>";
                echo "<ul>";
                echo "<li>Amount: KSh " . number_format($paymentAmount, 2) . "</li>";
                echo "<li>Payment Type: $paymentType</li>";
                echo "<li>Date: $paymentDate</li>";
                echo "<li>Method: $paymentMethod</li>";
                echo "<li>Transaction ID: $transactionId</li>";
                echo "</ul>";
                
                if ($paymentType === 'initial') {
                    // Get booking details to determine security deposit and monthly rent
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
                            // Allocate the monthly rent portion to the first month
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
                        $paymentDate,
                        $paymentMethod,
                        $transactionId
                    );
                    
                    if ($result['success']) {
                        echo "<p style='color: green;'>✅ Monthly payment allocated successfully!</p>";
                        echo "<p>Message: " . $result['message'] . "</p>";
                        echo "<p>Allocated to: " . date('F Y', strtotime($result['allocated_month'])) . "</p>";
                    } else {
                        echo "<p style='color: red;'>❌ Monthly payment failed: " . $result['message'] . "</p>";
                    }
                }
            } else {
                echo "<p><a href='?booking_id=$testBookingId&test_allocation=1' class='btn btn-primary'>Test Manual Payment Allocation</a></p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ No completed M-Pesa payments found for this booking.</p>";
        }
    } else {
        echo "<p>No M-Pesa payment requests found for this booking.</p>";
    }
    
    // Show current monthly payments
    echo "<h4>Current Monthly Payments:</h4>";
    $tracker = new MonthlyPaymentTracker($conn);
    $monthlyPayments = $tracker->getMonthlyPayments($testBookingId);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Method</th></tr>";
    
    foreach ($monthlyPayments as $payment) {
        echo "<tr>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['payment_date'] ?: '-') . "</td>";
        echo "<td>" . ($payment['payment_method'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='debug_initial_payment.php?booking_id=$testBookingId'>Initial Payment Debug</a></li>";
echo "<li><a href='test_payment_flow_debug.php?booking_id=$testBookingId'>Payment Flow Debug</a></li>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "</ul>";
?> 