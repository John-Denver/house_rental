<?php
/**
 * Get Monthly Payments - New API
 * Uses the fresh monthly payment tracking system
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
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
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
    
    // Get monthly payments
    $monthlyPayments = $tracker->getMonthlyPayments($bookingId);
    
    // Get payment summary
    $paymentSummary = $tracker->getPaymentSummary($bookingId);
    
    // Get next payment due
    $nextPaymentDue = $tracker->getNextPaymentDue($bookingId);
    
    // Format the response
    $formattedPayments = [];
    foreach ($monthlyPayments as $payment) {
        $formattedPayments[] = [
            'id' => $payment['id'],
            'month' => $payment['month'],
            'month_display' => date('F Y', strtotime($payment['month'])),
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
    
    echo json_encode([
        'success' => true,
        'data' => [
            'payments' => $formattedPayments,
            'summary' => $paymentSummary,
            'next_payment_due' => $nextPaymentDue ? [
                'month' => $nextPaymentDue['month'],
                'month_display' => date('F Y', strtotime($nextPaymentDue['month'])),
                'amount' => $nextPaymentDue['amount']
            ] : null,
            'booking' => [
                'id' => $booking['id'],
                'start_date' => $booking['start_date'],
                'end_date' => $booking['end_date'],
                'status' => $booking['status'],
                'monthly_rent' => $booking['monthly_rent'],
                'security_deposit' => $booking['security_deposit']
            ]
        ]
    ]);
    
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