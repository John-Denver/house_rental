<?php
/**
 * Allocate Payment API
 * Allocates a payment to the next unpaid month for a booking
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

// Get required parameters
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$paymentAmount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
$transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_SANITIZE_STRING);
$mpesaReceipt = filter_input(INPUT_POST, 'mpesa_receipt', FILTER_SANITIZE_STRING);

if (!$bookingId || !$paymentAmount || !$paymentMethod) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Include the monthly payment tracker
    require_once __DIR__ . '/monthly_payment_tracker.php';
    
    // Verify that the current user owns this booking
    $stmt = $conn->prepare("
        SELECT id, user_id, house_id, start_date, end_date, status, monthly_rent, security_deposit
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
    
    // Create tracker instance
    $tracker = new MonthlyPaymentTracker($conn);
    
    // Get next payment due to validate amount
    $nextPaymentDue = $tracker->getNextPaymentDue($bookingId);
    
    if (!$nextPaymentDue) {
        echo json_encode(['success' => false, 'message' => 'No unpaid months found for this booking']);
        exit;
    }
    
    // Validate payment amount (should match the next payment due)
    if (abs($paymentAmount - $nextPaymentDue['amount']) > 0.01) {
        echo json_encode([
            'success' => false, 
            'message' => 'Payment amount does not match the next payment due. Expected: KSh ' . number_format($nextPaymentDue['amount'], 2) . ', Received: KSh ' . number_format($paymentAmount, 2)
        ]);
        exit;
    }
    
    // Allocate the payment
    $paymentDate = date('Y-m-d H:i:s');
    $result = $tracker->allocatePayment($bookingId, $paymentAmount, $paymentDate, $paymentMethod, $transactionId, $mpesaReceipt);
    
    // Log the payment
    error_log("Payment allocated for booking $bookingId: " . json_encode($result));
    
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data' => [
            'allocated_month' => $result['allocated_month'],
            'allocated_month_display' => date('F Y', strtotime($result['allocated_month'])),
            'amount' => $result['amount'],
            'payment_date' => $paymentDate,
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
            'mpesa_receipt' => $mpesaReceipt
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Payment Allocation Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?> 