<?php
/**
 * Test the bind_param fix
 * This script tests the specific line that was causing the error
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Test bind_param Fix</h2>";

// Test the specific SQL statement that was causing the error
$testSQL = "
    INSERT INTO monthly_rent_payments 
    (booking_id, month, amount, status, payment_date, payment_method, mpesa_receipt_number, notes, is_first_payment, payment_type)
    VALUES (?, ?, ?, 'paid', ?, ?, ?, ?, 1, 'initial_payment')
";

echo "<h3>SQL Statement:</h3>";
echo "<pre>" . htmlspecialchars($testSQL) . "</pre>";

// Count the placeholders
$placeholderCount = substr_count($testSQL, '?');
echo "<p>Number of placeholders (?): $placeholderCount</p>";

// Test the bind_param call
try {
    $stmt = $conn->prepare($testSQL);
    
    if ($stmt) {
        echo "<p style='color: green;'>✅ SQL prepared successfully</p>";
        
        // Test with dummy values
        $bookingId = 1;
        $month = '2025-08-01';
        $amount = 100.00;
        $paymentDate = '2025-08-06 10:45:00';
        $paymentMethod = 'M-Pesa';
        $transactionId = 'MPESA_123456';
        $notes = 'Test payment';
        
        // The type definition should be 'isdsisss' (7 characters for 7 placeholders)
        $typeDef = 'isdsisss';
        echo "<p>Type definition: '$typeDef' (length: " . strlen($typeDef) . ")</p>";
        echo "<p>Expected: 7 parameters</p>";
        
        $result = $stmt->bind_param($typeDef, 
            $bookingId, 
            $month, 
            $amount,
            $paymentDate,
            $paymentMethod,
            $transactionId,
            $notes
        );
        
        if ($result) {
            echo "<p style='color: green;'>✅ bind_param executed successfully</p>";
        } else {
            echo "<p style='color: red;'>❌ bind_param failed: " . $stmt->error . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ SQL preparation failed: " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Test Complete</h3>";
echo "<p>If you see green checkmarks above, the fix is working correctly.</p>";
echo "<p>You can now test the monthly payments functionality in my_bookings.php</p>";
?> 