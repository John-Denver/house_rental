# Payment System Fixes

## Overview
This document outlines the fixes implemented for three critical payment system issues:
1. M-Pesa Callback Race Conditions
2. Payment Status Inconsistency
3. Missing Database Transactions

## 1. M-Pesa Callback Race Conditions

### Problem
Multiple M-Pesa callbacks for the same payment could cause:
- Duplicate payment processing
- Database corruption
- Inconsistent booking status

### Solution
Implemented comprehensive race condition protection in `mpesa_callback.php`:

#### Database Transaction Protection
```php
// Start database transaction to prevent race conditions
$pdo->beginTransaction();

try {
    // Check if payment is already processed
    $checkQuery = "SELECT status, booking_id FROM mpesa_payment_requests 
                  WHERE checkout_request_id = ? FOR UPDATE";
    
    // Prevent duplicate processing
    if ($existingPayment['status'] === 'completed') {
        $pdo->rollback();
        return success response to prevent retries;
    }
    
    // Optimistic locking for updates
    $updateQuery = "UPDATE mpesa_payment_requests SET 
        status = 'completed', ... 
        WHERE checkout_request_id = ? AND status != 'completed'";
    
    // Verify update was successful
    if ($stmt->rowCount() === 0) {
        throw new Exception("Payment already processed");
    }
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    // Return success to M-Pesa to prevent retries
}
```

#### Key Features
- **FOR UPDATE Locking**: Prevents concurrent access to payment records
- **Optimistic Locking**: Updates only if status hasn't changed
- **Transaction Rollback**: Ensures data consistency on errors
- **Duplicate Detection**: Checks existing status before processing
- **M-Pesa Retry Prevention**: Always returns success to prevent retries

## 2. Payment Status Inconsistency

### Problem
Payment status could become inconsistent between:
- Frontend polling results
- M-Pesa callback processing
- Database state

### Solution
Implemented status synchronization and monitoring:

#### Status Synchronization Method
```php
public function synchronizePaymentStatus($bookingId) {
    // Get booking and payment status with transaction
    $bookingStmt = $this->conn->prepare("
        SELECT rb.*, 
               COUNT(bp.id) as payment_count,
               SUM(CASE WHEN bp.status = 'completed' THEN 1 ELSE 0 END) as completed_payments
        FROM rental_bookings rb
        LEFT JOIN booking_payments bp ON rb.id = bp.booking_id
        WHERE rb.id = ?
        GROUP BY rb.id
    ");
    
    // Determine correct status based on actual payments
    $hasCompletedPayments = $booking['completed_payments'] > 0;
    $correctStatus = $hasCompletedPayments ? 'confirmed' : 'pending';
    $correctPaymentStatus = $hasCompletedPayments ? 'paid' : 'pending';
    
    // Update if inconsistent
    if ($currentStatus !== $correctStatus || $currentPaymentStatus !== $correctPaymentStatus) {
        // Update with transaction safety
    }
}
```

#### Status Monitoring Script
Created `cron/monitor_payment_status.php` to:
- Detect payment status inconsistencies
- Automatically fix inconsistencies
- Monitor orphaned M-Pesa requests
- Log all status changes

#### Enhanced Status Checking
Updated `mpesa_payment_status.php` to:
- Check for status inconsistencies during polling
- Automatically fix detected inconsistencies
- Provide detailed logging

## 3. Missing Database Transactions

### Problem
Payment processing could leave database in inconsistent state if:
- Payment record created but booking not updated
- Partial updates during errors
- Concurrent payment attempts

### Solution
Implemented comprehensive transaction management:

