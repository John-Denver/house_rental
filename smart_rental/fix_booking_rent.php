<?php
/**
 * Fix Booking Monthly Rent
 * Updates existing bookings with zero monthly rent to use the house price
 */

session_start();
require_once '../config/db.php';

echo "<h2>Fix Booking Monthly Rent</h2>";

$bookingId = 29; // Your test booking ID

echo "<h3>Fixing Booking ID: $bookingId</h3>";

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

    echo "<h4>1. Current Booking Details:</h4>";
    echo "<ul>";
    echo "<li><strong>Monthly Rent:</strong> KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
    echo "<li><strong>House ID:</strong> " . $booking['house_id'] . "</li>";
    echo "<li><strong>Status:</strong> " . $booking['status'] . "</li>";
    echo "</ul>";

    // Get house details
    $stmt = $conn->prepare("SELECT * FROM houses WHERE id = ?");
    $stmt->bind_param('i', $booking['house_id']);
    $stmt->execute();
    $house = $stmt->get_result()->fetch_assoc();

    if (!$house) {
        echo "<p>❌ House not found</p>";
        exit;
    }

    echo "<h4>2. House Details:</h4>";
    echo "<ul>";
    echo "<li><strong>House Price:</strong> KSh " . number_format($house['price'], 2) . "</li>";
    echo "<li><strong>Security Deposit:</strong> KSh " . number_format($house['security_deposit'], 2) . "</li>";
    echo "</ul>";

    // Check if monthly rent needs to be fixed
    if ($booking['monthly_rent'] == 0 || $booking['monthly_rent'] != $house['price']) {
        echo "<h4>3. Fix Required:</h4>";
        echo "<p style='color: red;'>⚠️ Monthly rent is " . number_format($booking['monthly_rent'], 2) . " but should be " . number_format($house['price'], 2) . "</p>";
        
        if (isset($_GET['fix'])) {
            // Update the booking
            $updateStmt = $conn->prepare("UPDATE rental_bookings SET monthly_rent = ? WHERE id = ?");
            $updateStmt->bind_param('di', $house['price'], $bookingId);
            
            if ($updateStmt->execute()) {
                echo "<p style='color: green;'>✅ Fixed: Updated monthly rent to KSh " . number_format($house['price'], 2) . "</p>";
                
                // Also update the monthly_rent_payments table
                $updatePaymentsStmt = $conn->prepare("UPDATE monthly_rent_payments SET amount = ? WHERE booking_id = ?");
                $updatePaymentsStmt->bind_param('di', $house['price'], $bookingId);
                
                if ($updatePaymentsStmt->execute()) {
                    echo "<p style='color: green;'>✅ Updated monthly_rent_payments table</p>";
                } else {
                    echo "<p style='color: orange;'>⚠️ Warning: Could not update monthly_rent_payments table</p>";
                }
                
                // Verify the fix
                $verifyStmt = $conn->prepare("SELECT monthly_rent FROM rental_bookings WHERE id = ?");
                $verifyStmt->bind_param('i', $bookingId);
                $verifyStmt->execute();
                $updatedBooking = $verifyStmt->get_result()->fetch_assoc();
                
                echo "<p><strong>Verification:</strong> Monthly rent is now KSh " . number_format($updatedBooking['monthly_rent'], 2) . "</p>";
                
            } else {
                echo "<p style='color: red;'>❌ Error updating monthly rent: " . $updateStmt->error . "</p>";
            }
        } else {
            echo "<p><a href='?fix=1' class='btn btn-warning'>Fix Monthly Rent</a></p>";
        }
    } else {
        echo "<h4>3. No Fix Needed:</h4>";
        echo "<p style='color: green;'>✅ Monthly rent is already correct</p>";
    }

    echo "<h4>4. Test Links:</h4>";
    echo "<ul>";
    echo "<li><a href='booking_payment.php?id=$bookingId&type=prepayment'>Go to Payment Page</a></li>";
    echo "<li><a href='debug_payment_type.php?booking_id=$bookingId&type=prepayment'>Debug Payment Type</a></li>";
    echo "<li><a href='check_monthly_payments.php?booking_id=$bookingId'>Check Monthly Payments</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 