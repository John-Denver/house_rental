<?php
/**
 * Test script to check monthly records creation
 */

require_once '../config/db.php';
require_once 'monthly_payment_tracker.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Monthly Records Test</h2>";

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
        SELECT id, monthly_rent, security_deposit, start_date, end_date, status, payment_status
        FROM rental_bookings 
        WHERE id = ?
    ");
    $bookingStmt->bind_param('i', $testBookingId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc();
    
    if ($booking) {
        echo "<h4>Booking Details:</h4>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> " . $booking['id'] . "</li>";
        echo "<li><strong>Monthly Rent:</strong> KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
        echo "<li><strong>Security Deposit:</strong> KSh " . number_format($booking['security_deposit'], 2) . "</li>";
        echo "<li><strong>Start Date:</strong> " . $booking['start_date'] . "</li>";
        echo "<li><strong>End Date:</strong> " . $booking['end_date'] . "</li>";
        echo "<li><strong>Status:</strong> " . $booking['status'] . "</li>";
        echo "<li><strong>Payment Status:</strong> " . $booking['payment_status'] . "</li>";
        echo "</ul>";
        
        // Check if monthly records exist
        echo "<h4>Checking Monthly Records:</h4>";
        
        $monthlyRecordsStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM monthly_rent_payments 
            WHERE booking_id = ?
        ");
        $monthlyRecordsStmt->bind_param('i', $testBookingId);
        $monthlyRecordsStmt->execute();
        $recordCount = $monthlyRecordsStmt->get_result()->fetch_assoc()['count'];
        
        echo "<p><strong>Monthly records count:</strong> $recordCount</p>";
        
        if ($recordCount == 0) {
            echo "<p style='color: orange;'>⚠️ No monthly records found. This might be the issue!</p>";
            
            if (isset($_GET['create_records'])) {
                echo "<h4>Creating Monthly Records:</h4>";
                
                // Call getMonthlyPayments which should create the records
                $payments = $tracker->getMonthlyPayments($testBookingId);
                
                echo "<p style='color: green;'>✅ Created " . count($payments) . " monthly records</p>";
                
                // Show the created records
                echo "<h4>Created Monthly Records:</h4>";
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>Month</th><th>Amount</th><th>Status</th><th>Type</th><th>Is First Payment</th></tr>";
                
                foreach ($payments as $payment) {
                    echo "<tr>";
                    echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
                    echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
                    echo "<td>" . $payment['status'] . "</td>";
                    echo "<td>" . $payment['payment_type'] . "</td>";
                    echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p><a href='?booking_id=$testBookingId&create_records=1' class='btn btn-primary'>Create Monthly Records</a></p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Monthly records exist</p>";
            
            // Show existing records
            echo "<h4>Existing Monthly Records:</h4>";
            $existingRecordsStmt = $conn->prepare("
                SELECT month, amount, status, payment_type, is_first_payment 
                FROM monthly_rent_payments 
                WHERE booking_id = ?
                ORDER BY month ASC
            ");
            $existingRecordsStmt->bind_param('i', $testBookingId);
            $existingRecordsStmt->execute();
            $existingRecords = $existingRecordsStmt->get_result();
            
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Month</th><th>Amount</th><th>Status</th><th>Type</th><th>Is First Payment</th></tr>";
            
            while ($record = $existingRecords->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . date('F Y', strtotime($record['month'])) . "</td>";
                echo "<td>KSh " . number_format($record['amount'], 2) . "</td>";
                echo "<td>" . $record['status'] . "</td>";
                echo "<td>" . $record['payment_type'] . "</td>";
                echo "<td>" . ($record['is_first_payment'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Test next unpaid month
        echo "<h4>Testing Next Unpaid Month:</h4>";
        $nextUnpaidMonth = $tracker->getNextUnpaidMonth($testBookingId);
        
        if ($nextUnpaidMonth) {
            echo "<p><strong>Next unpaid month:</strong> " . date('F Y', strtotime($nextUnpaidMonth)) . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ No unpaid months found</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Booking not found</p>";
    }
    
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