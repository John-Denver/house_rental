<?php
/**
 * Check Payment Tracking Table
 * Verifies that the payment_tracking table exists and has the correct structure
 */

session_start();
require_once '../config/db.php';

echo "<h2>Check Payment Tracking Table</h2>";

try {
    // Check if payment_tracking table exists
    echo "<h3>1. Table Existence Check:</h3>";
    $tableExists = $conn->query("SHOW TABLES LIKE 'payment_tracking'");
    
    if ($tableExists->num_rows > 0) {
        echo "<p style='color: green;'>✅ payment_tracking table exists</p>";
    } else {
        echo "<p style='color: red;'>❌ payment_tracking table does not exist</p>";
        
        // Create the table
        if (isset($_GET['create_table'])) {
            echo "<h3>2. Creating payment_tracking table...</h3>";
            
            $createTableSQL = "
            CREATE TABLE IF NOT EXISTS `payment_tracking` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `booking_id` int(11) NOT NULL,
              `payment_type` varchar(50) NOT NULL,
              `amount` decimal(15,2) NOT NULL,
              `security_deposit_amount` decimal(15,2) DEFAULT 0.00,
              `monthly_rent_amount` decimal(15,2) DEFAULT 0.00,
              `month` date DEFAULT NULL COMMENT 'For monthly payments, the month this payment covers',
              `is_first_payment` tinyint(1) DEFAULT 0,
              `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
              `payment_date` datetime DEFAULT NULL,
              `payment_method` varchar(50) DEFAULT NULL,
              `transaction_id` varchar(255) DEFAULT NULL,
              `mpesa_receipt_number` varchar(50) DEFAULT NULL,
              `notes` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_booking_id` (`booking_id`),
              KEY `idx_payment_type` (`payment_type`),
              KEY `idx_month` (`month`),
              KEY `idx_status` (`status`),
              KEY `idx_is_first_payment` (`is_first_payment`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            if ($conn->query($createTableSQL)) {
                echo "<p style='color: green;'>✅ payment_tracking table created successfully</p>";
            } else {
                echo "<p style='color: red;'>❌ Error creating table: " . $conn->error . "</p>";
            }
        } else {
            echo "<p><a href='?create_table=1' class='btn btn-primary'>Create payment_tracking Table</a></p>";
        }
    }

    // Check table structure
    echo "<h3>3. Table Structure Check:</h3>";
    $stmt = $conn->prepare("DESCRIBE payment_tracking");
    $stmt->execute();
    $columns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if ($columns) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th>";
        echo "</tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Check for sample data
    echo "<h3>4. Sample Data Check:</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payment_tracking");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo "<p>Total records in payment_tracking: " . $result['count'] . "</p>";

    if ($result['count'] > 0) {
        echo "<h4>Recent Payment Tracking Records:</h4>";
        $stmt = $conn->prepare("
            SELECT 
                id, booking_id, payment_type, amount, month, status, payment_date 
            FROM payment_tracking 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>Booking ID</th><th>Type</th><th>Amount</th><th>Month</th><th>Status</th><th>Date</th>";
        echo "</tr>";
        foreach ($records as $record) {
            echo "<tr>";
            echo "<td>" . $record['id'] . "</td>";
            echo "<td>" . $record['booking_id'] . "</td>";
            echo "<td>" . $record['payment_type'] . "</td>";
            echo "<td>KSh " . number_format($record['amount'], 2) . "</td>";
            echo "<td>" . ($record['month'] ? date('F Y', strtotime($record['month'])) : '-') . "</td>";
            echo "<td>" . $record['status'] . "</td>";
            echo "<td>" . ($record['payment_date'] ? date('M d, Y', strtotime($record['payment_date'])) : '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Test insert
    echo "<h3>5. Test Insert:</h3>";
    if (isset($_GET['test_insert'])) {
        echo "<p><strong>Testing insert...</strong></p>";
        
        $testData = [
            'booking_id' => 29, // Use your test booking ID
            'payment_type' => 'monthly_rent',
            'amount' => 1.00,
            'month' => '2025-09-01',
            'status' => 'completed',
            'payment_method' => 'manual',
            'transaction_id' => 'TEST-' . time()
        ];
        
        $stmt = $conn->prepare("
            INSERT INTO payment_tracking
            (booking_id, payment_type, amount, month, status, payment_method, transaction_id, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isdsisss',
            $testData['booking_id'],
            $testData['payment_type'],
            $testData['amount'],
            $testData['month'],
            $testData['status'],
            $testData['payment_method'],
            $testData['transaction_id'],
            'Test insert from check script'
        );
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ Test insert successful!</p>";
        } else {
            echo "<p style='color: red;'>❌ Test insert failed: " . $stmt->error . "</p>";
        }
    } else {
        echo "<p><a href='?test_insert=1' class='btn btn-success'>Test Insert</a></p>";
    }

    echo "<h3>6. Test Links:</h3>";
    echo "<ul>";
    echo "<li><a href='debug_prepayment.php?booking_id=29'>Debug Pre-Payment</a></li>";
    echo "<li><a href='test_payment_insert.php?booking_id=29'>Test Payment Insert</a></li>";
    echo "<li><a href='check_monthly_payments.php?booking_id=29'>Check Monthly Payments</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 