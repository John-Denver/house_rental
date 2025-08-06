<?php
/**
 * Add payment_type column to mpesa_payment_requests table
 */

require_once '../config/db.php';

try {
    // Add payment_type column to mpesa_payment_requests table
    $sql = "ALTER TABLE mpesa_payment_requests ADD COLUMN payment_type VARCHAR(50) DEFAULT 'initial' AFTER amount";
    $result = $conn->query($sql);
    
    if ($result) {
        echo "✅ Successfully added payment_type column to mpesa_payment_requests table<br>";
    } else {
        echo "❌ Failed to add payment_type column: " . $conn->error . "<br>";
    }
    
    // Update existing records to have 'initial' as payment_type
    $updateSql = "UPDATE mpesa_payment_requests SET payment_type = 'initial' WHERE payment_type IS NULL";
    $updateResult = $conn->query($updateSql);
    
    if ($updateResult) {
        echo "✅ Successfully updated existing records with default payment_type<br>";
    } else {
        echo "❌ Failed to update existing records: " . $conn->error . "<br>";
    }
    
    echo "<br>🎉 Payment type column setup complete!";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?> 