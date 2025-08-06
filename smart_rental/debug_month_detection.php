<?php
/**
 * Debug Month Detection
 * Tests the month detection logic for payments
 */

session_start();
require_once '../config/db.php';
require_once 'includes/payment_tracking_helper.php';

echo "<h2>Month Detection Debug</h2>";

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
    echo "<li>Start Date: " . $booking['start_date'] . "</li>";
    echo "<li>First Month: " . date('F Y', strtotime($booking['start_date'])) . "</li>";
    echo "<li>Monthly Rent: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
    echo "</ul>";

    // Test month detection
    echo "<h4>Month Detection Test:</h4>";
    
    $nextUnpaidMonth = getNextUnpaidMonth($conn, $bookingId);
    echo "<p><strong>Next Unpaid Month:</strong> " . ($nextUnpaidMonth ? date('F Y', strtotime($nextUnpaidMonth)) : 'None') . "</p>";
    
    $hasFirstPayment = hasFirstPaymentBeenMade($conn, $bookingId);
    echo "<p><strong>Has First Payment:</strong> " . ($hasFirstPayment ? 'Yes' : 'No') . "</p>";

    // Show all payment records
    echo "<h4>All Payment Records:</h4>";
    $stmt = $conn->prepare("
        SELECT month, status, payment_date, amount, is_first_payment, payment_type
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
        echo "<th>Month</th><th>Status</th><th>Payment Date</th><th>Amount</th><th>First Payment</th><th>Type</th>";
        echo "</tr>";
        foreach ($payments as $payment) {
            $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
            echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
            echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
            echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . $payment['payment_type'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No payment records found.</p>";
    }

    // Test payment flow logic
    echo "<h4>Payment Flow Logic:</h4>";
    
    if (!$hasFirstPayment) {
        echo "<p>🔵 <strong>Initial Payment Required</strong></p>";
        echo "<ul>";
        echo "<li>Type: Initial Payment (Security Deposit + First Month Rent)</li>";
        echo "<li>Month: " . date('F Y', strtotime($booking['start_date'])) . "</li>";
        echo "<li>Amount: KSh " . number_format($booking['monthly_rent'] + ($booking['security_deposit'] ?? 0), 2) . "</li>";
        echo "</ul>";
    } else {
        echo "<p>🟢 <strong>Pre-Payment Available</strong></p>";
        echo "<ul>";
        echo "<li>Type: Pre-Payment for " . date('F Y', strtotime($nextUnpaidMonth)) . "</li>";
        echo "<li>Month: " . date('F Y', strtotime($nextUnpaidMonth)) . "</li>";
        echo "<li>Amount: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
        echo "</ul>";
    }

    // Test creating missing months
    echo "<h4>Month Generation Test:</h4>";
    
    // Get booking end date to calculate total months
    $startDate = new DateTime($booking['start_date']);
    $endDate = new DateTime($booking['end_date'] ?? date('Y-m-d', strtotime($booking['start_date'] . ' +12 months')));
    $interval = $startDate->diff($endDate);
    $totalMonths = ($interval->y * 12) + $interval->m;
    
    echo "<p><strong>Total Rental Period:</strong> $totalMonths months</p>";
    
    // Generate all months for the rental period
    $currentMonth = clone $startDate;
    $currentMonth->setDate($currentMonth->format('Y'), $currentMonth->format('m'), 1);
    
    echo "<p><strong>Expected Months:</strong></p>";
    echo "<ul>";
    for ($i = 0; $i < $totalMonths; $i++) {
        $monthStr = $currentMonth->format('Y-m-01');
        $monthName = $currentMonth->format('F Y');
        
        // Check if this month exists in payments
        $stmt = $conn->prepare("
            SELECT COUNT(*) as month_exists, status 
            FROM monthly_rent_payments 
            WHERE booking_id = ? AND month = ?
        ");
        $stmt->bind_param('is', $bookingId, $monthStr);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $status = $result['month_exists'] > 0 ? $result['status'] : 'missing';
        $statusColor = $status === 'paid' ? 'green' : ($status === 'unpaid' ? 'orange' : 'red');
        
        echo "<li style='color: $statusColor;'>$monthName - $status</li>";
        
        $currentMonth->add(new DateInterval('P1M'));
    }
    echo "</ul>";

    echo "<h4>Test Links:</h4>";
    echo "<ul>";
    echo "<li><a href='booking_payment.php?id=$bookingId'>Go to Payment Page</a></li>";
    echo "<li><a href='test_payment_flow.php?booking_id=$bookingId'>Test Payment Flow</a></li>";
    echo "<li><a href='booking_details.php?id=$bookingId'>View Booking Details</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 