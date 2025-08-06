<?php
/**
 * Debug Pre-Payment Process
 * Tests the pre-payment process step by step
 */

session_start();
require_once '../config/db.php';
require_once 'includes/payment_tracking_helper.php';

echo "<h2>Debug Pre-Payment Process</h2>";

// Get booking ID from URL parameter
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;

if (!$bookingId) {
    echo "<p>Please provide a booking_id parameter: ?booking_id=X</p>";
    exit;
}

echo "<h3>Testing Booking ID: $bookingId</h3>";

try {
    // Get booking details
    $stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        echo "<p>❌ Booking not found</p>";
        exit;
    }

    echo "<h4>1. Booking Details:</h4>";
    echo "<ul>";
    echo "<li>Monthly Rent: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
    echo "<li>Security Deposit: KSh " . number_format($booking['security_deposit'] ?? 0, 2) . "</li>";
    echo "<li>Status: " . $booking['status'] . "</li>";
    echo "<li>Payment Status: " . $booking['payment_status'] . "</li>";
    echo "</ul>";

    // Test step 1: Check if first payment has been made
    echo "<h4>2. Check First Payment Status:</h4>";
    $hasFirstPayment = hasFirstPaymentBeenMade($conn, $bookingId);
    echo "<p><strong>Has First Payment:</strong> " . ($hasFirstPayment ? 'Yes' : 'No') . "</p>";

    if (!$hasFirstPayment) {
        echo "<p style='color: red;'>❌ Cannot make pre-payment - first payment not made yet!</p>";
        exit;
    }

    // Test step 2: Get next unpaid month
    echo "<h4>3. Get Next Unpaid Month:</h4>";
    $nextUnpaidMonth = getNextUnpaidMonth($conn, $bookingId);
    echo "<p><strong>Next Unpaid Month:</strong> " . ($nextUnpaidMonth ? date('F Y', strtotime($nextUnpaidMonth)) : 'None') . "</p>";

    if (!$nextUnpaidMonth) {
        echo "<p style='color: red;'>❌ No unpaid months found!</p>";
        exit;
    }

    // Test step 3: Check current monthly payments
    echo "<h4>4. Current Monthly Payments:</h4>";
    $stmt = $conn->prepare("
        SELECT month, status, amount, payment_type, is_first_payment 
        FROM monthly_rent_payments 
        WHERE booking_id = ? 
        ORDER BY month ASC
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if ($payments) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Month</th><th>Status</th><th>Amount</th><th>Type</th><th>First Payment</th>";
        echo "</tr>";
        foreach ($payments as $payment) {
            $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
            $rowColor = ($payment['month'] === $nextUnpaidMonth) ? 'background-color: #fff3cd;' : '';
            echo "<tr style='$rowColor'>";
            echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
            echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
            echo "<td>" . $payment['payment_type'] . "</td>";
            echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Test step 4: Simulate pre-payment
    echo "<h4>5. Test Pre-Payment Process:</h4>";
    
    if (isset($_GET['test_prepayment'])) {
        echo "<p><strong>Testing pre-payment...</strong></p>";
        
        try {
            // Test the recordPrePayment function
            $result = recordPrePayment(
                $conn,
                $bookingId,
                $booking['monthly_rent'],
                'manual',
                'TEST-' . time(),
                null,
                'Test pre-payment from debug script'
            );
            
            if ($result['success']) {
                echo "<p style='color: green;'>✅ Pre-payment recorded successfully!</p>";
                echo "<p><strong>Month Paid:</strong> " . date('F Y', strtotime($result['month_paid'])) . "</p>";
                echo "<p><strong>Message:</strong> " . $result['message'] . "</p>";
                
                // Check if the record was actually updated
                $stmt = $conn->prepare("
                    SELECT month, status, payment_date, payment_method 
                    FROM monthly_rent_payments 
                    WHERE booking_id = ? AND month = ?
                ");
                $stmt->bind_param('is', $bookingId, $result['month_paid']);
                $stmt->execute();
                $updatedPayment = $stmt->get_result()->fetch_assoc();
                
                if ($updatedPayment) {
                    echo "<p style='color: green;'>✅ Monthly payment record updated:</p>";
                    echo "<ul>";
                    echo "<li>Month: " . date('F Y', strtotime($updatedPayment['month'])) . "</li>";
                    echo "<li>Status: " . $updatedPayment['status'] . "</li>";
                    echo "<li>Payment Date: " . ($updatedPayment['payment_date'] ? date('M d, Y H:i:s', strtotime($updatedPayment['payment_date'])) : 'None') . "</li>";
                    echo "<li>Payment Method: " . ($updatedPayment['payment_method'] ?? 'None') . "</li>";
                    echo "</ul>";
                } else {
                    echo "<p style='color: red;'>❌ Monthly payment record not found!</p>";
                }
                
                // Check payment tracking table
                $stmt = $conn->prepare("
                    SELECT payment_type, amount, month, status, payment_date 
                    FROM payment_tracking 
                    WHERE booking_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->bind_param('i', $bookingId);
                $stmt->execute();
                $trackingRecord = $stmt->get_result()->fetch_assoc();
                
                if ($trackingRecord) {
                    echo "<p style='color: green;'>✅ Payment tracking record created:</p>";
                    echo "<ul>";
                    echo "<li>Type: " . $trackingRecord['payment_type'] . "</li>";
                    echo "<li>Amount: KSh " . number_format($trackingRecord['amount'], 2) . "</li>";
                    echo "<li>Month: " . ($trackingRecord['month'] ? date('F Y', strtotime($trackingRecord['month'])) : 'None') . "</li>";
                    echo "<li>Status: " . $trackingRecord['status'] . "</li>";
                    echo "</ul>";
                } else {
                    echo "<p style='color: red;'>❌ Payment tracking record not found!</p>";
                }
                
            } else {
                echo "<p style='color: red;'>❌ Pre-payment failed!</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p><a href='?booking_id=$bookingId&test_prepayment=1' class='btn btn-primary'>Test Pre-Payment</a></p>";
    }

    // Test step 5: Check for errors
    echo "<h4>6. Check for Potential Issues:</h4>";
    
    // Check if payment_tracking table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'payment_tracking'");
    echo "<p>payment_tracking table: " . ($tableExists->num_rows > 0 ? "✅ Exists" : "❌ Missing") . "</p>";
    
    // Check if monthly_rent_payments table has the right structure
    $stmt = $conn->prepare("DESCRIBE monthly_rent_payments");
    $stmt->execute();
    $columns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    $requiredColumns = ['booking_id', 'month', 'amount', 'status', 'payment_type', 'is_first_payment'];
    $missingColumns = array_diff($requiredColumns, $columnNames);
    
    if (empty($missingColumns)) {
        echo "<p>✅ All required columns exist in monthly_rent_payments table</p>";
    } else {
        echo "<p style='color: red;'>❌ Missing columns: " . implode(', ', $missingColumns) . "</p>";
    }

    echo "<h4>7. Test Links:</h4>";
    echo "<ul>";
    echo "<li><a href='booking_payment.php?id=$bookingId'>Go to Payment Page</a></li>";
    echo "<li><a href='check_monthly_payments.php?booking_id=$bookingId'>Check Monthly Payments</a></li>";
    echo "<li><a href='test_payment_insert.php?booking_id=$bookingId'>Test Payment Insert</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 