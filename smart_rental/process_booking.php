<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once 'controllers/BookingController.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

// Initialize booking controller
$bookingController = new BookingController($conn);

try {
    // Get property details to get monthly rent
    $houseId = filter_input(INPUT_POST, 'house_id', FILTER_VALIDATE_INT);
    $stmt = $conn->prepare("SELECT monthly_rent FROM houses WHERE id = ?");
    $stmt->bind_param('i', $houseId);
    $stmt->execute();
    $house = $stmt->get_result()->fetch_assoc();
    
    if (!$house) {
        throw new Exception('Property not found');
    }
    
    // Prepare booking data
    $bookingData = [
        'house_id' => $houseId,
        'start_date' => filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING),
        'rental_period' => filter_input(INPUT_POST, 'rental_period', FILTER_VALIDATE_INT),
        'special_requests' => filter_input(INPUT_POST, 'special_requests', FILTER_SANITIZE_STRING),
        'monthly_rent' => $house['monthly_rent']
    ];
    
    // Calculate end date (fixed 12 months)
    $startDate = new DateTime($bookingData['start_date']);
    $endDate = clone $startDate;
    $endDate->add(new DateInterval('P12M'));
    $bookingData['end_date'] = $endDate->format('Y-m-d');
    
    // Calculate total rent for the entire period
    $bookingData['total_rent'] = $bookingData['monthly_rent'] * 12;
    
    // Process booking
    $result = $bookingController->createBooking($bookingData, $_FILES);
    
    // If booking is successful, create initial rent payment record
    if ($result['success']) {
        require_once 'controllers/RentPaymentController.php';
        $rentPaymentController = new RentPaymentController($conn);
        
        // Create first month's invoice
        $rentPaymentController->generateMonthlyInvoice($result['booking_id']);
    }
    
    if ($result['success']) {
        // Redirect to booking confirmation page
        header('Location: booking_confirmation.php?id=' . $result['booking_id']);
        exit();
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    // Log error
    error_log('Booking error: ' . $e->getMessage());
    
    // Redirect back with error
    $_SESSION['error'] = 'Failed to process booking: ' . $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}
