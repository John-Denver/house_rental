<?php
session_start();
require_once '../config/db.php';
require_once 'controllers/BookingController.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if booking ID is provided
if (!isset($_GET['id'])) {
    header('Location: my_bookings.php');
    exit();
}

$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$bookingController = new BookingController($conn);

try {
    // Get booking details
    $booking = $bookingController->getBookingDetails($bookingId);
    
    // Verify that the current user is the one who made the booking
    if ($booking['user_id'] != $_SESSION['user_id'] && !isset($_SESSION['is_admin'])) {
        throw new Exception('Unauthorized access to this booking');
    }
    
    // Check if booking is already paid
    if ($booking['payment_status'] === 'paid') {
        $_SESSION['success'] = 'This booking has already been paid.';
        header('Location: booking_details.php?id=' . $bookingId);
        exit();
    }
    
    // Check if booking is confirmed
    if ($booking['status'] !== 'confirmed') {
        throw new Exception('This booking is not confirmed for payment.');
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: my_bookings.php');
    exit();
}

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $paymentData = [
            'booking_id' => $bookingId,
            'amount' => filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT),
            'payment_method' => filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'transaction_id' => filter_input(INPUT_POST, 'transaction_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'notes' => filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
        ];
        
        // Process the payment
        $result = $bookingController->processPayment($paymentData);
        
        if ($result['success']) {
            $_SESSION['success'] = 'Payment processed successfully!';
            header('Location: booking_confirmation.php?id=' . $bookingId);
            exit();
        } else {
            throw new Exception($result['message']);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Include header
$pageTitle = 'Complete Payment';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="my_bookings.php">My Bookings</a></li>
                    <li class="breadcrumb-item"><a href="booking_details.php?id=<?php echo $bookingId; ?>">Booking #<?php echo str_pad($bookingId, 6, '0', STR_PAD_LEFT); ?></a></li>
                    <li class="breadcrumb-item active">Payment</li>
                </ol>
            </nav>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Complete Payment</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="booking-summary p-4 border rounded">
                                <h5 class="mb-4">Booking Summary</h5>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Booking ID:</span>
                                    <span>#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Property:</span>
                                    <span><?php echo htmlspecialchars($booking['house_no']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Check-in:</span>
                                    <span><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Check-out:</span>
                                    <span><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Duration:</span>
                                    <span><?php echo $booking['rental_period']; ?> months</span>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Monthly Rent:</span>
                                    <span>KSh <?php echo number_format($booking['property_price'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal (<?php echo $booking['rental_period']; ?> months):</span>
                                    <span>KSh <?php echo number_format($booking['property_price'] * $booking['rental_period'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Security Deposit:</span>
                                    <span>KSh <?php echo number_format($booking['security_deposit'], 2); ?></span>
                                </div>
                                
                                <?php if ($booking['additional_fees'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Additional Fees:</span>
                                        <span>KSh <?php echo number_format($booking['additional_fees'], 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between fw-bold mt-3 pt-2 border-top">
                                    <span>Total Amount Due:</span>
                                    <span>KSh <?php echo number_format($booking['total_amount'], 2); ?></span>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Need Help?</h6>
                                    <p class="mb-0 small">If you encounter any issues with your payment, please contact our support team.</p>
                                    <a href="contact.php" class="btn btn-sm btn-outline-info mt-2">
                                        <i class="fas fa-headset me-1"></i> Contact Support
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="payment-methods">
                                <h5 class="mb-4">Select Payment Method</h5>
                                
                                <ul class="nav nav-pills mb-4" id="paymentTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id=
