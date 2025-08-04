<?php
/**
 * Manual Payment Success
 * Manually mark a payment as successful for testing
 */

// Start output buffering
ob_start();

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
    
    echo "<h2>Manual Payment Success Test</h2>";
    
    // Get the latest payment request
    $stmt = $conn->prepare("
        SELECT 
            id,
            checkout_request_id,
            booking_id,
            amount,
            status,
            created_at
        FROM mpesa_payment_requests 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $payment_request = $stmt->get_result()->fetch_assoc();
    
    if (!$payment_request) {
        echo "<p>No payment requests found.</p>";
        exit;
    }
    
    echo "<h3>Latest Payment Request:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Checkout Request ID</th><th>Booking ID</th><th>Amount</th><th>Status</th><th>Created</th></tr>";
    echo "<tr>";
    echo "<td>" . $payment_request['id'] . "</td>";
    echo "<td>" . $payment_request['checkout_request_id'] . "</td>";
    echo "<td>" . $payment_request['booking_id'] . "</td>";
    echo "<td>KSh " . number_format($payment_request['amount'], 2) . "</td>";
    echo "<td style='color: " . ($payment_request['status'] === 'completed' ? 'green' : ($payment_request['status'] === 'failed' ? 'red' : 'orange')) . ";'>" . $payment_request['status'] . "</td>";
    echo "<td>" . $payment_request['created_at'] . "</td>";
    echo "</tr>";
    echo "</table>";
    
    // Check if payment is already completed
    if ($payment_request['status'] === 'completed') {
        echo "<p>✅ Payment is already marked as completed.</p>";
    } else {
        echo "<h3>Mark Payment as Successful</h3>";
        
        // Update payment request to completed
        $receipt_number = time();
        $manual_receipt = 'MANUAL_' . $receipt_number;
        
        $stmt = $conn->prepare("
            UPDATE mpesa_payment_requests 
            SET 
                status = 'completed',
                result_code = '0',
                result_desc = 'Payment completed successfully (manual)',
                mpesa_receipt_number = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('si', $manual_receipt, $payment_request['id']);
        $stmt->execute();
        
        // Update booking status
        $stmt = $conn->prepare("
            UPDATE rental_bookings 
            SET status = 'paid', updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $payment_request['booking_id']);
        $stmt->execute();
        
        // Record payment in booking_payments table
        $stmt = $conn->prepare("
            INSERT INTO booking_payments (
                booking_id, amount, payment_method, transaction_id, 
                payment_date, status, notes, created_at
            ) VALUES (?, ?, 'M-Pesa', ?, NOW(), 'completed', ?, NOW())
        ");
        $transaction_id = 'MANUAL_' . time();
        $notes = 'Manual Payment Success - Checkout Request: ' . $payment_request['checkout_request_id'];
        $stmt->bind_param('idss', 
            $payment_request['booking_id'], 
            $payment_request['amount'], 
            $transaction_id, 
            $notes
        );
        $stmt->execute();
        
        // Include payment tracking helper
        require_once 'includes/payment_tracking_helper.php';
        
        // Check if this is the first payment for this booking
        $hasFirstPayment = hasFirstPaymentBeenMade($conn, $payment_request['booking_id']);
        
        if (!$hasFirstPayment) {
            // This is the initial payment (security deposit + first month rent)
            $breakdown = getInitialPaymentBreakdown($conn, $payment_request['booking_id']);
            $securityDepositAmount = $breakdown['security_deposit'];
            $monthlyRentAmount = $breakdown['monthly_rent'];
            
            // Record initial payment
            recordInitialPayment(
                $conn, 
                $payment_request['booking_id'], 
                $payment_request['amount'], 
                $securityDepositAmount, 
                $monthlyRentAmount, 
                'M-Pesa', 
                $transaction_id, 
                $manual_receipt, 
                'Manual Initial Payment - Checkout Request: ' . $payment_request['checkout_request_id']
            );
        } else {
            // This is a monthly rent payment
            $nextMonth = getNextUnpaidMonth($conn, $payment_request['booking_id']);
            
            // Record monthly payment
            recordMonthlyPayment(
                $conn, 
                $payment_request['booking_id'], 
                $nextMonth, 
                $payment_request['amount'], 
                'M-Pesa', 
                $transaction_id, 
                $manual_receipt, 
                'Manual Monthly Payment - Checkout Request: ' . $payment_request['checkout_request_id']
            );
        }
        
        echo "<p>✅ Payment marked as successful!</p>";
        echo "<p>Receipt Number: <strong>$manual_receipt</strong></p>";
        echo "<p>Transaction ID: <strong>$transaction_id</strong></p>";
        
        // Verify the update
        $stmt = $conn->prepare("
            SELECT status, result_code, result_desc, mpesa_receipt_number, updated_at
            FROM mpesa_payment_requests 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $payment_request['id']);
        $stmt->execute();
        $updated_request = $stmt->get_result()->fetch_assoc();
        
        echo "<h3>Updated Payment Request:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Status</th><th>Result Code</th><th>Result Desc</th><th>Receipt Number</th><th>Updated</th></tr>";
        echo "<tr>";
        echo "<td style='color: green;'>" . $updated_request['status'] . "</td>";
        echo "<td>" . $updated_request['result_code'] . "</td>";
        echo "<td>" . $updated_request['result_desc'] . "</td>";
        echo "<td>" . $updated_request['mpesa_receipt_number'] . "</td>";
        echo "<td>" . $updated_request['updated_at'] . "</td>";
        echo "</tr>";
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 