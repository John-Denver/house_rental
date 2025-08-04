<?php
/**
 * Setup Payment Tracking System
 * Implements the new payment tracking system to distinguish between initial payments and monthly payments
 */

// Database connection
$host = "localhost";
$dbname = "house_rental";
$username = "root";
$password = "";

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Setting up Payment Tracking System</h2>";
    
    // 1. Create payment_types table
    echo "<h3>1. Creating payment_types table...</h3>";
    $createPaymentTypesTable = "
    CREATE TABLE IF NOT EXISTS `payment_types` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(50) NOT NULL,
      `description` text,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($createPaymentTypesTable)) {
        echo "<p>✅ payment_types table created successfully</p>";
    } else {
        echo "<p>❌ Error creating payment_types table: " . $conn->error . "</p>";
    }
    
    // 2. Insert default payment types
    echo "<h3>2. Inserting default payment types...</h3>";
    $insertPaymentTypes = "
    INSERT INTO `payment_types` (`name`, `description`) VALUES
    ('initial_payment', 'Security deposit + first month rent'),
    ('monthly_rent', 'Monthly rent payment'),
    ('security_deposit', 'Security deposit only'),
    ('additional_fees', 'Additional fees or charges'),
    ('penalty', 'Late payment penalty'),
    ('refund', 'Refund of security deposit or overpayment')
    ON DUPLICATE KEY UPDATE description = VALUES(description);
    ";
    
    if ($conn->query($insertPaymentTypes)) {
        echo "<p>✅ Default payment types inserted successfully</p>";
    } else {
        echo "<p>❌ Error inserting payment types: " . $conn->error . "</p>";
    }
    
    // 3. Check if monthly_rent_payments table exists
    echo "<h3>3. Checking monthly_rent_payments table...</h3>";
    $tableExists = $conn->query("SHOW TABLES LIKE 'monthly_rent_payments'");
    if ($tableExists->num_rows === 0) {
        echo "<p>❌ monthly_rent_payments table does not exist. Please run setup_monthly_payments.php first.</p>";
        exit;
    } else {
        echo "<p>✅ monthly_rent_payments table exists</p>";
    }
    
    // 4. Add new columns to monthly_rent_payments table
    echo "<h3>4. Adding new columns to monthly_rent_payments table...</h3>";
    
    // Check if columns already exist
    $columns = $conn->query("SHOW COLUMNS FROM monthly_rent_payments");
    $existingColumns = [];
    while ($column = $columns->fetch_assoc()) {
        $existingColumns[] = $column['Field'];
    }
    
    $newColumns = [
        'payment_type' => "ADD COLUMN `payment_type` varchar(50) DEFAULT 'monthly_rent' AFTER `status`",
        'is_first_payment' => "ADD COLUMN `is_first_payment` tinyint(1) DEFAULT 0 AFTER `payment_type`",
        'security_deposit_amount' => "ADD COLUMN `security_deposit_amount` decimal(15,2) DEFAULT 0.00 AFTER `is_first_payment`",
        'monthly_rent_amount' => "ADD COLUMN `monthly_rent_amount` decimal(15,2) DEFAULT 0.00 AFTER `security_deposit_amount`"
    ];
    
    foreach ($newColumns as $columnName => $sql) {
        if (!in_array($columnName, $existingColumns)) {
            $alterSQL = "ALTER TABLE `monthly_rent_payments` $sql";
            if ($conn->query($alterSQL)) {
                echo "<p>✅ Added column: $columnName</p>";
            } else {
                echo "<p>❌ Error adding column $columnName: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>✅ Column $columnName already exists</p>";
        }
    }
    
    // 5. Add indexes
    echo "<h3>5. Adding indexes...</h3>";
    $indexes = [
        'idx_payment_type' => "ADD INDEX `idx_payment_type` (`payment_type`)",
        'idx_is_first_payment' => "ADD INDEX `idx_is_first_payment` (`is_first_payment`)"
    ];
    
    foreach ($indexes as $indexName => $sql) {
        $alterSQL = "ALTER TABLE `monthly_rent_payments` $sql";
        if ($conn->query($alterSQL)) {
            echo "<p>✅ Added index: $indexName</p>";
        } else {
            echo "<p>⚠️ Index $indexName may already exist: " . $conn->error . "</p>";
        }
    }
    
    // 6. Create payment_tracking table
    echo "<h3>6. Creating payment_tracking table...</h3>";
    $createPaymentTrackingTable = "
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
    
    if ($conn->query($createPaymentTrackingTable)) {
        echo "<p>✅ payment_tracking table created successfully</p>";
    } else {
        echo "<p>❌ Error creating payment_tracking table: " . $conn->error . "</p>";
    }
    
    // 7. Create database functions
    echo "<h3>7. Creating database functions...</h3>";
    
    // Drop functions if they exist
    $conn->query("DROP FUNCTION IF EXISTS get_next_unpaid_month");
    $conn->query("DROP FUNCTION IF EXISTS has_first_payment_been_made");
    $conn->query("DROP FUNCTION IF EXISTS get_first_unpaid_month");
    
    // Create get_next_unpaid_month function
    $createNextUnpaidMonthFunction = "
    CREATE FUNCTION `get_next_unpaid_month`(booking_id_param INT) 
    RETURNS DATE
    READS SQL DATA
    DETERMINISTIC
    BEGIN
        DECLARE next_month DATE;
        
        -- Get the next unpaid month after the last paid month
        SELECT DATE_ADD(month, INTERVAL 1 MONTH)
        INTO next_month
        FROM monthly_rent_payments 
        WHERE booking_id = booking_id_param 
        AND status = 'paid'
        ORDER BY month DESC 
        LIMIT 1;
        
        -- If no paid months found, get the first month of the booking
        IF next_month IS NULL THEN
            SELECT start_date
            INTO next_month
            FROM rental_bookings 
            WHERE id = booking_id_param;
            
            -- Set to first day of the month
            SET next_month = DATE_FORMAT(next_month, '%Y-%m-01');
        END IF;
        
        RETURN next_month;
    END;
    ";
    
    if ($conn->query($createNextUnpaidMonthFunction)) {
        echo "<p>✅ get_next_unpaid_month function created successfully</p>";
    } else {
        echo "<p>❌ Error creating get_next_unpaid_month function: " . $conn->error . "</p>";
    }
    
    // Create has_first_payment_been_made function
    $createHasFirstPaymentFunction = "
    CREATE FUNCTION `has_first_payment_been_made`(booking_id_param INT) 
    RETURNS BOOLEAN
    READS SQL DATA
    DETERMINISTIC
    BEGIN
        DECLARE first_payment_exists INT DEFAULT 0;
        
        SELECT COUNT(*)
        INTO first_payment_exists
        FROM monthly_rent_payments 
        WHERE booking_id = booking_id_param 
        AND is_first_payment = 1 
        AND status = 'paid';
        
        RETURN first_payment_exists > 0;
    END;
    ";
    
    if ($conn->query($createHasFirstPaymentFunction)) {
        echo "<p>✅ has_first_payment_been_made function created successfully</p>";
    } else {
        echo "<p>❌ Error creating has_first_payment_been_made function: " . $conn->error . "</p>";
    }
    
    // Create get_first_unpaid_month function
    $createFirstUnpaidMonthFunction = "
    CREATE FUNCTION `get_first_unpaid_month`(booking_id_param INT) 
    RETURNS DATE
    READS SQL DATA
    DETERMINISTIC
    BEGIN
        DECLARE first_unpaid_month DATE;
        
        -- Get the first unpaid month
        SELECT month
        INTO first_unpaid_month
        FROM monthly_rent_payments 
        WHERE booking_id = booking_id_param 
        AND status = 'unpaid'
        ORDER BY month ASC 
        LIMIT 1;
        
        RETURN first_unpaid_month;
    END;
    ";
    
    if ($conn->query($createFirstUnpaidMonthFunction)) {
        echo "<p>✅ get_first_unpaid_month function created successfully</p>";
    } else {
        echo "<p>❌ Error creating get_first_unpaid_month function: " . $conn->error . "</p>";
    }
    
    // 8. Update existing data
    echo "<h3>8. Updating existing payment data...</h3>";
    
    // Update existing paid payments to mark them as first payments if they're the only paid payment
    $updateFirstPayments = "
    UPDATE monthly_rent_payments 
    SET is_first_payment = 1, payment_type = 'initial_payment'
    WHERE id IN (
        SELECT * FROM (
            SELECT mr1.id
            FROM monthly_rent_payments mr1
            WHERE mr1.status = 'paid'
            AND mr1.booking_id IN (
                SELECT booking_id 
                FROM monthly_rent_payments 
                WHERE status = 'paid'
                GROUP BY booking_id 
                HAVING COUNT(*) = 1
            )
        ) AS temp
    );
    ";
    
    if ($conn->query($updateFirstPayments)) {
        echo "<p>✅ Updated existing first payments</p>";
    } else {
        echo "<p>❌ Error updating first payments: " . $conn->error . "</p>";
    }
    
    // Update payment amounts for existing records
    $updateAmounts = "
    UPDATE monthly_rent_payments 
    SET monthly_rent_amount = amount
    WHERE monthly_rent_amount = 0 AND amount > 0;
    ";
    
    if ($conn->query($updateAmounts)) {
        echo "<p>✅ Updated payment amounts</p>";
    } else {
        echo "<p>❌ Error updating payment amounts: " . $conn->error . "</p>";
    }
    
    echo "<h3>✅ Payment Tracking System Setup Complete!</h3>";
    echo "<p>The system now distinguishes between:</p>";
    echo "<ul>";
    echo "<li><strong>Initial Payment:</strong> Security deposit + first month's rent</li>";
    echo "<li><strong>Monthly Rent:</strong> Subsequent monthly rent payments</li>";
    echo "</ul>";
    echo "<p>Next payments will automatically be tracked as monthly rent payments.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 