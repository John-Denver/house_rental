<?php
/**
 * Simple Payment Test
 * Test the payment allocation process step by step
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Simple Payment Test</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 32;

echo "<h3>Testing with Booking ID: $testBookingId</h3>";

try {
    require_once __DIR__ . '/monthly_payment_tracker.php';
    $tracker = new MonthlyPaymentTracker($conn);
    
    // Step 1: Check if monthly records exist
    echo "<h4>Step 1: Check Monthly Records</h4>";
    $payments = $tracker->getMonthlyPayments($testBookingId);
    echo "<p>Found " . count($payments) . " monthly payment records</p>";
    
    if (count($payments) == 0) {
        echo "<p style='color: red;'>❌ No monthly records found. Creating them...</p>";
        $payments = $tracker->getMonthlyPayments($testBookingId); // This will create them
        echo "<p style='color: green;'>✅ Created " . count($payments) . " monthly records</p>";
    }
    
    // Step 2: Get next unpaid month
    echo "<h4>Step 2: Get Next Unpaid Month</h4>";
    $nextUnpaidMonth = $tracker->getNextUnpaidMonth($testBookingId);
    
    if ($nextUnpaidMonth) {
        echo "<p style='color: green;'>✅ Next unpaid month: " . date('F Y', strtotime($nextUnpaidMonth)) . "</p>";
        
        // Step 3: Check the current status of this month
        echo "<h4>Step 3: Check Current Status</h4>";
        $stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? AND month = ?");
        $stmt->bind_param('is', $testBookingId, $nextUnpaidMonth);
        $stmt->execute();
        $currentRecord = $stmt->get_result()->fetch_assoc();
        
        if ($currentRecord) {
            echo "<p>Current status: " . $currentRecord['status'] . "</p>";
            echo "<p>Payment date: " . ($currentRecord['payment_date'] ?: 'NULL') . "</p>";
            echo "<p>Payment method: " . ($currentRecord['payment_method'] ?: 'NULL') . "</p>";
        } else {
            echo "<p style='color: red;'>❌ No record found for this month!</p>";
        }
        
        // Step 4: Test payment allocation
        if (isset($_GET['test_payment'])) {
            echo "<h4>Step 4: Test Payment Allocation</h4>";
            
            $paymentAmount = $currentRecord['amount'];
            $paymentDate = date('Y-m-d H:i:s');
            $paymentMethod = 'Test Payment';
            $transactionId = 'TEST_' . time();
            
            echo "<p>Allocating payment:</p>";
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
                
                // Step 5: Verify the update
                echo "<h4>Step 5: Verify Update</h4>";
                $stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? AND month = ?");
                $stmt->bind_param('is', $testBookingId, $nextUnpaidMonth);
                $stmt->execute();
                $updatedRecord = $stmt->get_result()->fetch_assoc();
                
                if ($updatedRecord) {
                    echo "<p style='color: green;'>✅ Record updated successfully!</p>";
                    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                    echo "<tr style='background-color: #f0f0f0;'>";
                    echo "<th>Field</th><th>Before</th><th>After</th>";
                    echo "</tr>";
                    echo "<tr><td>Status</td><td>" . $currentRecord['status'] . "</td><td>" . $updatedRecord['status'] . "</td></tr>";
                    echo "<tr><td>Payment Date</td><td>" . ($currentRecord['payment_date'] ?: 'NULL') . "</td><td>" . ($updatedRecord['payment_date'] ?: 'NULL') . "</td></tr>";
                    echo "<tr><td>Payment Method</td><td>" . ($currentRecord['payment_method'] ?: 'NULL') . "</td><td>" . ($updatedRecord['payment_method'] ?: 'NULL') . "</td></tr>";
                    echo "<tr><td>Transaction ID</td><td>" . ($currentRecord['transaction_id'] ?: 'NULL') . "</td><td>" . ($updatedRecord['transaction_id'] ?: 'NULL') . "</td></tr>";
                    echo "</table>";
                } else {
                    echo "<p style='color: red;'>❌ Record not found after update!</p>";
                }
                
            } else {
                echo "<p style='color: red;'>❌ Payment failed: " . $result['message'] . "</p>";
            }
        } else {
            echo "<p><a href='?booking_id=$testBookingId&test_payment=1' class='btn btn-primary'>Test Payment Allocation</a></p>";
        }
        
    } else {
        echo "<p style='color: green;'>✅ All payments completed!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "<li><a href='debug_payment_process.php?booking_id=$testBookingId'>Debug Payment Process</a></li>";
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