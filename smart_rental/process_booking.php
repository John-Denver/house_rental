<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once 'controllers/BookingController.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['error'] = 'Please log in to book a property';
    header('Location: login.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

// Log the POST data for debugging
error_log('Booking form submitted: ' . print_r($_POST, true));

// Initialize booking controller
$bookingController = new BookingController($conn);

try {
    // Get property ID from POST data
    $houseId = filter_input(INPUT_POST, 'house_id', FILTER_VALIDATE_INT);
    if (!$houseId) {
        throw new Exception('Invalid property ID');
    }
    
    // Get property details to get price
    $stmt = $conn->prepare("SELECT id, price, landlord_id FROM houses WHERE id = ?");
    $stmt->bind_param('i', $houseId);
    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $stmt->error);
    }
    $house = $stmt->get_result()->fetch_assoc();
    
    if (!$house) {
        throw new Exception('Property not found');
    }
    
    // Get and validate start date
    $startDate = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if (empty($startDate)) {
        throw new Exception('Please select a move-in date');
    }
    
    // Prepare booking data
    $bookingData = [
        'house_id' => $houseId,
        'start_date' => $startDate,
        'special_requests' => filter_input(INPUT_POST, 'special_requests', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null
    ];
    
    error_log('Processed booking data: ' . print_r($bookingData, true));
    
    // Process booking
    error_log('Calling createBooking with data: ' . print_r($bookingData, true));
    $result = $bookingController->createBooking($bookingData, $_FILES);
    error_log('createBooking result: ' . print_r($result, true));
    
    if ($result['success']) {
        try {
            // If booking is successful, create initial rent payment record
            require_once 'controllers/RentCalculationController.php';
            $rentCalculationController = new RentCalculationController($conn);
            
            // Create first month's invoice
            error_log('Generating first month\'s invoice for booking ID: ' . $result['booking_id']);
            $invoiceResult = $rentCalculationController->generateMonthlyInvoice($result['booking_id']);
            error_log('Invoice generation result: ' . ($invoiceResult ? 'Success' : 'Failed'));
            
            if (!$invoiceResult) {
                error_log('Failed to generate invoice for booking ID: ' . $result['booking_id']);
                // Don't fail the booking if invoice generation fails
                // Just log it and continue
            }
            
            // Redirect to booking confirmation page
            header('Location: booking_confirmation.php?id=' . $result['booking_id']);
            exit();
            
        } catch (Exception $e) {
            error_log('Error in post-booking processing: ' . $e->getMessage());
            // Even if invoice generation fails, still show success to user
            // but log the error for admin review
            header('Location: booking_confirmation.php?id=' . $result['booking_id']);
            exit();
        }
    } else {
        throw new Exception($result['message'] ?? 'Failed to create booking');
    }
    
} catch (Exception $e) {
    // Log error
    error_log('Booking error: ' . $e->getMessage());
    
    // Redirect back with error
    $_SESSION['error'] = 'Failed to process booking: ' . $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}
