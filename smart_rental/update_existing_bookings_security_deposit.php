<?php
require_once '../config/db.php';

echo "<h2>Updating Existing Bookings Security Deposit</h2>";

try {
    // Check current state
    echo "<h3>Current State:</h3>";
    $stmt = $conn->prepare("
        SELECT rb.id, rb.house_id, rb.security_deposit, h.price, h.house_no
        FROM rental_bookings rb
        JOIN houses h ON rb.house_id = h.id
        WHERE rb.security_deposit IS NULL OR rb.security_deposit = 0
    ");
    $stmt->execute();
    $bookingsToUpdate = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<p>Found " . count($bookingsToUpdate) . " bookings that need security deposit update:</p>";
    
    if ($bookingsToUpdate) {
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        foreach ($bookingsToUpdate as $booking) {
            echo "<p>Booking ID: " . $booking['id'] . " - " . $booking['house_no'] . " - Current: " . ($booking['security_deposit'] ?? 'NULL') . " - Will set to: " . $booking['price'] . "</p>";
        }
        echo "</div>";
        
        // Update the bookings
        echo "<h3>Updating Bookings...</h3>";
        $updateStmt = $conn->prepare("
            UPDATE rental_bookings rb
            JOIN houses h ON rb.house_id = h.id
            SET rb.security_deposit = h.price
            WHERE rb.security_deposit IS NULL OR rb.security_deposit = 0
        ");
        
        if ($updateStmt->execute()) {
            $affectedRows = $conn->affected_rows;
            echo "<p style='color: green;'>✅ Successfully updated $affectedRows bookings!</p>";
        } else {
            echo "<p style='color: red;'>❌ Error updating bookings: " . $conn->error . "</p>";
        }
        
        // Verify the update
        echo "<h3>Verification:</h3>";
        $verifyStmt = $conn->prepare("
            SELECT rb.id, rb.house_id, rb.security_deposit, h.price, h.house_no
            FROM rental_bookings rb
            JOIN houses h ON rb.house_id = h.id
            ORDER BY rb.id DESC
            LIMIT 10
        ");
        $verifyStmt->execute();
        $updatedBookings = $verifyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h5>Updated Bookings:</h5>";
        foreach ($updatedBookings as $booking) {
            echo "<p>Booking ID: " . $booking['id'] . " - " . $booking['house_no'] . " - Security Deposit: KSh " . number_format($booking['security_deposit'], 2) . "</p>";
        }
        echo "</div>";
        
    } else {
        echo "<p style='color: green;'>✅ All bookings already have proper security deposit values!</p>";
    }
    
    // Check booking ID 3 specifically
    echo "<h3>Checking Booking ID 3:</h3>";
    $stmt = $conn->prepare("
        SELECT rb.id, rb.security_deposit, h.price, h.house_no
        FROM rental_bookings rb
        JOIN houses h ON rb.house_id = h.id
        WHERE rb.id = 3
    ");
    $stmt->execute();
    $booking3 = $stmt->get_result()->fetch_assoc();
    
    if ($booking3) {
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h5>Booking ID 3 Details:</h5>";
        echo "<p><strong>House:</strong> " . $booking3['house_no'] . "</p>";
        echo "<p><strong>Monthly Price:</strong> KSh " . number_format($booking3['price'], 2) . "</p>";
        echo "<p><strong>Security Deposit:</strong> KSh " . number_format($booking3['security_deposit'], 2) . "</p>";
        echo "<p><strong>Initial Payment Required:</strong> KSh " . number_format($booking3['price'] + $booking3['security_deposit'], 2) . "</p>";
        echo "</div>";
    } else {
        echo "<p style='color: red;'>❌ Booking ID 3 not found!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
?> 