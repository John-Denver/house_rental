<?php
/**
 * Debug Monthly Payments System
 * Check database connection and table structure
 */

require_once '../config/db.php';
require_once 'includes/monthly_payment_helper.php';

echo "=== Monthly Payments Debug ===\n";

try {
    // Test database connection
    echo "1. Testing database connection...\n";
    if ($conn->ping()) {
        echo "✓ Database connection successful\n";
    } else {
        echo "✗ Database connection failed\n";
        exit(1);
    }
    
    // Check if monthly_rent_payments table exists
    echo "2. Checking monthly_rent_payments table...\n";
    $result = $conn->query("SHOW TABLES LIKE 'monthly_rent_payments'");
    if ($result->num_rows > 0) {
        echo "✓ Table exists\n";
        
        // Check table structure
        $result = $conn->query("DESCRIBE monthly_rent_payments");
        echo "Table structure:\n";
        while ($row = $result->fetch_assoc()) {
            echo "  - {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Key']}\n";
        }
    } else {
        echo "✗ Table does not exist\n";
        echo "Creating table...\n";
        
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `monthly_rent_payments` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `booking_id` int(11) NOT NULL,
          `month` date NOT NULL COMMENT 'First day of the month (YYYY-MM-01)',
          `amount` decimal(15,2) NOT NULL,
          `status` enum('paid','unpaid','overdue') NOT NULL DEFAULT 'unpaid',
          `payment_date` datetime DEFAULT NULL,
          `payment_method` varchar(50) DEFAULT NULL,
          `transaction_id` varchar(255) DEFAULT NULL,
          `mpesa_receipt_number` varchar(50) DEFAULT NULL,
          `notes` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_booking_month` (`booking_id`, `month`),
          KEY `idx_booking_id` (`booking_id`),
          KEY `idx_month` (`month`),
          KEY `idx_status` (`status`),
          KEY `idx_payment_date` (`payment_date`),
          CONSTRAINT `fk_monthly_rent_booking` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        if ($conn->query($createTableSQL)) {
            echo "✓ Table created successfully\n";
        } else {
            echo "✗ Error creating table: " . $conn->error . "\n";
        }
    }
    
    // Check if rental_bookings table exists
    echo "3. Checking rental_bookings table...\n";
    $result = $conn->query("SHOW TABLES LIKE 'rental_bookings'");
    if ($result->num_rows > 0) {
        echo "✓ rental_bookings table exists\n";
        
        // Count bookings
        $result = $conn->query("SELECT COUNT(*) as count FROM rental_bookings");
        $count = $result->fetch_assoc()['count'];
        echo "  - Total bookings: $count\n";
        
        // Check confirmed bookings
        $result = $conn->query("SELECT COUNT(*) as count FROM rental_bookings WHERE status IN ('confirmed', 'paid', 'active')");
        $count = $result->fetch_assoc()['count'];
        echo "  - Confirmed bookings: $count\n";
    } else {
        echo "✗ rental_bookings table does not exist\n";
    }
    
    // Test helper functions
    echo "4. Testing helper functions...\n";
    
    // Test getCurrentMonthPaymentStatus
    $testBookingId = 1;
    $status = getCurrentMonthPaymentStatus($conn, $testBookingId);
    echo "  - Current month status for booking $testBookingId: " . json_encode($status) . "\n";
    
    // Test getMonthlyPayments
    $payments = getMonthlyPayments($conn, $testBookingId);
    echo "  - Monthly payments for booking $testBookingId: " . count($payments) . " records\n";
    
    // Test getPaymentStatusBadge
    $badge = getPaymentStatusBadge('paid');
    echo "  - Payment status badge: $badge\n";
    
    echo "=== Debug Complete ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 