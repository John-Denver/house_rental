<?php
/**
 * Populate Monthly Payments
 * Create initial monthly payment records for existing bookings
 */

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
    echo "Database connection failed: " . $e->getMessage();
    exit;
}

echo "<h2>Populate Monthly Payments</h2>";

// Check if monthly_rent_payments table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'monthly_rent_payments'");
if ($tableExists->num_rows === 0) {
    echo "<p style='color: red;'>❌ monthly_rent_payments table does not exist!</p>";
    echo "<p>Please run the table structure test first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ monthly_rent_payments table exists</p>";

// Get all confirmed/active bookings
$stmt = $conn->prepare("
    SELECT 
        rb.id as booking_id,
        rb.user_id,
        rb.house_id,
        rb.start_date,
        rb.end_date,
        rb.status,
        rb.payment_status,
        rb.monthly_rent,
        rb.security_deposit,
        h.price as house_price
    FROM rental_bookings rb
    LEFT JOIN houses h ON rb.house_id = h.id
    WHERE rb.status IN ('confirmed', 'paid', 'active', 'completed')
    ORDER BY rb.id
");
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<h3>Found " . count($bookings) . " active bookings</h3>";

if (empty($bookings)) {
    echo "<p>No active bookings found. Please create some bookings first.</p>";
    exit;
}

// Display bookings
echo "<h3>Bookings to Process:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Booking ID</th><th>User ID</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Monthly Rent</th>";
echo "</tr>";

foreach ($bookings as $booking) {
    echo "<tr>";
    echo "<td>" . $booking['booking_id'] . "</td>";
    echo "<td>" . $booking['user_id'] . "</td>";
    echo "<td>" . $booking['start_date'] . "</td>";
    echo "<td>" . $booking['end_date'] . "</td>";
    echo "<td>" . $booking['status'] . "</td>";
    echo "<td>KSh " . number_format($booking['monthly_rent'] ?? $booking['house_price'], 2) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<form method='POST'>";
echo "<button type='submit' name='populate' class='btn btn-primary'>Populate Monthly Payments</button>";
echo "</form>";

if (isset($_POST['populate'])) {
    echo "<h3>Populating Monthly Payments...</h3>";
    
    $totalCreated = 0;
    $totalBookings = 0;
    
    foreach ($bookings as $booking) {
        $bookingId = $booking['booking_id'];
        $monthlyRent = $booking['monthly_rent'] ?? $booking['house_price'];
        $securityDeposit = $booking['security_deposit'] ?? 0;
        
        echo "<h4>Processing Booking ID: $bookingId</h4>";
        
        // Check if this booking already has monthly payment records
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM monthly_rent_payments WHERE booking_id = ?");
        $checkStmt->bind_param('i', $bookingId);
        $checkStmt->execute();
        $existingCount = $checkStmt->get_result()->fetch_assoc()['count'];
        
        if ($existingCount > 0) {
            echo "<p>✅ Booking $bookingId already has $existingCount payment records</p>";
            continue;
        }
        
        // Generate monthly payment records for the booking period
        $start = new DateTime($booking['start_date']);
        $end = new DateTime($booking['end_date']);
        $current = clone $start;
        
        $recordsCreated = 0;
        
        while ($current <= $end) {
            $month = $current->format('Y-m-01');
            
            // Determine if this is the first payment
            $isFirstPayment = ($current == $start);
            
            // Determine payment status based on booking status
            $paymentStatus = 'unpaid';
            if ($booking['payment_status'] === 'paid' || $booking['payment_status'] === 'completed') {
                $paymentStatus = 'paid';
            }
            
            // Calculate amount for first payment (rent + security deposit)
            $amount = $monthlyRent;
            if ($isFirstPayment && $securityDeposit > 0) {
                $amount += $securityDeposit;
            }
            
            // Insert monthly payment record
            $insertStmt = $conn->prepare("
                INSERT INTO monthly_rent_payments 
                (booking_id, month, amount, status, payment_type, is_first_payment, 
                 security_deposit_amount, monthly_rent_amount, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $paymentType = $isFirstPayment ? 'initial_payment' : 'monthly_rent';
            $notes = $isFirstPayment ? 'Initial payment including security deposit' : 'Monthly rent payment';
            
            // Store values in variables to avoid reference issues
            $isFirstPaymentValue = $isFirstPayment ? 1 : 0;
            $securityDepositValue = $isFirstPayment ? $securityDeposit : 0;
            
            $insertStmt->bind_param('isdsisddss', 
                $bookingId,
                $month,
                $amount,
                $paymentStatus,
                $paymentType,
                $isFirstPaymentValue,
                $securityDepositValue,
                $monthlyRent,
                $notes
            );
            
            if ($insertStmt->execute()) {
                $recordsCreated++;
                echo "<p>✅ Created payment record for " . date('F Y', strtotime($month)) . " - KSh " . number_format($amount, 2) . "</p>";
            } else {
                echo "<p>❌ Failed to create payment record for " . date('F Y', strtotime($month)) . " - " . $insertStmt->error . "</p>";
            }
            
            $current->add(new DateInterval('P1M'));
        }
        
        $totalCreated += $recordsCreated;
        $totalBookings++;
        
        echo "<p><strong>Created $recordsCreated payment records for booking $bookingId</strong></p>";
        echo "<hr>";
    }
    
    echo "<h3>Summary</h3>";
    echo "<p>✅ Processed $totalBookings bookings</p>";
    echo "<p>✅ Created $totalCreated payment records</p>";
    
    echo "<div class='alert alert-success'>";
    echo "<strong>Monthly payments populated successfully!</strong><br>";
    echo "You can now test the monthly payments functionality.";
    echo "</div>";
    
    echo "<h3>Test Links:</h3>";
    echo "<ul>";
    echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
    echo "<li><a href='debug_monthly_payments.php'>Debug Monthly Payments</a></li>";
    echo "<li><a href='test_ajax.php'>Test AJAX Call</a></li>";
    echo "</ul>";
}

echo "<h3>Manual SQL (if needed):</h3>";
echo "<p>If the automatic population doesn't work, you can run these SQL commands manually:</p>";
echo "<pre>";
foreach ($bookings as $booking) {
    $bookingId = $booking['booking_id'];
    $monthlyRent = $booking['monthly_rent'] ?? $booking['house_price'];
    $securityDeposit = $booking['security_deposit'] ?? 0;
    $startDate = $booking['start_date'];
    
    echo "-- Booking ID: $bookingId\n";
    echo "INSERT INTO monthly_rent_payments (booking_id, month, amount, status, payment_type, is_first_payment, security_deposit_amount, monthly_rent_amount, notes) VALUES ($bookingId, '$startDate', " . ($monthlyRent + $securityDeposit) . ", 'unpaid', 'initial_payment', 1, $securityDeposit, $monthlyRent, 'Initial payment including security deposit');\n";
    echo "\n";
}
echo "</pre>";
?> 