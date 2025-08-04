<?php
/**
 * Test Previous Months Functionality
 * Generate sample monthly payment data for testing
 */

require_once '../config/db.php';

echo "Generating sample monthly payment data...\n";

try {
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
    
    // Generate sample data for booking ID 1 (if it exists)
    $bookingId = 1;
    $monthlyRent = 50000.00;
    
    // Generate 12 months of data (current year)
    $currentYear = date('Y');
    $months = [];
    
    for ($month = 1; $month <= 12; $month++) {
        $monthDate = sprintf('%04d-%02d-01', $currentYear, $month);
        $months[] = $monthDate;
    }
    
    echo "Generating data for booking $bookingId...\n";
    
    $inserted = 0;
    foreach ($months as $month) {
        // Randomly assign payment status
        $statuses = ['paid', 'unpaid', 'overdue'];
        $status = $statuses[array_rand($statuses)];
        
        // For paid months, add payment details
        $paymentDate = null;
        $paymentMethod = null;
        $mpesaReceipt = null;
        
        if ($status === 'paid') {
            $paymentDate = date('Y-m-d H:i:s', strtotime($month . ' +' . rand(1, 15) . ' days'));
            $paymentMethod = 'M-Pesa';
            $mpesaReceipt = 'MPESA_' . time() . '_' . rand(1000, 9999);
        }
        
        $stmt = $conn->prepare("
            INSERT INTO monthly_rent_payments 
            (booking_id, month, amount, status, payment_date, payment_method, mpesa_receipt_number, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            payment_date = VALUES(payment_date),
            payment_method = VALUES(payment_method),
            mpesa_receipt_number = VALUES(mpesa_receipt_number),
            notes = VALUES(notes)
        ");
        
        $notes = $status === 'paid' ? 'Sample payment data' : 'Sample unpaid month';
        $stmt->bind_param('isdsisss', 
            $bookingId, 
            $month, 
            $monthlyRent, 
            $status, 
            $paymentDate, 
            $paymentMethod, 
            $mpesaReceipt, 
            $notes
        );
        
        if ($stmt->execute()) {
            $inserted++;
            echo "✓ Month $month: $status\n";
        } else {
            echo "✗ Error inserting $month: " . $stmt->error . "\n";
        }
    }
    
    echo "✓ Generated $inserted months of sample data\n";
    echo "✓ Test data ready! Try viewing monthly payments for booking $bookingId\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 