<?php
/**
 * Test Database Connection and Table Status
 * This script will help identify the source of the 500 error
 */

// Prevent any output before JSON response
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Start session
session_start();

// Database connection parameters
$host = "localhost";
$dbname = "house_rental";
$username = "root";
$password = "";

$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    // Test database connection
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $response['data']['db_connection'] = 'success';
    
    // Check if monthly_rent_payments table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'monthly_rent_payments'");
    $response['data']['table_exists'] = ($tableExists->num_rows > 0);
    
    if ($tableExists->num_rows === 0) {
        // Try to create the table
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
          `is_first_payment` tinyint(1) DEFAULT 0,
          `payment_type` varchar(50) DEFAULT 'monthly_rent',
          `security_deposit_amount` decimal(15,2) DEFAULT 0,
          `monthly_rent_amount` decimal(15,2) DEFAULT 0,
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
            $response['data']['table_created'] = true;
        } else {
            $response['data']['table_created'] = false;
            $response['data']['table_error'] = $conn->error;
        }
    }
    
    // Check session data
    $response['data']['session_user_id'] = $_SESSION['user_id'] ?? 'not_set';
    $response['data']['session_data'] = $_SESSION;
    
    // Check if rental_bookings table exists
    $bookingsTableExists = $conn->query("SHOW TABLES LIKE 'rental_bookings'");
    $response['data']['bookings_table_exists'] = ($bookingsTableExists->num_rows > 0);
    
    // Check if houses table exists
    $housesTableExists = $conn->query("SHOW TABLES LIKE 'houses'");
    $response['data']['houses_table_exists'] = ($housesTableExists->num_rows > 0);
    
    // Count records in key tables
    if ($response['data']['bookings_table_exists']) {
        $result = $conn->query("SELECT COUNT(*) as count FROM rental_bookings");
        $response['data']['bookings_count'] = $result->fetch_assoc()['count'];
    }
    
    if ($response['data']['table_exists']) {
        $result = $conn->query("SELECT COUNT(*) as count FROM monthly_rent_payments");
        $response['data']['monthly_payments_count'] = $result->fetch_assoc()['count'];
    }
    
    $response['success'] = true;
    $response['message'] = 'Database connection and table status checked successfully';
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    $response['data']['error'] = $e->getMessage();
}

// Clear any output buffer
ob_clean();

echo json_encode($response);
?> 