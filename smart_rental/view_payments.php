<?php
/**
 * View Payment Details
 * This script displays payment information from the database
 */

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rental_system;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "💰 Payment Details Dashboard\n";
echo "============================\n\n";

// Get all payment requests
$query = "SELECT 
    pr.*,
    rb.property_id,
    rb.user_id,
    rb.check_in_date,
    rb.check_out_date,
    rb.total_amount
FROM mpesa_payment_requests pr
LEFT JOIN rental_bookings rb ON pr.booking_id = rb.id
ORDER BY pr.created_at DESC
LIMIT 20";

$stmt = $pdo->prepare($query);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📊 Recent Payment Requests (" . count($payments) . " records):\n";
echo "==========================================\n\n";

if (empty($payments)) {
    echo "❌ No payment requests found in database.\n";
    echo "💡 This could mean:\n";
    echo "   - No payments have been made yet\n";
    echo "   - Database table doesn't exist\n";
    echo "   - Database connection issues\n\n";
} else {
    foreach ($payments as $payment) {
        echo "🆔 Payment ID: " . $payment['id'] . "\n";
        echo "📅 Created: " . $payment['created_at'] . "\n";
        echo "📱 Phone: " . $payment['phone_number'] . "\n";
        echo "💰 Amount: Ksh " . number_format($payment['amount'], 2) . "\n";
        echo "📋 Status: " . strtoupper($payment['status']) . "\n";
        
        if ($payment['mpesa_receipt_number']) {
            echo "🧾 Receipt: " . $payment['mpesa_receipt_number'] . "\n";
        }
        
        if ($payment['result_code']) {
            echo "🔢 Result Code: " . $payment['result_code'] . "\n";
        }
        
        if ($payment['result_desc']) {
            echo "📝 Description: " . $payment['result_desc'] . "\n";
        }
        
        if ($payment['booking_id']) {
            echo "🏠 Booking ID: " . $payment['booking_id'] . "\n";
            echo "📅 Check-in: " . $payment['check_in_date'] . "\n";
            echo "📅 Check-out: " . $payment['check_out_date'] . "\n";
            echo "💵 Total Amount: Ksh " . number_format($payment['total_amount'], 2) . "\n";
        }
        
        echo "🆔 Checkout Request ID: " . $payment['checkout_request_id'] . "\n";
        echo "----------------------------------------\n\n";
    }
}

// Get payment statistics
echo "📈 Payment Statistics:\n";
echo "======================\n";

$statsQuery = "SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_payments,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount
FROM mpesa_payment_requests";

$stmt = $pdo->prepare($statsQuery);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📊 Total Payments: " . $stats['total_payments'] . "\n";
echo "✅ Successful: " . $stats['successful_payments'] . "\n";
echo "❌ Failed: " . $stats['failed_payments'] . "\n";
echo "⏳ Pending: " . $stats['pending_payments'] . "\n";
echo "💰 Total Amount: Ksh " . number_format($stats['total_amount'], 2) . "\n\n";

// Check if database table exists
$tableQuery = "SHOW TABLES LIKE 'mpesa_payment_requests'";
$stmt = $pdo->prepare($tableQuery);
$stmt->execute();
$tableExists = $stmt->fetch();

if (!$tableExists) {
    echo "⚠️  WARNING: mpesa_payment_requests table doesn't exist!\n";
    echo "💡 Run the SQL file: smart_rental/database/mpesa_payment_requests.sql\n\n";
}

// Check callback logs
$logFile = __DIR__ . '/logs/mpesa_callback.log';
if (file_exists($logFile)) {
    $logSize = filesize($logFile);
    echo "📄 Callback Logs:\n";
    echo "=================\n";
    echo "📁 File: smart_rental/logs/mpesa_callback.log\n";
    echo "📏 Size: " . number_format($logSize) . " bytes\n";
    
    // Show last few log entries
    $lines = file($logFile);
    if ($lines) {
        echo "📝 Last 3 log entries:\n";
        $lastLines = array_slice($lines, -6);
        foreach ($lastLines as $line) {
            echo "   " . trim($line) . "\n";
        }
    }
} else {
    echo "📄 Callback Logs: No log file found\n";
}

echo "\n🎯 What happens when a user pays:\n";
echo "==================================\n";
echo "1. ✅ User initiates M-Pesa payment\n";
echo "2. ✅ Payment request saved to database (status: pending)\n";
echo "3. ✅ M-Pesa processes payment\n";
echo "4. ✅ M-Pesa sends callback to your ngrok URL\n";
echo "5. ✅ Callback updates payment status in database\n";
echo "6. ✅ Booking status updated to 'confirmed'\n";
echo "7. ✅ Payment details logged for monitoring\n\n";

echo "💡 To test the complete flow:\n";
echo "1. Make a payment through your rental system\n";
echo "2. Check this script to see payment details\n";
echo "3. Monitor logs/mpesa_callback.log\n";
echo "4. Check ngrok web interface at http://localhost:4040\n";
?> 