<?php
/**
 * Test Monthly Payments System
 * Simple test to verify everything works
 */

require_once '../config/db.php';

echo "Testing Monthly Payments System...\n";

try {
    // Test database connection
    if ($conn->ping()) {
        echo "✓ Database connection successful\n";
    } else {
        echo "✗ Database connection failed\n";
        exit(1);
    }
    
    // Create table if it doesn't exist
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
      KEY `idx_payment_date` (`payment_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($createTableSQL)) {
        echo "✓ Table created/verified successfully\n";
    } else {
        echo "✗ Error creating table: " . $conn->error . "\n";
        exit(1);
    }
    
    // Test inserting a sample record
    $testBookingId = 1;
    $testMonth = date('Y-m-01');
    $testAmount = 50000.00;
    
    $stmt = $conn->prepare("
        INSERT INTO monthly_rent_payments (booking_id, month, amount, status)
        VALUES (?, ?, ?, 'unpaid')
        ON DUPLICATE KEY UPDATE amount = VALUES(amount)
    ");
    $stmt->bind_param('isd', $testBookingId, $testMonth, $testAmount);
    
    if ($stmt->execute()) {
        echo "✓ Test record inserted successfully\n";
    } else {
        echo "✗ Error inserting test record: " . $stmt->error . "\n";
    }
    
    // Test retrieving the record
    $stmt = $conn->prepare("
        SELECT * FROM monthly_rent_payments WHERE booking_id = ? AND month = ?
    ");
    $stmt->bind_param('is', $testBookingId, $testMonth);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        echo "✓ Test record retrieved successfully\n";
        echo "  - Booking ID: " . $result['booking_id'] . "\n";
        echo "  - Month: " . $result['month'] . "\n";
        echo "  - Amount: " . $result['amount'] . "\n";
        echo "  - Status: " . $result['status'] . "\n";
    } else {
        echo "✗ Error retrieving test record\n";
    }
    
    echo "✓ All tests passed!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 