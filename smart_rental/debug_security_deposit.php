<?php
require_once '../config/db.php';

echo "<h2>Debug Security Deposit Issue</h2>";

try {
    // Check booking ID 3 specifically
    $bookingId = 3;
    
    echo "<h3>Checking Booking ID: $bookingId</h3>";
    
    // Check rental_bookings table structure
    echo "<h4>1. Rental Bookings Table Structure:</h4>";
    $stmt = $conn->prepare("DESCRIBE rental_bookings");
    $stmt->execute();
    $columns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $hasSecurityDeposit = false;
    foreach ($columns as $column) {
        echo "<p>" . $column['Field'] . " - " . $column['Type'] . " - " . $column['Null'] . " - " . $column['Default'] . "</p>";
        if ($column['Field'] === 'security_deposit') {
            $hasSecurityDeposit = true;
        }
    }
    
    if (!$hasSecurityDeposit) {
        echo "<p style='color: red;'>❌ security_deposit column NOT found in rental_bookings table!</p>";
    } else {
        echo "<p style='color: green;'>✅ security_deposit column found in rental_bookings table</p>";
    }
    
    // Check the specific booking data
    echo "<h4>2. Booking Data for ID $bookingId:</h4>";
    $stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if ($booking) {
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h5>Raw Booking Data:</h5>";
        foreach ($booking as $key => $value) {
            echo "<p><strong>$key:</strong> " . ($value === null ? 'NULL' : $value) . "</p>";
        }
        echo "</div>";
    } else {
        echo "<p style='color: red;'>❌ Booking ID $bookingId not found!</p>";
    }
    
    // Check the property data
    if ($booking) {
        echo "<h4>3. Property Data:</h4>";
        $stmt = $conn->prepare("SELECT * FROM houses WHERE id = ?");
        $stmt->bind_param('i', $booking['house_id']);
        $stmt->execute();
        $property = $stmt->get_result()->fetch_assoc();
        
        if ($property) {
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
            echo "<h5>Property Data:</h5>";
            echo "<p><strong>Property ID:</strong> " . $property['id'] . "</p>";
            echo "<p><strong>House No:</strong> " . $property['house_no'] . "</p>";
            echo "<p><strong>Price:</strong> KSh " . number_format($property['price'], 2) . "</p>";
            echo "<p><strong>Security Deposit:</strong> " . ($property['security_deposit'] ? 'KSh ' . number_format($property['security_deposit'], 2) : 'NULL') . "</p>";
            echo "</div>";
        }
    }
    
    // Check what the BookingController query returns
    echo "<h4>4. BookingController Query Result:</h4>";
    $stmt = $conn->prepare("
        SELECT 
            b.*, 
            h.house_no,
            h.house_no as property_name,
            h.price as property_price,
            h.location as property_location,
            h.main_image,
            h.bedrooms,
            h.bathrooms,
            h.description,
            u.name as tenant_name,
            u.username as tenant_email,
            u.phone_number as tenant_phone,
            l.name as landlord_name,
            l.username as landlord_email,
            l.phone_number as landlord_phone
        FROM rental_bookings b
        JOIN houses h ON b.house_id = h.id
        JOIN users u ON b.user_id = u.id
        LEFT JOIN users l ON h.landlord_id = l.id
        WHERE b.id = ?
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h5>BookingController Query Result:</h5>";
        echo "<p><strong>security_deposit:</strong> " . ($result['security_deposit'] === null ? 'NULL' : $result['security_deposit']) . "</p>";
        echo "<p><strong>property_price:</strong> " . $result['property_price'] . "</p>";
        echo "<p><strong>Expected Security Deposit:</strong> " . ($result['security_deposit'] ?? $result['property_price']) . "</p>";
        echo "</div>";
    }
    
    // Check if we need to update existing bookings
    echo "<h4>5. Check All Bookings:</h4>";
    $stmt = $conn->prepare("SELECT id, house_id, security_deposit FROM rental_bookings LIMIT 10");
    $stmt->execute();
    $allBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<p>Found " . count($allBookings) . " bookings:</p>";
    foreach ($allBookings as $booking) {
        echo "<p>Booking ID: " . $booking['id'] . " - Security Deposit: " . ($booking['security_deposit'] === null ? 'NULL' : $booking['security_deposit']) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
?> 