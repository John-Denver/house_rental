<?php
/**
 * Fix Payment Sync
 * Sync initial payments from rental_bookings to monthly_rent_payments table
 */

session_start();

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
} catch (Exception $e) {
    echo "<h2>Database Connection: ❌ Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Fix Payment Sync</h2>";

// Get booking ID from URL parameter
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;

if (!$bookingId) {
    echo "<p>Please provide a booking_id parameter: ?booking_id=X</p>";
    exit;
}

echo "<h3>Booking ID: $bookingId</h3>";

// Check booking details
$stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<p>❌ Booking not found</p>";
    exit;
}

echo "<h4>Booking Details:</h4>";
echo "<ul>";
echo "<li>Status: " . $booking['status'] . "</li>";
echo "<li>Payment Status: " . $booking['payment_status'] . "</li>";
echo "<li>Start Date: " . $booking['start_date'] . "</li>";
echo "<li>Monthly Rent: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
echo "<li>Security Deposit: KSh " . number_format($booking['security_deposit'] ?? 0, 2) . "</li>";
echo "</ul>";

// Check if initial payment exists in monthly_rent_payments
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM monthly_rent_payments 
    WHERE booking_id = ? AND is_first_payment = 1 AND status = 'paid'
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$hasInitialPaymentRecord = ($result['count'] > 0);

echo "<h4>Payment Sync Status:</h4>";
echo "<ul>";
echo "<li>Rental Bookings Payment Status: " . $booking['payment_status'] . "</li>";
echo "<li>Has Initial Payment Record in monthly_rent_payments: " . ($hasInitialPaymentRecord ? 'Yes' : 'No') . "</li>";
echo "</ul>";

// Check if we need to sync
$needsSync = ($booking['payment_status'] === 'paid' || $booking['payment_status'] === 'completed') && !$hasInitialPaymentRecord;

if ($needsSync) {
    echo "<h4>🔧 Payment Sync Required</h4>";
    echo "<p>The booking shows as paid but doesn't have a corresponding record in monthly_rent_payments table.</p>";
    
    echo "<form method='POST'>";
    echo "<button type='submit' name='fix_sync' class='btn btn-warning'>Fix Payment Sync</button>";
    echo "</form>";
    
    if (isset($_POST['fix_sync'])) {
        try {
            // Get the first month of the booking
            $firstMonth = date('Y-m-01', strtotime($booking['start_date']));
            $paymentDate = $booking['updated_at'] ?? date('Y-m-d H:i:s');
            
            // Insert initial payment record
            $stmt = $conn->prepare("
                INSERT INTO monthly_rent_payments
                (booking_id, month, amount, status, payment_type, is_first_payment, 
                 security_deposit_amount, monthly_rent_amount, payment_date, 
                 payment_method, notes)
                VALUES (?, ?, ?, 'paid', 'initial_payment', 1, ?, ?, ?, 'manual', 'Auto-synced from rental_bookings')
                ON DUPLICATE KEY UPDATE
                status = 'paid',
                payment_type = 'initial_payment',
                is_first_payment = 1,
                security_deposit_amount = VALUES(security_deposit_amount),
                monthly_rent_amount = VALUES(monthly_rent_amount),
                payment_date = VALUES(payment_date),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            // Store values in variables to avoid reference issues
            $monthlyRent = $booking['monthly_rent'];
            $securityDeposit = $booking['security_deposit'] ?? 0;
            
            $stmt->bind_param('isddds',
                $bookingId,
                $firstMonth,
                $monthlyRent, // amount (monthly rent only)
                $securityDeposit,
                $monthlyRent,
                $paymentDate
            );
            
            if ($stmt->execute()) {
                echo "<div class='alert alert-success'>";
                echo "<strong>✅ Payment sync completed successfully!</strong><br>";
                echo "Initial payment record created for " . date('F Y', strtotime($firstMonth));
                echo "</div>";
                
                // Refresh the page to show updated status
                echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
            } else {
                echo "<div class='alert alert-danger'>";
                echo "<strong>❌ Error syncing payment:</strong> " . $stmt->error;
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>";
            echo "<strong>❌ Error:</strong> " . $e->getMessage();
            echo "</div>";
        }
    }
} else {
    echo "<h4>✅ Payment Sync Status: OK</h4>";
    echo "<p>No sync required. Payment records are consistent.</p>";
}

// Show current payment status
echo "<h4>Current Payment Status:</h4>";
$stmt = $conn->prepare("
    SELECT month, status, payment_date, amount, is_first_payment, payment_type
    FROM monthly_rent_payments 
    WHERE booking_id = ? 
    ORDER BY month ASC
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($payments) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Month</th><th>Status</th><th>Payment Date</th><th>Amount</th><th>First Payment</th><th>Type</th>";
    echo "</tr>";
    foreach ($payments as $payment) {
        $statusColor = $payment['status'] === 'paid' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $payment['status'] . "</td>";
        echo "<td>" . ($payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-') . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . $payment['payment_type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment records found.</p>";
}

echo "<h4>Test Links:</h4>";
echo "<ul>";
echo "<li><a href='booking_details.php?id=$bookingId'>View Booking Details</a></li>";
echo "<li><a href='debug_prepayment_button.php?booking_id=$bookingId'>Debug Pre-Payment Button</a></li>";
echo "<li><a href='my_bookings.php'>View My Bookings</a></li>";
echo "</ul>";
?> 