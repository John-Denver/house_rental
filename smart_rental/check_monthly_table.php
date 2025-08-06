<?php
/**
 * Check Monthly Rent Payments Table
 * Direct database check to see what's in the table
 */

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Check Monthly Rent Payments Table</h2>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 32;

echo "<h3>Checking Booking ID: $testBookingId</h3>";

// Check if booking exists
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

// Check monthly_rent_payments table
echo "<h4>Monthly Rent Payments Records:</h4>";

$stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? ORDER BY month ASC");
$stmt->bind_param('i', $testBookingId);
$stmt->execute();
$monthlyPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($monthlyPayments) {
    echo "<p>Found " . count($monthlyPayments) . " records</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Method</th><th>Transaction ID</th>";
    echo "</tr>";
    foreach ($monthlyPayments as $payment) {
        $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
        echo "<td>" . ($payment['payment_method'] ?: '-') . "</td>";
        echo "<td>" . ($payment['transaction_id'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check unpaid months
    echo "<h4>Unpaid Months:</h4>";
    $stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? AND status = 'unpaid' ORDER BY month ASC");
    $stmt->bind_param('i', $testBookingId);
    $stmt->execute();
    $unpaidMonths = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if ($unpaidMonths) {
        echo "<p>Found " . count($unpaidMonths) . " unpaid months:</p>";
        echo "<ul>";
        foreach ($unpaidMonths as $month) {
            echo "<li>" . date('F Y', strtotime($month['month'])) . " - KSh " . number_format($month['amount'], 2) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: green;'>✅ All months are paid!</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ No monthly payment records found!</p>";
    
    // Try to create them
    echo "<h4>Attempting to Create Monthly Records:</h4>";
    try {
        require_once __DIR__ . '/monthly_payment_tracker.php';
        $tracker = new MonthlyPaymentTracker($conn);
        $payments = $tracker->getMonthlyPayments($testBookingId);
        echo "<p style='color: green;'>✅ Created " . count($payments) . " monthly records</p>";
        
        // Show the created records
        $stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? ORDER BY month ASC");
        $stmt->bind_param('i', $testBookingId);
        $stmt->execute();
        $newPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Month</th><th>Amount</th><th>Status</th><th>Type</th>";
        echo "</tr>";
        foreach ($newPayments as $payment) {
            $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
            echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
            echo "<td>" . $payment['payment_type'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error creating records: " . $e->getMessage() . "</p>";
    }
}

// Test payment allocation
if (isset($_GET['test_payment']) && $monthlyPayments) {
    echo "<h4>Testing Payment Allocation:</h4>";
    
    // Get first unpaid month
    $stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? AND status = 'unpaid' ORDER BY month ASC LIMIT 1");
    $stmt->bind_param('i', $testBookingId);
    $stmt->execute();
    $nextUnpaid = $stmt->get_result()->fetch_assoc();
    
    if ($nextUnpaid) {
        echo "<p>Testing payment for: " . date('F Y', strtotime($nextUnpaid['month'])) . "</p>";
        
        try {
            require_once __DIR__ . '/monthly_payment_tracker.php';
            $tracker = new MonthlyPaymentTracker($conn);
            
            $result = $tracker->allocatePayment(
                $testBookingId,
                $nextUnpaid['amount'],
                date('Y-m-d H:i:s'),
                'Test Payment',
                'TEST_' . time()
            );
            
            if ($result['success']) {
                echo "<p style='color: green;'>✅ Payment allocated successfully!</p>";
                echo "<p>Message: " . $result['message'] . "</p>";
                
                // Refresh the page to show updated data
                echo "<p><a href='?booking_id=$testBookingId' class='btn btn-primary'>Refresh to See Changes</a></p>";
            } else {
                echo "<p style='color: red;'>❌ Payment failed: " . $result['message'] . "</p>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ All months are paid!</p>";
    }
} elseif ($monthlyPayments) {
    echo "<p><a href='?booking_id=$testBookingId&test_payment=1' class='btn btn-primary'>Test Payment Allocation</a></p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "<li><a href='test_simple_payment.php?booking_id=$testBookingId'>Simple Payment Test</a></li>";
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