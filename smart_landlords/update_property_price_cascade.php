<?php
/**
 * Property Price Cascade Update Utility
 * Updates all related tables when a property's price is changed
 */

require_once '../config/db.php';

class PropertyPriceCascade {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Update property price and cascade to all related tables
     */
    public function updatePropertyPrice($propertyId, $newPrice, $oldPrice = null) {
        try {
            // Start transaction
            $this->conn->begin_transaction();
            
            // 1. Update houses table
            $stmt = $this->conn->prepare("UPDATE houses SET price = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('di', $newPrice, $propertyId);
            $stmt->execute();
            
            // 2. Update rental_bookings table - update monthly_rent for active bookings
            $stmt = $this->conn->prepare("
                UPDATE rental_bookings 
                SET monthly_rent = ?, updated_at = NOW() 
                WHERE house_id = ? AND status IN ('pending', 'confirmed', 'active')
            ");
            $stmt->bind_param('di', $newPrice, $propertyId);
            $stmt->execute();
            
            // 3. Update monthly_rent_payments table for active bookings
            $stmt = $this->conn->prepare("
                UPDATE monthly_rent_payments mrp
                JOIN rental_bookings rb ON mrp.booking_id = rb.id
                SET mrp.amount = ?, mrp.updated_at = NOW()
                WHERE rb.house_id = ? 
                AND rb.status IN ('pending', 'confirmed', 'active')
                AND mrp.status = 'unpaid'
            ");
            $stmt->bind_param('di', $newPrice, $propertyId);
            $stmt->execute();
            
            // 4. Log the price change
            $this->logPriceChange($propertyId, $oldPrice, $newPrice);
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Property price updated successfully and cascaded to related tables',
                'affected_bookings' => $this->getAffectedBookingsCount($propertyId),
                'affected_monthly_payments' => $this->getAffectedMonthlyPaymentsCount($propertyId)
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Error updating property price: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get count of affected bookings
     */
    private function getAffectedBookingsCount($propertyId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM rental_bookings 
            WHERE house_id = ? AND status IN ('pending', 'confirmed', 'active')
        ");
        $stmt->bind_param('i', $propertyId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'];
    }
    
    /**
     * Get count of affected monthly payments
     */
    private function getAffectedMonthlyPaymentsCount($propertyId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM monthly_rent_payments mrp
            JOIN rental_bookings rb ON mrp.booking_id = rb.id
            WHERE rb.house_id = ? 
            AND rb.status IN ('pending', 'confirmed', 'active')
            AND mrp.status = 'unpaid'
        ");
        $stmt->bind_param('i', $propertyId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'];
    }
    
    /**
     * Log price change for audit trail
     */
    private function logPriceChange($propertyId, $oldPrice, $newPrice) {
        // Create price_change_log table if it doesn't exist
        $createTable = "
            CREATE TABLE IF NOT EXISTS price_change_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                property_id INT NOT NULL,
                old_price DECIMAL(15,2),
                new_price DECIMAL(15,2),
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                changed_by INT,
                INDEX (property_id)
            )
        ";
        $this->conn->query($createTable);
        
        // Insert log entry
        $stmt = $this->conn->prepare("
            INSERT INTO price_change_log (property_id, old_price, new_price, changed_by) 
            VALUES (?, ?, ?, ?)
        ");
        $changedBy = $_SESSION['user_id'] ?? 0;
        $stmt->bind_param('iddi', $propertyId, $oldPrice, $newPrice, $changedBy);
        $stmt->execute();
    }
    
    /**
     * Get property details
     */
    public function getPropertyDetails($propertyId) {
        $stmt = $this->conn->prepare("
            SELECT h.*, 
                   COUNT(rb.id) as active_bookings,
                   COUNT(mrp.id) as unpaid_monthly_payments
            FROM houses h
            LEFT JOIN rental_bookings rb ON h.id = rb.house_id AND rb.status IN ('pending', 'confirmed', 'active')
            LEFT JOIN monthly_rent_payments mrp ON rb.id = mrp.booking_id AND mrp.status = 'unpaid'
            WHERE h.id = ?
            GROUP BY h.id
        ");
        $stmt->bind_param('i', $propertyId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get affected bookings details
     */
    public function getAffectedBookings($propertyId) {
        $stmt = $this->conn->prepare("
            SELECT rb.*, u.firstname, u.lastname, u.email
            FROM rental_bookings rb
            JOIN users u ON rb.user_id = u.id
            WHERE rb.house_id = ? AND rb.status IN ('pending', 'confirmed', 'active')
            ORDER BY rb.created_at DESC
        ");
        $stmt->bind_param('i', $propertyId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Test the functionality
if (isset($_GET['test'])) {
    echo "<h2>Property Price Cascade Update Test</h2>";
    
    $cascade = new PropertyPriceCascade($conn);
    
    if (isset($_GET['property_id']) && isset($_GET['new_price'])) {
        $propertyId = (int)$_GET['property_id'];
        $newPrice = (float)$_GET['new_price'];
        
        // Get current property details
        $property = $cascade->getPropertyDetails($propertyId);
        
        if ($property) {
            echo "<h3>Property Details:</h3>";
            echo "<ul>";
            echo "<li><strong>Property:</strong> " . htmlspecialchars($property['house_no']) . "</li>";
            echo "<li><strong>Current Price:</strong> KSh " . number_format($property['price'], 2) . "</li>";
            echo "<li><strong>New Price:</strong> KSh " . number_format($newPrice, 2) . "</li>";
            echo "<li><strong>Active Bookings:</strong> " . $property['active_bookings'] . "</li>";
            echo "<li><strong>Unpaid Monthly Payments:</strong> " . $property['unpaid_monthly_payments'] . "</li>";
            echo "</ul>";
            
            if (isset($_GET['update']) && $_GET['update'] === '1') {
                $result = $cascade->updatePropertyPrice($propertyId, $newPrice, $property['price']);
                
                echo "<h3>Update Result:</h3>";
                if ($result['success']) {
                    echo "<div style='color: green;'>✅ " . $result['message'] . "</div>";
                    echo "<ul>";
                    echo "<li>Affected Bookings: " . $result['affected_bookings'] . "</li>";
                    echo "<li>Affected Monthly Payments: " . $result['affected_monthly_payments'] . "</li>";
                    echo "</ul>";
                } else {
                    echo "<div style='color: red;'>❌ " . $result['message'] . "</div>";
                }
            } else {
                echo "<p><a href='?test=1&property_id=$propertyId&new_price=$newPrice&update=1' class='btn btn-primary'>Update Price</a></p>";
            }
            
            // Show affected bookings
            $affectedBookings = $cascade->getAffectedBookings($propertyId);
            if (!empty($affectedBookings)) {
                echo "<h3>Affected Bookings:</h3>";
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
    } else {
        echo "<p>Usage: ?test=1&property_id=X&new_price=Y&update=1</p>";
    }
}
?> 