<?php
/**
 * Landlord Property Price Cascade Test
 */

require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();
require_once 'update_property_price_cascade.php';

echo "<h2>Landlord Property Price Cascade Test</h2>";

echo "<p style='color: green;'>✅ Landlord logged in (ID: " . $_SESSION['user_id'] . ")</p>";

$cascade = new PropertyPriceCascade($conn);

// Get landlord's properties
$properties = $conn->query("
    SELECT h.*, 
           COUNT(rb.id) as active_bookings,
           COUNT(mrp.id) as unpaid_monthly_payments
    FROM houses h
    LEFT JOIN rental_bookings rb ON h.id = rb.house_id AND rb.status IN ('pending', 'confirmed', 'active')
    LEFT JOIN monthly_rent_payments mrp ON rb.id = mrp.booking_id AND mrp.status = 'unpaid'
    WHERE h.landlord_id = " . $_SESSION['user_id'] . "
    GROUP BY h.id
    ORDER BY h.id DESC
")->fetch_all(MYSQLI_ASSOC);

echo "<h3>Your Properties:</h3>";
if (!empty($properties)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Property</th><th>Current Price</th><th>Active Bookings</th><th>Unpaid Payments</th><th>Actions</th>";
    echo "</tr>";
    
    foreach ($properties as $property) {
        echo "<tr>";
        echo "<td>" . $property['id'] . "</td>";
        echo "<td>" . htmlspecialchars($property['house_no']) . "</td>";
        echo "<td>KSh " . number_format($property['price'], 2) . "</td>";
        echo "<td>" . $property['active_bookings'] . "</td>";
        echo "<td>" . $property['unpaid_monthly_payments'] . "</td>";
        echo "<td>";
        echo "<a href='?test_property=" . $property['id'] . "' class='btn btn-sm btn-primary'>Test Price Change</a> ";
        echo "<a href='?view_bookings=" . $property['id'] . "' class='btn btn-sm btn-info'>View Bookings</a>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No properties found for this landlord.</p>";
}

// Test specific property
if (isset($_GET['test_property'])) {
    $propertyId = (int)$_GET['test_property'];
    
    // Verify landlord owns this property
    $stmt = $conn->prepare("SELECT id FROM houses WHERE id = ? AND landlord_id = ?");
    $stmt->bind_param('ii', $propertyId, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo "<p style='color: red;'>❌ Property not found or you don't have permission to edit it.</p>";
        exit;
    }
    
    $property = $cascade->getPropertyDetails($propertyId);
    
    if ($property) {
        echo "<h3>Testing Property: " . htmlspecialchars($property['house_no']) . "</h3>";
        echo "<ul>";
        echo "<li><strong>Current Price:</strong> KSh " . number_format($property['price'], 2) . "</li>";
        echo "<li><strong>Active Bookings:</strong> " . $property['active_bookings'] . "</li>";
        echo "<li><strong>Unpaid Monthly Payments:</strong> " . $property['unpaid_monthly_payments'] . "</li>";
        echo "</ul>";
        
        // Test price change
        $newPrice = $property['price'] + 1000; // Add 1000 to current price
        echo "<p><strong>Test Price Change:</strong> KSh " . number_format($property['price'], 2) . " → KSh " . number_format($newPrice, 2) . "</p>";
        
        if (isset($_GET['execute_test'])) {
            $result = $cascade->updatePropertyPrice($propertyId, $newPrice, $property['price']);
            
            echo "<h4>Test Result:</h4>";
            if ($result['success']) {
                echo "<div style='color: green;'>✅ " . $result['message'] . "</div>";
                echo "<ul>";
                echo "<li>Affected Bookings: " . $result['affected_bookings'] . "</li>";
                echo "<li>Affected Monthly Payments: " . $result['affected_monthly_payments'] . "</li>";
                echo "</ul>";
                
                // Show updated property details
                $updatedProperty = $cascade->getPropertyDetails($propertyId);
                echo "<h4>Updated Property Details:</h4>";
                echo "<ul>";
                echo "<li><strong>New Price:</strong> KSh " . number_format($updatedProperty['price'], 2) . "</li>";
                echo "<li><strong>Active Bookings:</strong> " . $updatedProperty['active_bookings'] . "</li>";
                echo "<li><strong>Unpaid Monthly Payments:</strong> " . $updatedProperty['unpaid_monthly_payments'] . "</li>";
                echo "</ul>";
            } else {
                echo "<div style='color: red;'>❌ " . $result['message'] . "</div>";
            }
        } else {
            echo "<p><a href='?test_property=$propertyId&execute_test=1' class='btn btn-warning'>Execute Test Price Change</a></p>";
        }
        
        // Show affected bookings
        $affectedBookings = $cascade->getAffectedBookings($propertyId);
        if (!empty($affectedBookings)) {
            echo "<h4>Affected Bookings:</h4>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Booking ID</th><th>Tenant</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Monthly Rent</th></tr>";
            
            foreach ($affectedBookings as $booking) {
                echo "<tr>";
                echo "<td>" . $booking['id'] . "</td>";
                echo "<td>" . htmlspecialchars($booking['firstname'] . ' ' . $booking['lastname']) . "</td>";
                echo "<td>" . $booking['start_date'] . "</td>";
                echo "<td>" . $booking['end_date'] . "</td>";
                echo "<td>" . ucfirst($booking['status']) . "</td>";
                echo "<td>KSh " . number_format($booking['monthly_rent'], 2) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'>Property not found!</p>";
    }
}

// View bookings for a property
if (isset($_GET['view_bookings'])) {
    $propertyId = (int)$_GET['view_bookings'];
    
    // Verify landlord owns this property
    $stmt = $conn->prepare("SELECT id FROM houses WHERE id = ? AND landlord_id = ?");
    $stmt->bind_param('ii', $propertyId, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo "<p style='color: red;'>❌ Property not found or you don't have permission to view it.</p>";
        exit;
    }
    
    echo "<h3>Bookings for Property ID: $propertyId</h3>";
    
    $bookings = $conn->query("
        SELECT rb.*, u.firstname, u.lastname, u.email
        FROM rental_bookings rb
        JOIN users u ON rb.user_id = u.id
        WHERE rb.house_id = $propertyId
        ORDER BY rb.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($bookings)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Booking ID</th><th>Tenant</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Monthly Rent</th><th>Security Deposit</th></tr>";
        
        foreach ($bookings as $booking) {
            echo "<tr>";
            echo "<td>" . $booking['id'] . "</td>";
            echo "<td>" . htmlspecialchars($booking['firstname'] . ' ' . $booking['lastname']) . "</td>";
            echo "<td>" . $booking['start_date'] . "</td>";
            echo "<td>" . $booking['end_date'] . "</td>";
            echo "<td>" . ucfirst($booking['status']) . "</td>";
            echo "<td>KSh " . number_format($booking['monthly_rent'], 2) . "</td>";
            echo "<td>KSh " . number_format($booking['security_deposit'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No bookings found for this property.</p>";
    }
}

echo "<h3>Navigation:</h3>";
echo "<ul>";
echo "<li><a href='properties.php'>Back to Properties</a></li>";
echo "<li><a href='index.php'>Landlord Dashboard</a></li>";
echo "</ul>";
?> 