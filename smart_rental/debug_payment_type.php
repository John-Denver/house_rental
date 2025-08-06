<?php
/**
 * Debug Payment Type Determination
 * Tests the exact logic from booking_payment.php
 */

session_start();
require_once '../config/db.php';
require_once 'includes/payment_tracking_helper.php';

echo "<h2>Debug Payment Type Determination</h2>";

// Get booking ID from URL parameter
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;
$paymentType = $_GET['type'] ?? 'initial'; // Simulate the URL parameter

if (!$bookingId) {
    echo "<p>Please provide a booking_id parameter: ?booking_id=X&type=prepayment</p>";
    exit;
}

echo "<h3>Testing Booking ID: $bookingId</h3>";
echo "<h3>URL Payment Type: $paymentType</h3>";

try {
    // Get booking details (simulate the booking controller)
    $stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        echo "<p>❌ Booking not found</p>";
        exit;
    }

    echo "<h4>1. Booking Details:</h4>";
    echo "<ul>";
    echo "<li>Monthly Rent: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
    echo "<li>Security Deposit: KSh " . number_format($booking['security_deposit'] ?? 0, 2) . "</li>";
    echo "<li>Status: " . $booking['status'] . "</li>";
    echo "<li>Payment Status: " . $booking['payment_status'] . "</li>";
    echo "</ul>";

    // Check if first payment has been made
    $hasFirstPayment = hasFirstPaymentBeenMade($conn, $bookingId);
    echo "<h4>2. First Payment Check:</h4>";
    echo "<p><strong>Has First Payment:</strong> " . ($hasFirstPayment ? 'Yes' : 'No') . "</p>";

    // Simulate the exact logic from booking_payment.php
    echo "<h4>3. Payment Type Determination Logic:</h4>";
    
    $additionalFees = $booking['additional_fees'] ?? 0;
    $monthlyRent = floatval($booking['monthly_rent']);
    $securityDeposit = floatval($booking['security_deposit'] ?? 0);
    
    echo "<p><strong>Variables:</strong></p>";
    echo "<ul>";
    echo "<li>URL Payment Type: $paymentType</li>";
    echo "<li>Has First Payment: " . ($hasFirstPayment ? 'Yes' : 'No') . "</li>";
    echo "<li>Monthly Rent: KSh " . number_format($monthlyRent, 2) . "</li>";
    echo "<li>Security Deposit: KSh " . number_format($securityDeposit, 2) . "</li>";
    echo "<li>Additional Fees: KSh " . number_format($additionalFees, 2) . "</li>";
    echo "</ul>";
    
    echo "<p><strong>Logic Flow:</strong></p>";
    
    // Test the exact same logic
    if ($paymentType === 'prepayment') {
        echo "<p style='color: blue;'>→ Entering: if (\$paymentType === 'prepayment')</p>";
        
        if (!$hasFirstPayment) {
            echo "<p style='color: red;'>→ Throwing Exception: Initial payment must be completed before making pre-payments</p>";
            throw new Exception('Initial payment must be completed before making pre-payments');
        }
        
        $totalAmount = $monthlyRent + $additionalFees;
        $paymentType = 'prepayment';
        $paymentDescription = 'Pre-Payment for Next Month';
        
        echo "<p style='color: green;'>→ Setting: \$paymentType = 'prepayment'</p>";
        echo "<p style='color: green;'>→ Setting: \$paymentDescription = 'Pre-Payment for Next Month'</p>";
        echo "<p style='color: green;'>→ Setting: \$totalAmount = KSh " . number_format($totalAmount, 2) . "</p>";
        
    } elseif (!$hasFirstPayment) {
        echo "<p style='color: blue;'>→ Entering: elseif (!\$hasFirstPayment)</p>";
        
        $totalAmount = $monthlyRent + $securityDeposit + $additionalFees;
        $paymentType = 'initial_payment';
        $paymentDescription = 'Initial Payment (Security Deposit + First Month Rent)';
        
        echo "<p style='color: green;'>→ Setting: \$paymentType = 'initial_payment'</p>";
        echo "<p style='color: green;'>→ Setting: \$paymentDescription = 'Initial Payment (Security Deposit + First Month Rent)'</p>";
        echo "<p style='color: green;'>→ Setting: \$totalAmount = KSh " . number_format($totalAmount, 2) . "</p>";
        
    } else {
        echo "<p style='color: blue;'>→ Entering: else (subsequent payments)</p>";
        
        $totalAmount = $monthlyRent + $additionalFees;
        $paymentType = 'prepayment';
        
        // Get the next unpaid month for the description
        $nextUnpaidMonth = getNextUnpaidMonth($conn, $bookingId);
        $paymentDescription = 'Pre-Payment for ' . date('F Y', strtotime($nextUnpaidMonth));
        
        echo "<p style='color: green;'>→ Setting: \$paymentType = 'prepayment' (OVERRIDING URL PARAMETER!)</p>";
        echo "<p style='color: green;'>→ Setting: \$paymentDescription = '$paymentDescription'</p>";
        echo "<p style='color: green;'>→ Setting: \$totalAmount = KSh " . number_format($totalAmount, 2) . "</p>";
    }
    
    echo "<h4>4. Final Result:</h4>";
    echo "<ul>";
    echo "<li><strong>Final Payment Type:</strong> $paymentType</li>";
    echo "<li><strong>Payment Description:</strong> $paymentDescription</li>";
    echo "<li><strong>Total Amount:</strong> KSh " . number_format($totalAmount, 2) . "</li>";
    echo "</ul>";
    
    if (isset($_GET['test_submit'])) {
        echo "<h4>5. Test Form Submission:</h4>";
        
        // Simulate form data
        $paymentData = [
            'booking_id' => $bookingId,
            'amount' => $totalAmount,
            'payment_method' => 'manual',
            'transaction_id' => 'DEBUG-TEST-' . time(),
            'notes' => 'Debug test payment'
        ];
        
        echo "<p><strong>Form Data:</strong></p>";
        echo "<ul>";
        echo "<li>Amount: KSh " . number_format($paymentData['amount'], 2) . "</li>";
        echo "<li>Method: " . $paymentData['payment_method'] . "</li>";
        echo "<li>Transaction ID: " . $paymentData['transaction_id'] . "</li>";
        echo "</ul>";
        
        echo "<p><strong>Processing Logic:</strong></p>";
        
        if ($paymentType === 'prepayment') {
            echo "<p style='color: blue;'>→ Calling: recordPrePayment()</p>";
            
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
                    echo "<p style='color: green;'>✅ recordPrePayment() successful!</p>";
                    echo "<p><strong>Month Paid:</strong> " . date('F Y', strtotime($result['month_paid'])) . "</p>";
                    echo "<p><strong>Message:</strong> " . $result['message'] . "</p>";
                } else {
                    echo "<p style='color: red;'>❌ recordPrePayment() failed!</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
            }
            
        } elseif ($paymentType === 'initial_payment') {
            echo "<p style='color: blue;'>→ Calling: recordInitialPayment()</p>";
            echo "<p style='color: orange;'>→ This would call recordInitialPayment()</p>";
        } else {
            echo "<p style='color: blue;'>→ Calling: bookingController->processPayment()</p>";
            echo "<p style='color: orange;'>→ This would call the old processPayment() method</p>";
        }
    } else {
        echo "<p><a href='?booking_id=$bookingId&type=$paymentType&test_submit=1' class='btn btn-primary'>Test Form Submission</a></p>";
    }

    echo "<h4>6. Test Links:</h4>";
    echo "<ul>";
    echo "<li><a href='booking_payment.php?id=$bookingId&type=prepayment'>Go to Payment Page (Prepayment)</a></li>";
    echo "<li><a href='booking_payment.php?id=$bookingId&type=initial'>Go to Payment Page (Initial)</a></li>";
    echo "<li><a href='debug_prepayment.php?booking_id=$bookingId'>Debug Pre-Payment</a></li>";
    echo "<li><a href='check_monthly_payments.php?booking_id=$bookingId'>Check Monthly Payments</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 