<?php
/**
 * View Error Logs
 * Simple script to view error logs and test payment processing
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>View Error Logs</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 36;

echo "<h3>Testing with Booking ID: $testBookingId</h3>";

// Test the payment processing and generate some error logs
echo "<h4>Testing Payment Processing:</h4>";

try {
    // Database connection
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/monthly_payment_tracker.php';
    
    $tracker = new MonthlyPaymentTracker($conn);
    
    // Get next payment due
    $nextPaymentDue = $tracker->getNextPaymentDue($testBookingId);
    
    if ($nextPaymentDue) {
        echo "<p style='color: green;'>✅ Next payment due: " . date('F Y', strtotime($nextPaymentDue['month'])) . " - KSh " . number_format($nextPaymentDue['amount'], 2) . "</p>";
        
        // Test payment allocation to generate logs
        if (isset($_GET['test_payment'])) {
            echo "<h5>Testing Payment Allocation:</h5>";
            
            $paymentAmount = $nextPaymentDue['amount'];
            $paymentDate = date('Y-m-d H:i:s');
            $paymentMethod = 'Test Payment';
            $transactionId = 'TEST_' . time();
            
            echo "<p>Allocating payment:</p>";
            echo "<ul>";
            echo "<li>Amount: KSh " . number_format($paymentAmount, 2) . "</li>";
            echo "<li>Date: " . $paymentDate . "</li>";
            echo "<li>Method: " . $paymentMethod . "</li>";
            echo "<li>Transaction ID: " . $transactionId . "</li>";
            echo "</ul>";
            
            // This will generate error logs
            $result = $tracker->allocatePayment(
                $testBookingId,
                $paymentAmount,
                $paymentDate,
                $paymentMethod,
                $transactionId
            );
            
            if ($result['success']) {
                echo "<p style='color: green;'>✅ Payment allocated successfully!</p>";
                echo "<p>Message: " . $result['message'] . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Payment failed: " . $result['message'] . "</p>";
            }
        } else {
            echo "<p><a href='?booking_id=$testBookingId&test_payment=1' class='btn btn-primary'>Test Payment Allocation</a></p>";
        }
        
    } else {
        echo "<p style='color: green;'>✅ All payments completed!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Show common error log locations
echo "<h4>Error Log Locations:</h4>";
echo "<ul>";
echo "<li><strong>PHP Error Log:</strong> C:\\xampp\\php\\logs\\php_error_log</li>";
echo "<li><strong>Apache Error Log:</strong> C:\\xampp\\apache\\logs\\error.log</li>";
echo "<li><strong>Apache Access Log:</strong> C:\\xampp\\apache\\logs\\access.log</li>";
echo "</ul>";

// Try to read error logs if they exist
echo "<h4>Recent Error Log Entries:</h4>";

$logFiles = [
    'C:\\xampp\\php\\logs\\php_error_log',
    'C:\\xampp\\apache\\logs\\error.log',
    'C:\\xampp\\apache\\logs\\access.log'
];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "<h5>$logFile:</h5>";
        $lines = file($logFile);
        if ($lines) {
            // Show last 20 lines
            $recentLines = array_slice($lines, -20);
            echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
            foreach ($recentLines as $line) {
                echo htmlspecialchars($line);
            }
            echo "</pre>";
        } else {
            echo "<p>Log file is empty</p>";
        }
    } else {
        echo "<p>Log file not found: $logFile</p>";
    }
}

// Show current PHP error log setting
echo "<h4>PHP Error Log Configuration:</h4>";
echo "<ul>";
echo "<li><strong>error_log:</strong> " . ini_get('error_log') . "</li>";
echo "<li><strong>log_errors:</strong> " . (ini_get('log_errors') ? 'On' : 'Off') . "</li>";
echo "<li><strong>display_errors:</strong> " . (ini_get('display_errors') ? 'On' : 'Off') . "</li>";
echo "</ul>";

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$testBookingId'>Go to Payment Page</a></li>";
echo "<li><a href='check_payment_logs.php?booking_id=$testBookingId'>Check Payment Logs</a></li>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "</ul>";
?>

<style>
pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 5px;
    overflow-x: auto;
    font-size: 12px;
    line-height: 1.4;
}
.btn {
    margin: 10px 0;
}
</style> 