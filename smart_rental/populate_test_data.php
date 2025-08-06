<?php
/**
 * Populate Test Data for Monthly Rent Payments
 * This script will create test data in the monthly_rent_payments table
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

echo "<h2>Populate Test Data for Monthly Rent Payments</h2>";

// Check if monthly_rent_payments table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'monthly_rent_payments'");
if ($tableExists->num_rows === 0) {
    echo "<p style='color: red;'>❌ monthly_rent_payments table does not exist!</p>";
    echo "<p>Creating the table first...</p>";
    
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS `monthly_rent_payments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `booking_id` int(11) NOT NULL,
      `month` date NOT NULL COMMENT 'First day of the month (YYYY-MM-01)',
      `amount` decimal(15,2) NOT NULL,
      `status` enum('paid','unpaid','overdue') NOT NULL DEFAULT 'unpaid',
      `payment_date` datetime DEFAULT NULL,
      `payment_method` varchar(50) DEFAULT NULL,
      `transaction_id` varchar(255) DEFAULT NULL,
      `mpesa_receipt_number` varchar(50) DEFAULT NULL,
      `notes` text DEFAULT NULL,
      `is_first_payment` tinyint(1) DEFAULT 0,
      `payment_type` varchar(50) DEFAULT 'monthly_rent',
      `security_deposit_amount` decimal(15,2) DEFAULT 0,
      `monthly_rent_amount` decimal(15,2) DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_booking_month` (`booking_id`, `month`),
      KEY `idx_booking_id` (`booking_id`),
      KEY `idx_month` (`month`),
      KEY `idx_status` (`status`),
      KEY `idx_payment_date` (`payment_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($createTableSQL)) {
        echo "<p style='color: green;'>✅ monthly_rent_payments table created successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating table: " . $conn->error . "</p>";
        exit;
    }
} else {
    echo "<p style='color: green;'>✅ monthly_rent_payments table exists</p>";
}

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
    echo "<p>No active bookings found. Creating a test booking first...</p>";
    
    // Create a test booking
    $testUserId = 1; // Assuming user ID 1 exists
    $testHouseId = 1; // Assuming house ID 1 exists
    
    // Check if test user exists
    $userExists = $conn->query("SELECT id FROM users WHERE id = $testUserId");
    if ($userExists->num_rows === 0) {
        echo "<p style='color: red;'>❌ Test user (ID: $testUserId) does not exist. Please create a user first.</p>";
        exit;
    }
    
    // Check if test house exists
    $houseExists = $conn->query("SELECT id FROM houses WHERE id = $testHouseId");
    if ($houseExists->num_rows === 0) {
        echo "<p style='color: red;'>❌ Test house (ID: $testHouseId) does not exist. Please create a house first.</p>";
        exit;
    }
    
    // Create test booking
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+6 months'));
    $monthlyRent = 50000; // KSh 50,000
    $securityDeposit = 100000; // KSh 100,000
    
    $insertBooking = $conn->prepare("
        INSERT INTO rental_bookings (user_id, house_id, start_date, end_date, status, payment_status, monthly_rent, security_deposit)
        VALUES (?, ?, ?, ?, 'confirmed', 'paid', ?, ?)
    ");
    $insertBooking->bind_param('iissdd', $testUserId, $testHouseId, $startDate, $endDate, $monthlyRent, $securityDeposit);
    
    if ($insertBooking->execute()) {
        $testBookingId = $conn->insert_id;
        echo "<p style='color: green;'>✅ Created test booking (ID: $testBookingId)</p>";
        
        // Add to bookings array for processing
        $bookings = [[
            'booking_id' => $testBookingId,
            'user_id' => $testUserId,
            'house_id' => $testHouseId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'monthly_rent' => $monthlyRent,
            'security_deposit' => $securityDeposit,
            'house_price' => $monthlyRent
        ]];
    } else {
        echo "<p style='color: red;'>❌ Failed to create test booking: " . $insertBooking->error . "</p>";
        exit;
    }
}

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
    echo "<li><a href='test_db_connection.php'>Test Database Connection</a></li>";
    echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
    echo "<li><a href='get_monthly_payments.php'>Test Monthly Payments API</a></li>";
    echo "</ul>";
}

echo "<h3>Current Status:</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM monthly_rent_payments");
$totalRecords = $result->fetch_assoc()['count'];
echo "<p>Total records in monthly_rent_payments: $totalRecords</p>";

if ($totalRecords > 0) {
    echo "<h4>Sample Records:</h4>";
    $sampleRecords = $conn->query("SELECT * FROM monthly_rent_payments ORDER BY id DESC LIMIT 5");
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Booking ID</th><th>Month</th><th>Amount</th><th>Status</th><th>Type</th>";
    echo "</tr>";
    while ($row = $sampleRecords->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['booking_id'] . "</td>";
        echo "<td>" . date('F Y', strtotime($row['month'])) . "</td>";
        echo "<td>KSh " . number_format($row['amount'], 2) . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['payment_type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?> 