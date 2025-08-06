<?php
/**
 * Check Booking Details
 * Verify the booking details and monthly rent amount
 */

session_start();
require_once '../config/db.php';

echo "<h2>Check Booking Details</h2>";

$bookingId = 29; // Your test booking ID

echo "<h3>Booking ID: $bookingId</h3>";

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
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Field</th><th>Value</th>";
    echo "</tr>";
    foreach ($booking as $field => $value) {
        echo "<tr>";
        echo "<td><strong>$field</strong></td>";
        echo "<td>" . htmlspecialchars($value) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Get house details
    $stmt = $conn->prepare("SELECT * FROM houses WHERE id = ?");
    $stmt->bind_param('i', $booking['house_id']);
    $stmt->execute();
    $house = $stmt->get_result()->fetch_assoc();

    if ($house) {
        echo "<h4>2. House Details:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Field</th><th>Value</th>";
        echo "</tr>";
        foreach ($house as $field => $value) {
            echo "<tr>";
            echo "<td><strong>$field</strong></td>";
            echo "<td>" . htmlspecialchars($value) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ House not found</p>";
    }

    // Check if monthly rent should be updated
    echo "<h4>3. Monthly Rent Analysis:</h4>";
    echo "<ul>";
    echo "<li><strong>Booking Monthly Rent:</strong> KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
    if ($house) {
        echo "<li><strong>House Price:</strong> KSh " . number_format($house['price'], 2) . "</li>";
        
        if ($booking['monthly_rent'] != $house['price']) {
            echo "<li style='color: red;'><strong>⚠️ MISMATCH:</strong> Booking monthly rent doesn't match house price!</li>";
            
            // Offer to fix it
            if (isset($_GET['fix_rent'])) {
                $updateStmt = $conn->prepare("UPDATE rental_bookings SET monthly_rent = ? WHERE id = ?");
                $updateStmt->bind_param('di', $house['price'], $bookingId);
                if ($updateStmt->execute()) {
                    echo "<li style='color: green;'>✅ Fixed: Updated monthly rent to KSh " . number_format($house['price'], 2) . "</li>";
                } else {
                    echo "<li style='color: red;'>❌ Error updating monthly rent: " . $updateStmt->error . "</li>";
                }
            } else {
                echo "<li><a href='?fix_rent=1' class='btn btn-warning'>Fix Monthly Rent</a></li>";
            }
        } else {
            echo "<li style='color: green;'>✅ Monthly rent matches house price</li>";
        }
    }
    echo "</ul>";

    // Check monthly payments
    echo "<h4>4. Monthly Payments:</h4>";
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
            echo "<tr>";
            echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
            echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
            echo "<td>" . $payment['payment_type'] . "</td>";
            echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No monthly payments found</p>";
    }

    echo "<h4>5. Test Links:</h4>";
    echo "<ul>";
    echo "<li><a href='debug_payment_type.php?booking_id=$bookingId&type=prepayment'>Debug Payment Type</a></li>";
    echo "<li><a href='test_payment_form.php'>Test Payment Form</a></li>";
    echo "<li><a href='booking_payment.php?id=$bookingId&type=prepayment'>Go to Payment Page</a></li>";
    echo "<li><a href='check_monthly_payments.php?booking_id=$bookingId'>Check Monthly Payments</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 