#### BookingController.processPayment()
```php
public function processPayment($paymentData) {
    // Start database transaction
    $this->conn->begin_transaction();
    
    try {
        // Get booking with lock to prevent race conditions
        $bookingStmt = $this->conn->prepare("
            SELECT * FROM rental_bookings 
            WHERE id = ? AND (status = 'pending' OR status = 'confirmed')
            FOR UPDATE
        ");
        
        // Check for duplicate payments
        $duplicateStmt = $this->conn->prepare("
            SELECT COUNT(*) as payment_count 
            FROM booking_payments 
            WHERE booking_id = ? AND status = 'completed'
        ");
        
        if ($duplicateResult['payment_count'] > 0) {
            throw new Exception("Payment already completed for this booking");
        }
        
        // Validate payment amount
        $expectedAmount = $this->calculateExpectedPaymentAmount($bookingId);
        if (abs($amount - $expectedAmount) > 0.01) {
            throw new Exception("Payment amount does not match expected amount");
        }
        
        // Record payment
        $stmt = $this->conn->prepare("INSERT INTO booking_payments ...");
        
        // Update booking status with optimistic locking
        $updateStmt = $this->conn->prepare("
            UPDATE rental_bookings 
            SET status = 'paid', payment_status = 'paid', updated_at = NOW() 
            WHERE id = ? AND (status = 'pending' OR status = 'confirmed')
        ");
        
        if ($updateStmt->affected_rows === 0) {
            throw new Exception("Failed to update booking status");
        }
        
        // Commit transaction
        $this->conn->commit();
        
        // Send notifications outside transaction
        $this->sendPaymentConfirmation($bookingId, $amount, $paymentMethod);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $this->conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
```

#### Key Transaction Features
- **Atomic Operations**: All payment steps in single transaction
- **Row-Level Locking**: FOR UPDATE prevents concurrent modifications
- **Optimistic Locking**: Updates only if conditions still valid
- **Rollback on Error**: Ensures data consistency
- **External Operations**: Email notifications outside transaction

## Database Schema Updates

### New Columns Added
```sql
-- Add callback_data column to mpesa_payment_requests
ALTER TABLE mpesa_payment_requests 
ADD COLUMN callback_data TEXT NULL COMMENT 'Full callback data from M-Pesa API';

-- Add payment_status column to rental_bookings
ALTER TABLE rental_bookings 
ADD COLUMN payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending';

-- Add performance indexes
CREATE INDEX idx_mpesa_payment_requests_status ON mpesa_payment_requests(status);
CREATE INDEX idx_mpesa_payment_requests_checkout_id ON mpesa_payment_requests(checkout_request_id);
CREATE INDEX idx_rental_bookings_payment_status ON rental_bookings(payment_status);
```

## Monitoring and Logging

### Payment Monitor Script
- **Location**: `cron/monitor_payment_status.php`
- **Frequency**: Run every 5 minutes via cron
- **Functions**:
  - Detect payment status inconsistencies
  - Fix inconsistencies automatically
  - Monitor orphaned M-Pesa requests
  - Log all activities

### Enhanced Logging
- **M-Pesa Callbacks**: Full callback data stored in database
- **Status Changes**: All status updates logged
- **Error Tracking**: Comprehensive error logging
- **Audit Trail**: Complete payment processing history

## Testing Recommendations

### Race Condition Testing
1. **Concurrent Callbacks**: Send multiple callbacks for same payment
2. **Simultaneous Payments**: Multiple users paying for same booking
3. **Network Failures**: Test transaction rollback scenarios

### Status Consistency Testing
1. **Polling vs Callback**: Verify status consistency
2. **Manual vs M-Pesa**: Test different payment methods
3. **Error Scenarios**: Test partial payment failures

### Transaction Testing
1. **Database Failures**: Test rollback scenarios
2. **Concurrent Access**: Test row-level locking
3. **Amount Validation**: Test payment amount verification

## Deployment Checklist

### Database Updates
- [ ] Run `add_callback_data_column.sql` migration
- [ ] Verify new columns exist
- [ ] Check indexes are created

### Code Deployment
- [ ] Deploy updated `mpesa_callback.php`
- [ ] Deploy updated `BookingController.php`
- [ ] Deploy updated `mpesa_payment_status.php`
- [ ] Deploy monitoring script

### Monitoring Setup
- [ ] Set up cron job for payment monitor
- [ ] Configure log rotation
- [ ] Set up alerting for critical errors

### Testing
- [ ] Test M-Pesa integration
- [ ] Test manual payment processing
- [ ] Test status synchronization
- [ ] Test error scenarios

## Benefits

### Security
- **Race Condition Prevention**: No duplicate payments
- **Data Consistency**: Atomic operations
- **Audit Trail**: Complete payment history

### Reliability
- **Error Recovery**: Automatic rollback on failures
- **Status Consistency**: Automatic synchronization
- **Monitoring**: Proactive issue detection

### Performance
- **Optimized Queries**: Proper indexing
- **Efficient Locking**: Minimal lock duration
- **Reduced Retries**: Better error handling

These fixes ensure a robust, secure, and reliable payment system that can handle high concurrency and maintain data consistency under all conditions. 