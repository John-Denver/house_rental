<?php
/**
 * Test Payment Insert
 * Tests if payment inserts are working correctly
 */

session_start();
require_once '../config/db.php';
require_once 'includes/payment_tracking_helper.php';

echo "<h2>Test Payment Insert</h2>";

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

    echo "<h4>Booking Details:</h4>";
    echo "<ul>";
    echo "<li>Monthly Rent: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
    echo "<li>Security Deposit: KSh " . number_format($booking['security_deposit'] ?? 0, 2) . "</li>";
    echo "<li>Status: " . $booking['status'] . "</li>";
    echo "</ul>";

    // Check if tables exist
    echo "<h4>1. Table Existence Check:</h4>";
    
    $tableExists = $conn->query("SHOW TABLES LIKE 'monthly_rent_payments'");
    echo "<p>monthly_rent_payments table: " . ($tableExists->num_rows > 0 ? "✅ Exists" : "❌ Missing") . "</p>";
    
    $tableExists = $conn->query("SHOW TABLES LIKE 'payment_tracking'");
    echo "<p>payment_tracking table: " . ($tableExists->num_rows > 0 ? "✅ Exists" : "❌ Missing") . "</p>";

    // Test initial payment insert
    echo "<h4>2. Test Initial Payment Insert:</h4>";
    
    if (isset($_GET['test_initial'])) {
        echo "<p><strong>Testing initial payment insert...</strong></p>";
        
        try {
            $result = recordInitialPayment(
                $conn,
                $bookingId,
                $booking['monthly_rent'] + ($booking['security_deposit'] ?? 0),
                $booking['security_deposit'] ?? 0,
                $booking['monthly_rent'],
                'manual',
                'TEST-' . time(),
                null,
                'Test initial payment'
            );
            
            if ($result) {
                echo "<p style='color: green;'>✅ Initial payment recorded successfully!</p>";
            } else {
                echo "<p style='color: red;'>❌ Initial payment failed!</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p><a href='?booking_id=$bookingId&test_initial=1' class='btn btn-success'>Test Initial Payment Insert</a></p>";
    }

    // Test pre-payment insert
    echo "<h4>3. Test Pre-Payment Insert:</h4>";
    
    if (isset($_GET['test_prepayment'])) {
        echo "<p><strong>Testing pre-payment insert...</strong></p>";
        
        try {
            $result = recordPrePayment(
                $conn,
                $bookingId,
                $booking['monthly_rent'],
                'manual',
                'TEST-' . time(),
                null,
                'Test pre-payment'
            );
            
            if ($result['success']) {
                echo "<p style='color: green;'>✅ Pre-payment recorded successfully for " . date('F Y', strtotime($result['month_paid'])) . "!</p>";
            } else {
                echo "<p style='color: red;'>❌ Pre-payment failed!</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p><a href='?booking_id=$bookingId&test_prepayment=1' class='btn btn-primary'>Test Pre-Payment Insert</a></p>";
    }

    // Check current records
    echo "<h4>4. Current Records:</h4>";
    
    // Check monthly_rent_payments
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM monthly_rent_payments WHERE booking_id = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $monthlyCount = $stmt->get_result()->fetch_assoc()['count'];
    echo "<p>monthly_rent_payments records: $monthlyCount</p>";
    
    // Check payment_tracking
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payment_tracking WHERE booking_id = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $trackingCount = $stmt->get_result()->fetch_assoc()['count'];
    echo "<p>payment_tracking records: $trackingCount</p>";

    // Show recent records
    if ($monthlyCount > 0) {
        echo "<h5>Recent Monthly Payments:</h5>";
        $stmt = $conn->prepare("
            SELECT month, status, amount, payment_type, is_first_payment 
            FROM monthly_rent_payments 
            WHERE booking_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $recentPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Month</th><th>Status</th><th>Amount</th><th>Type</th><th>First Payment</th>";
        echo "</tr>";
        foreach ($recentPayments as $payment) {
            echo "<tr>";
            echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
            echo "<td>" . $payment['status'] . "</td>";
            echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
            echo "<td>" . $payment['payment_type'] . "</td>";
            echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    if ($trackingCount > 0) {
        echo "<h5>Recent Payment Tracking:</h5>";
        $stmt = $conn->prepare("
            SELECT payment_type, amount, month, status, payment_date 
            FROM payment_tracking 
            WHERE booking_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $recentTracking = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Type</th><th>Amount</th><th>Month</th><th>Status</th><th>Date</th>";
        echo "</tr>";
        foreach ($recentTracking as $tracking) {
            echo "<tr>";
            echo "<td>" . $tracking['payment_type'] . "</td>";
            echo "<td>KSh " . number_format($tracking['amount'], 2) . "</td>";
            echo "<td>" . ($tracking['month'] ? date('F Y', strtotime($tracking['month'])) : '-') . "</td>";
            echo "<td>" . $tracking['status'] . "</td>";
            echo "<td>" . ($tracking['payment_date'] ? date('M d, Y', strtotime($tracking['payment_date'])) : '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<h4>5. Refresh and Check:</h4>";
    echo "<p><a href='?booking_id=$bookingId' class='btn btn-info'>Refresh This Page</a></p>";
    echo "<p><a href='check_monthly_payments.php?booking_id=$bookingId'>Check Monthly Payments</a></p>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 