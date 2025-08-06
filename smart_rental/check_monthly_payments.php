<?php
/**
 * Check Monthly Payments
 * Verifies that monthly rent payments are being created and updated correctly
 */

session_start();
require_once '../config/db.php';
require_once 'includes/payment_tracking_helper.php';

echo "<h2>Monthly Payments Check</h2>";

// Get booking ID from URL parameter
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;

if (!$bookingId) {
    echo "<p>Please provide a booking_id parameter: ?booking_id=X</p>";
    exit;
}

echo "<h3>Checking Booking ID: $bookingId</h3>";

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
    echo "<li>Start Date: " . $booking['start_date'] . "</li>";
    echo "<li>End Date: " . $booking['end_date'] . "</li>";
    echo "<li>Monthly Rent: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
    echo "<li>Security Deposit: KSh " . number_format($booking['security_deposit'] ?? 0, 2) . "</li>";
    echo "<li>Status: " . $booking['status'] . "</li>";
    echo "<li>Payment Status: " . $booking['payment_status'] . "</li>";
    echo "</ul>";

    // Check if monthly_rent_payments table exists
    echo "<h4>1. Table Check:</h4>";
    $tableExists = $conn->query("SHOW TABLES LIKE 'monthly_rent_payments'");
    if ($tableExists->num_rows > 0) {
        echo "<p style='color: green;'>✅ monthly_rent_payments table exists</p>";
    } else {
        echo "<p style='color: red;'>❌ monthly_rent_payments table does not exist</p>";
        exit;
    }

    // Check current monthly payments
    echo "<h4>2. Current Monthly Payments:</h4>";
    $stmt = $conn->prepare("
        SELECT * FROM monthly_rent_payments 
        WHERE booking_id = ? 
        ORDER BY month ASC
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $monthlyPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if ($monthlyPayments) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>Month</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Payment Type</th><th>First Payment</th>";
        echo "</tr>";
        foreach ($monthlyPayments as $payment) {
            $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . $payment['id'] . "</td>";
            echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
            echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
            echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
            echo "<td>" . $payment['payment_type'] . "</td>";
            echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No monthly payment records found.</p>";
    }

    // Check if we need to generate monthly payments
    echo "<h4>3. Generate Missing Monthly Payments:</h4>";
    
    // Calculate expected months
    $startDate = new DateTime($booking['start_date']);
    $endDate = new DateTime($booking['end_date']);
    $interval = $startDate->diff($endDate);
    $totalMonths = ($interval->y * 12) + $interval->m;
    
    echo "<p><strong>Total Rental Period:</strong> $totalMonths months</p>";
    
    // Generate expected months
    $currentMonth = clone $startDate;
    $currentMonth->setDate($currentMonth->format('Y'), $currentMonth->format('m'), 1);
    
    $missingMonths = [];
    for ($i = 0; $i < $totalMonths; $i++) {
        $monthStr = $currentMonth->format('Y-m-01');
        
        // Check if this month exists
        $stmt = $conn->prepare("
            SELECT COUNT(*) as month_exists 
            FROM monthly_rent_payments 
            WHERE booking_id = ? AND month = ?
        ");
        $stmt->bind_param('is', $bookingId, $monthStr);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['month_exists'] == 0) {
            $missingMonths[] = $monthStr;
        }
        
        $currentMonth->add(new DateInterval('P1M'));
    }
    
    if (!empty($missingMonths)) {
        echo "<p><strong>Missing Months:</strong></p>";
        echo "<ul>";
        foreach ($missingMonths as $month) {
            echo "<li>" . date('F Y', strtotime($month)) . "</li>";
        }
        echo "</ul>";
        
        // Generate missing months
        if (isset($_GET['generate'])) {
            echo "<p><strong>Generating missing months...</strong></p>";
            
            foreach ($missingMonths as $month) {
                $stmt = $conn->prepare("
                    INSERT INTO monthly_rent_payments 
                    (booking_id, month, amount, status, payment_type, is_first_payment, monthly_rent_amount) 
                    VALUES (?, ?, ?, 'unpaid', 'monthly_rent', 0, ?)
                ");
                $stmt->bind_param('isds', $bookingId, $month, $booking['monthly_rent'], $booking['monthly_rent']);
                
                if ($stmt->execute()) {
                    echo "<p style='color: green;'>✅ Created record for " . date('F Y', strtotime($month)) . "</p>";
                } else {
                    echo "<p style='color: red;'>❌ Failed to create record for " . date('F Y', strtotime($month)) . "</p>";
                }
            }
        } else {
            echo "<p><a href='?booking_id=$bookingId&generate=1' class='btn btn-primary'>Generate Missing Months</a></p>";
        }
    } else {
        echo "<p style='color: green;'>✅ All expected months exist in the table.</p>";
    }

    // Test payment functions
    echo "<h4>4. Test Payment Functions:</h4>";
    
    $hasFirstPayment = hasFirstPaymentBeenMade($conn, $bookingId);
    $nextUnpaidMonth = getNextUnpaidMonth($conn, $bookingId);
    
    echo "<ul>";
    echo "<li><strong>Has First Payment:</strong> " . ($hasFirstPayment ? 'Yes' : 'No') . "</li>";
    echo "<li><strong>Next Unpaid Month:</strong> " . ($nextUnpaidMonth ? date('F Y', strtotime($nextUnpaidMonth)) : 'None') . "</li>";
    echo "</ul>";

    // Check payment tracking table
    echo "<h4>5. Payment Tracking Table:</h4>";
    $stmt = $conn->prepare("
        SELECT * FROM payment_tracking 
        WHERE booking_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $paymentTracking = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if ($paymentTracking) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>Payment Type</th><th>Amount</th><th>Month</th><th>Status</th><th>Payment Date</th>";
        echo "</tr>";
        foreach ($paymentTracking as $tracking) {
            echo "<tr>";
            echo "<td>" . $tracking['id'] . "</td>";
            echo "<td>" . $tracking['payment_type'] . "</td>";
            echo "<td>KSh " . number_format($tracking['amount'], 2) . "</td>";
            echo "<td>" . ($tracking['month'] ? date('F Y', strtotime($tracking['month'])) : '-') . "</td>";
            echo "<td>" . $tracking['status'] . "</td>";
            echo "<td>" . ($tracking['payment_date'] ? date('M d, Y', strtotime($tracking['payment_date'])) : '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No payment tracking records found.</p>";
    }

    echo "<h4>6. Test Links:</h4>";
    echo "<ul>";
    echo "<li><a href='test_simple_payment.php?booking_id=$bookingId'>Test Simple Payment</a></li>";
    echo "<li><a href='debug_month_detection.php?booking_id=$bookingId'>Debug Month Detection</a></li>";
    echo "<li><a href='booking_payment.php?id=$bookingId'>Go to Payment Page</a></li>";
    echo "<li><a href='fix_payment_sync.php?booking_id=$bookingId'>Fix Payment Sync</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 