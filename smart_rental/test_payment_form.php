<?php
/**
 * Test Payment Form
 * Simple test to verify payment form submission works
 */

session_start();
require_once '../config/db.php';
require_once 'includes/payment_tracking_helper.php';

echo "<h2>Test Payment Form</h2>";

$bookingId = 29; // Your test booking ID

echo "<h3>Testing Booking ID: $bookingId</h3>";

// Get booking details
$stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<p>❌ Booking not found</p>";
    exit;
}

$hasFirstPayment = hasFirstPaymentBeenMade($conn, $bookingId);
$paymentType = 'prepayment'; // Force prepayment for testing
$totalAmount = $booking['monthly_rent'];

echo "<h4>Test Form:</h4>";
echo "<form method='POST' action=''>";
echo "<input type='hidden' name='amount' value='$totalAmount'>";
echo "<input type='hidden' name='payment_method' value='manual'>";
echo "<input type='hidden' name='payment_type' value='$paymentType'>";
echo "<input type='hidden' name='transaction_id' value='TEST-" . time() . "'>";
echo "<input type='hidden' name='notes' value='Test payment form'>";
echo "<p><strong>Amount:</strong> KSh " . number_format($totalAmount, 2) . "</p>";
echo "<p><strong>Payment Type:</strong> $paymentType</p>";
echo "<p><strong>Has First Payment:</strong> " . ($hasFirstPayment ? 'Yes' : 'No') . "</p>";
echo "<button type='submit' class='btn btn-primary'>Submit Test Payment</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h4>Form Submitted!</h4>";
    
    // Simulate the exact same logic as booking_payment.php
    $formPaymentType = filter_input(INPUT_POST, 'payment_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($formPaymentType) {
        $paymentType = $formPaymentType;
    }
    
    echo "<p><strong>Form Payment Type:</strong> $formPaymentType</p>";
    echo "<p><strong>Final Payment Type:</strong> $paymentType</p>";
    
    $paymentData = [
        'booking_id' => $bookingId,
        'amount' => filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT),
        'payment_method' => filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
        'transaction_id' => filter_input(INPUT_POST, 'transaction_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
        'notes' => filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    ];
    
    echo "<p><strong>Payment Data:</strong></p>";
    echo "<ul>";
    echo "<li>Amount: KSh " . number_format($paymentData['amount'], 2) . "</li>";
    echo "<li>Method: " . $paymentData['payment_method'] . "</li>";
    echo "<li>Transaction ID: " . $paymentData['transaction_id'] . "</li>";
    echo "<li>Notes: " . $paymentData['notes'] . "</li>";
    echo "</ul>";
    
    if ($paymentType === 'prepayment') {
        echo "<p style='color: blue;'>→ Processing as PREPAYMENT</p>";
        
        try {
            $result = recordPrePayment(
                $conn,
                $bookingId,
                $paymentData['amount'],
                $paymentData['payment_method'],
                $paymentData['transaction_id'],
                null,
                $paymentData['notes']
            );
            
            if ($result['success']) {
                echo "<p style='color: green;'>✅ Payment successful!</p>";
                echo "<p><strong>Month Paid:</strong> " . date('F Y', strtotime($result['month_paid'])) . "</p>";
                echo "<p><strong>Message:</strong> " . $result['message'] . "</p>";
                
                // Check if the record was updated
                $stmt = $conn->prepare("
                    SELECT month, status, payment_date 
                    FROM monthly_rent_payments 
                    WHERE booking_id = ? AND month = ?
                ");
                $stmt->bind_param('is', $bookingId, $result['month_paid']);
                $stmt->execute();
                $updatedPayment = $stmt->get_result()->fetch_assoc();
                
                if ($updatedPayment) {
                    echo "<p style='color: green;'>✅ Monthly payment record updated!</p>";
                    echo "<ul>";
                    echo "<li>Month: " . date('F Y', strtotime($updatedPayment['month'])) . "</li>";
                    echo "<li>Status: " . $updatedPayment['status'] . "</li>";
                    echo "<li>Payment Date: " . ($updatedPayment['payment_date'] ? date('M d, Y H:i:s', strtotime($updatedPayment['payment_date'])) : 'None') . "</li>";
                    echo "</ul>";
                } else {
                    echo "<p style='color: red;'>❌ Monthly payment record not found!</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ Payment failed!</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>→ Processing as INITIAL PAYMENT</p>";
    }
}

echo "<h4>Test Links:</h4>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$bookingId&type=prepayment'>Go to Actual Payment Page</a></li>";
echo "<li><a href='check_monthly_payments.php?booking_id=$bookingId'>Check Monthly Payments</a></li>";
echo "</ul>";
?> 