<?php
/**
 * Setup Monthly Payments
 * Creates the monthly_rent_payments table and generates initial records
 */

require_once '../config/db.php';

echo "Setting up monthly payments system...\n";

try {
    // Create the monthly_rent_payments table
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
        echo "✓ Monthly rent payments table created successfully\n";
    } else {
        echo "✗ Error creating table: " . $conn->error . "\n";
        exit(1);
    }
    
    // Get all confirmed bookings
    $stmt = $conn->prepare("
        SELECT rb.id, rb.house_id, rb.start_date, rb.end_date, h.price
        FROM rental_bookings rb
        JOIN houses h ON rb.house_id = h.id
        WHERE rb.status IN ('confirmed', 'paid', 'active')
    ");
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "Found " . count($bookings) . " confirmed bookings to process...\n";
    
    $processed = 0;
    foreach ($bookings as $booking) {
        // Generate monthly payment records for each booking
        $start = new DateTime($booking['start_date']);
        $end = new DateTime($booking['end_date']);
        $current = clone $start;
        
        while ($current <= $end) {
            $month = $current->format('Y-m-01');
            
            // Check if record already exists
            $checkStmt = $conn->prepare("
                SELECT id FROM monthly_rent_payments 
                WHERE booking_id = ? AND month = ?
            ");
            $checkStmt->bind_param('is', $booking['id'], $month);
            $checkStmt->execute();
            
            if (!$checkStmt->get_result()->fetch_assoc()) {
                // Insert new monthly payment record
                $insertStmt = $conn->prepare("
                    INSERT INTO monthly_rent_payments (booking_id, month, amount, status)
                    VALUES (?, ?, ?, 'unpaid')
                ");
                $insertStmt->bind_param('isd', $booking['id'], $month, $booking['price']);
                
                if ($insertStmt->execute()) {
                    $processed++;
                }
            }
            
            $current->add(new DateInterval('P1M'));
        }
    }
    
    echo "✓ Generated " . $processed . " monthly payment records\n";
    echo "Setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error during setup: " . $e->getMessage() . "\n";
    exit(1);
}
?> 