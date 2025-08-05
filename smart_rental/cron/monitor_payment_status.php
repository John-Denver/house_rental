<?php
/**
 * Payment Status Monitor
 * Detects and fixes payment status inconsistencies
 * Run this script via cron every 5 minutes
 */

require_once '../config/db.php';
require_once '../controllers/BookingController.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log file
$logFile = __DIR__ . '/../logs/payment_monitor.log';
$logDir = dirname($logFile);

// Create logs directory if it doesn't exist
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    writeLog("Starting payment status monitoring...");
    
    $bookingController = new BookingController($conn);
    
    // Find bookings with payment status inconsistencies
    $inconsistencyQuery = "
        SELECT rb.id, rb.status, rb.payment_status,
               COUNT(bp.id) as total_payments,
               SUM(CASE WHEN bp.status = 'completed' THEN 1 ELSE 0 END) as completed_payments
        FROM rental_bookings rb
        LEFT JOIN booking_payments bp ON rb.id = bp.booking_id
        GROUP BY rb.id
        HAVING 
            (completed_payments > 0 AND (rb.status != 'confirmed' OR rb.payment_status != 'paid'))
            OR (completed_payments = 0 AND (rb.status = 'confirmed' OR rb.payment_status = 'paid'))
    ";
    
    $result = $conn->query($inconsistencyQuery);
    $inconsistencies = [];
    
    while ($row = $result->fetch_assoc()) {
        $inconsistencies[] = $row;
    }
    
    writeLog("Found " . count($inconsistencies) . " payment status inconsistencies");
    
    $fixedCount = 0;
    $errorCount = 0;
    
    foreach ($inconsistencies as $booking) {
        try {
            $result = $bookingController->synchronizePaymentStatus($booking['id']);
            
            if ($result['success']) {
                $fixedCount++;
                writeLog("Fixed booking #{$booking['id']}: status {$result['old_status']}->{$result['new_status']}, payment_status {$result['old_payment_status']}->{$result['new_payment_status']}");
            } else {
                $errorCount++;
                writeLog("Error fixing booking #{$booking['id']}: " . $result['message']);
            }
        } catch (Exception $e) {
            $errorCount++;
            writeLog("Exception fixing booking #{$booking['id']}: " . $e->getMessage());
        }
    }
    
    // Check for orphaned M-Pesa payment requests
    $orphanedQuery = "
        SELECT pr.*, rb.id as booking_id, rb.status as booking_status
        FROM mpesa_payment_requests pr
        LEFT JOIN rental_bookings rb ON pr.booking_id = rb.id
        WHERE rb.id IS NULL OR rb.status = 'cancelled'
    ";
    
    $orphanedResult = $conn->query($orphanedQuery);
    $orphanedCount = 0;
    
    while ($orphaned = $orphanedResult->fetch_assoc()) {
        $orphanedCount++;
        writeLog("Found orphaned M-Pesa payment request: checkout_request_id={$orphaned['checkout_request_id']}, booking_id={$orphaned['booking_id']}");
    }
    
    // Check for M-Pesa callbacks without corresponding payment records
    $missingPaymentsQuery = "
        SELECT pr.checkout_request_id, pr.booking_id, pr.status as mpesa_status,
               COUNT(bp.id) as payment_count
        FROM mpesa_payment_requests pr
        LEFT JOIN booking_payments bp ON pr.booking_id = bp.booking_id AND bp.status = 'completed'
        WHERE pr.status = 'completed' AND payment_count = 0
        GROUP BY pr.checkout_request_id
    ";
    
    $missingResult = $conn->query($missingPaymentsQuery);
    $missingCount = 0;
    
    while ($missing = $missingResult->fetch_assoc()) {
        $missingCount++;
        writeLog("Found completed M-Pesa request without payment record: checkout_request_id={$missing['checkout_request_id']}, booking_id={$missing['booking_id']}");
    }
    
    writeLog("Monitoring completed: $fixedCount fixed, $errorCount errors, $orphanedCount orphaned requests, $missingCount missing payments");
    
} catch (Exception $e) {
    writeLog("Critical error in payment monitoring: " . $e->getMessage());
    exit(1);
}

writeLog("Payment status monitoring completed successfully");
?> 