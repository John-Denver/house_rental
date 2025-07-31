<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/RentPaymentController.php';

// This script should be set up as a cron job to run on the 1st of every month
// Example cron entry:
// 0 0 1 * * php /path/to/smart_rental/cron/generate_invoices.php

// Log file
$logFile = __DIR__ . '/cron_invoices.log';
$logMessage = "[" . date('Y-m-d H:i:s') . "] Starting invoice generation\n";

try {
    // Initialize database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Initialize RentPaymentController
    $rentPaymentController = new RentPaymentController($conn);
    
    // Process late fees for overdue payments
    $rentPaymentController->processLateFees();
    $logMessage .= "Processed late fees for overdue payments\n";
    
    // Generate monthly invoices for all active bookings
    $invoiceCount = $rentPaymentController->generateMonthlyInvoices();
    $logMessage .= "Generated $invoiceCount monthly invoices\n";
    
    $logMessage .= "Invoice generation completed successfully\n";
    
} catch (Exception $e) {
    $logMessage .= "ERROR: " . $e->getMessage() . "\n";
    $logMessage .= "Stack trace: " . $e->getTraceAsString() . "\n";
} finally {
    // Write to log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Close database connection if it exists
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    
    // For CLI execution, also output to console
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}
