<?php
/**
 * Get Monthly Payments AJAX Endpoint
 * Returns monthly payment history for a booking
 */

// Prevent any output before JSON response
ob_start();

// Disable error display but keep logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON content type early
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log session information for debugging
error_log("Session data: " . json_encode($_SESSION));

// Use the same database connection as config
require_once __DIR__ . '/config/config.php';

try {
    // Use the existing connection from config.php
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection not available");
    }
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ]);
    exit;
}

// Clear any output buffer
ob_clean();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in. Session data: " . json_encode($_SESSION));
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get booking ID
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId) {
    error_log("Invalid booking ID received: " . ($_POST['booking_id'] ?? 'not set'));
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

error_log("Processing monthly payments request for booking ID: $bookingId, User ID: " . ($_SESSION['user_id'] ?? 'not set'));

try {
    // First, check if the monthly_rent_payments table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'monthly_rent_payments'");
    if ($tableExists->num_rows === 0) {
        // Create the table if it doesn't exist
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `monthly_rent_payments` (
          `id` int(11) NOT NULL,
          `booking_id` int(11) NOT NULL,
          `month` date NOT NULL COMMENT 'First day of the month (YYYY-MM-01)',
          `amount` decimal(15,2) NOT NULL,
          `status` enum('paid','unpaid','overdue') NOT NULL DEFAULT 'unpaid',
          `payment_type` varchar(50) DEFAULT 'monthly_rent',
          `is_first_payment` tinyint(1) DEFAULT 0,
          `security_deposit_amount` decimal(15,2) DEFAULT 0.00,
          `monthly_rent_amount` decimal(15,2) DEFAULT 0.00,
          `payment_date` datetime DEFAULT NULL,
          `payment_method` varchar(50) DEFAULT NULL,
          `transaction_id` varchar(255) DEFAULT NULL,
          `mpesa_receipt_number` varchar(50) DEFAULT NULL,
          `notes` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        if (!$conn->query($createTableSQL)) {
            throw new Exception("Failed to create monthly_rent_payments table: " . $conn->error);
        }
    }
    
    // Verify that the current user owns this booking
    $stmt = $conn->prepare("
        SELECT id, user_id, house_id, start_date, end_date, status
        FROM rental_bookings 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param('ii', $bookingId, $_SESSION['user_id']);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        error_log("Booking not found or unauthorized. Booking ID: $bookingId, User ID: " . $_SESSION['user_id']);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found or unauthorized']);
        exit;
    }
    
    // Get monthly payments for this booking (including initial payments)
    $stmt = $conn->prepare("
        SELECT 
            month,
            amount,
            status,
            payment_date,
            payment_method,
            mpesa_receipt_number,
            notes,
            is_first_payment,
            payment_type
        FROM monthly_rent_payments 
        WHERE booking_id = ?
        ORDER BY month ASC
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $monthlyPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Also check booking_payments table for any payments
    $stmt = $conn->prepare("
        SELECT 
            payment_date,
            amount,
            payment_method,
            transaction_id,
            notes,
            status
        FROM booking_payments 
        WHERE booking_id = ? AND status = 'completed'
        ORDER BY payment_date ASC
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $bookingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Log for debugging
    error_log("Monthly payments for booking $bookingId: " . json_encode($monthlyPayments));
    error_log("Booking payments for booking $bookingId: " . json_encode($bookingPayments));
    
    // If no monthly payments exist but booking payments exist, create monthly payment records
    if (empty($monthlyPayments) && !empty($bookingPayments)) {
        // Get property details to get monthly rent
        $stmt = $conn->prepare("
            SELECT price FROM houses WHERE id = ?
        ");
        $stmt->bind_param('i', $booking['house_id']);
        $stmt->execute();
        $property = $stmt->get_result()->fetch_assoc();
        
        if ($property) {
            // For each booking payment, create a corresponding monthly payment record
            foreach ($bookingPayments as $bookingPayment) {
                $paymentDate = new DateTime($bookingPayment['payment_date']);
                $month = $paymentDate->format('Y-m-01');
                
                // Check if record already exists
                $checkStmt = $conn->prepare("
                    SELECT id FROM monthly_rent_payments 
                    WHERE booking_id = ? AND month = ?
                ");
                $checkStmt->bind_param('is', $bookingId, $month);
                $checkStmt->execute();
                
                if (!$checkStmt->get_result()->fetch_assoc()) {
                    // Insert monthly payment record based on booking payment
                    $insertStmt = $conn->prepare("
                        INSERT INTO monthly_rent_payments 
                        (booking_id, month, amount, status, payment_date, payment_method, mpesa_receipt_number, notes, is_first_payment, payment_type)
                        VALUES (?, ?, ?, 'paid', ?, ?, ?, ?, 1, 'initial_payment')
                    ");
                    $insertStmt->bind_param('isdsisss', 
                        $bookingId, 
                        $month, 
                        $bookingPayment['amount'],
                        $bookingPayment['payment_date'],
                        $bookingPayment['payment_method'],
                        $bookingPayment['transaction_id'],
                        $bookingPayment['notes']
                    );
                    $insertStmt->execute();
                }
            }
            
            // Get the updated monthly payments
            $stmt = $conn->prepare("
                SELECT 
                    month,
                    amount,
                    status,
                    payment_date,
                    payment_method,
                    mpesa_receipt_number,
                    notes,
                    is_first_payment,
                    payment_type
                FROM monthly_rent_payments 
                WHERE booking_id = ?
                ORDER BY month ASC
            ");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $monthlyPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // If still no payments exist, generate them based on the booking period
    if (empty($monthlyPayments)) {
        // Get property details to get monthly rent
        $stmt = $conn->prepare("
            SELECT price FROM houses WHERE id = ?
        ");
        $stmt->bind_param('i', $booking['house_id']);
        $stmt->execute();
        $property = $stmt->get_result()->fetch_assoc();
        
        if ($property) {
            // Generate monthly payment records
            $start = new DateTime($booking['start_date']);
            $end = new DateTime($booking['end_date']);
            $current = clone $start;
            
            while ($current <= $end) {
                $month = $current->format('Y-m-01');
                
                // Check if record already exists
                $checkStmt = $conn->prepare("
                    SELECT id FROM monthly_rent_payments 
                    WHERE booking_id = ? AND month = ?
                ");
                $checkStmt->bind_param('is', $bookingId, $month);
                $checkStmt->execute();
                
                if (!$checkStmt->get_result()->fetch_assoc()) {
                    // Insert new monthly payment record
                    $insertStmt = $conn->prepare("
                        INSERT INTO monthly_rent_payments (booking_id, month, amount, status, payment_type, is_first_payment)
                        VALUES (?, ?, ?, 'unpaid', ?, ?)
                    ");
                    
                    $isFirstPayment = ($current == $start) ? 1 : 0;
                    $paymentType = $isFirstPayment ? 'initial_payment' : 'monthly_rent';
                    
                    // Store values in variables to avoid reference issues
                    $isFirstPaymentValue = $isFirstPayment;
                    
                    $insertStmt->bind_param('isdsi', $bookingId, $month, $property['price'], $paymentType, $isFirstPaymentValue);
                    $insertStmt->execute();
                }
                
                $current->add(new DateInterval('P1M'));
            }
            
            // Get the generated payments
            $stmt = $conn->prepare("
                SELECT 
                    month,
                    amount,
                    status,
                    payment_date,
                    payment_method,
                    mpesa_receipt_number,
                    notes,
                    is_first_payment,
                    payment_type
                FROM monthly_rent_payments 
                WHERE booking_id = ?
                ORDER BY month ASC
            ");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $monthlyPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Format the response
    $formattedPayments = [];
    foreach ($monthlyPayments as $payment) {
        $formattedPayments[] = [
            'month' => $payment['month'],
            'amount' => $payment['amount'],
            'status' => $payment['status'],
            'payment_date' => $payment['payment_date'],
            'payment_method' => $payment['payment_method'],
            'mpesa_receipt_number' => $payment['mpesa_receipt_number'],
            'notes' => $payment['notes'],
            'is_first_payment' => $payment['is_first_payment'] ?? 0,
            'payment_type' => $payment['payment_type'] ?? 'monthly_rent'
        ];
    }
    
    $response = [
        'success' => true,
        'data' => $formattedPayments,
        'booking' => [
            'id' => $booking['id'],
            'start_date' => $booking['start_date'],
            'end_date' => $booking['end_date'],
            'status' => $booking['status']
        ]
    ];
    
    error_log("Monthly payments response for booking $bookingId: " . json_encode($response));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Monthly Payments Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?> 