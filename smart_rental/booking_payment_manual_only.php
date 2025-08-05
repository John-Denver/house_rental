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

// Include payment tracking helper
require_once 'includes/payment_tracking_helper.php';

// Check if first payment has been made
$hasFirstPayment = hasFirstPaymentBeenMade($conn, $bookingId);

// Calculate payment amount based on payment history
$additionalFees = $booking['additional_fees'] ?? 0;
$monthlyRent = floatval($booking['property_price']);
$securityDeposit = floatval($booking['security_deposit'] ?? 0);

if (!$hasFirstPayment) {
    // First payment: Security deposit + first month rent + additional fees
    $totalAmount = $monthlyRent + $securityDeposit + $additionalFees;
    $paymentType = 'initial_payment';
    $paymentDescription = 'Initial Payment (Security Deposit + First Month Rent)';
} else {
    // Subsequent payments: Monthly rent only
    $totalAmount = $monthlyRent + $additionalFees;
    $paymentType = 'monthly_rent';
    $paymentDescription = 'Monthly Rent Payment';
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
        <div class="col-lg-10">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="my_bookings.php">My Bookings</a></li>
                    <li class="breadcrumb-item"><a href="booking_details.php?id=<?php echo $bookingId; ?>">Booking #<?php echo str_pad($bookingId, 6, '0', STR_PAD_LEFT); ?></a></li>
                    <li class="breadcrumb-item active">Payment</li>
                </ol>
            </nav>
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Complete Payment</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <!-- Booking Summary -->
                        <div class="col-md-5">
                            <div class="booking-summary p-4 border rounded bg-light">
                                <h5 class="mb-4 text-primary">
                                    <i class="fas fa-file-invoice me-2"></i>Booking Summary
                                </h5>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Booking ID:</span>
                                    <span class="fw-bold">#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Property:</span>
                                    <span class="fw-bold"><?php echo htmlspecialchars($booking['house_no']); ?></span>
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
                                    <span><?php echo $booking['rental_period'] ?? 12; ?> months</span>
                                </div>
                                
                                <hr class="my-3">
                                
                                <h6 class="text-primary mb-3">Payment Breakdown</h6>
                                
                                <!-- Payment Type Badge -->
                                <div class="mb-3">
                                    <span class="badge bg-<?php echo $paymentType === 'initial_payment' ? 'warning' : 'success'; ?> fs-6">
                                        <?php echo $paymentDescription; ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Monthly Rent:</span>
                                    <span>KSh <?php echo number_format($monthlyRent, 2); ?></span>
                                </div>
                                
                                <?php if (!$hasFirstPayment): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Security Deposit:</span>
                                        <span>KSh <?php echo number_format($securityDeposit, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($additionalFees > 0): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Additional Fees:</span>
                                        <span>KSh <?php echo number_format($additionalFees, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between fw-bold mt-3 pt-2 border-top border-primary">
                                    <span class="text-primary">Total Amount:</span>
                                    <span class="text-primary fs-5">KSh <?php echo number_format($totalAmount, 2); ?></span>
                                </div>
                                
                                <?php if ($hasFirstPayment): ?>
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Note:</strong> Security deposit was paid with your initial payment. This is a monthly rent payment only.
                                    </div>
                                <?php endif; ?>
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
                        
                        <!-- Manual Payment Method -->
                        <div class="col-md-7">
                            <div class="payment-methods">
                                <h5 class="mb-4 text-primary">
                                    <i class="fas fa-credit-card me-2"></i>Manual Payment
                                </h5>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Note:</strong> M-Pesa integration is currently disabled. Please use manual payment method.
                                </div>
                                
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-credit-card me-2"></i>Manual Payment
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">
                                            Complete payment manually and provide proof of payment.
                                        </p>
                                        
                                        <form method="POST" action="">
                                            <input type="hidden" name="amount" value="<?php echo $totalAmount; ?>">
                                            <input type="hidden" name="payment_method" value="manual">
                                            <input type="hidden" name="payment_type" value="<?php echo $paymentType; ?>">
                                            
                                            <div class="mb-3">
                                                <label for="transaction_id" class="form-label">Transaction ID/Reference</label>
                                                <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                                       placeholder="Enter transaction ID or reference number" required>
                                                <div class="form-text">Enter the transaction ID from your bank transfer, M-Pesa, or other payment method</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="payment_notes" class="form-label">Payment Notes</label>
                                                <textarea class="form-control" id="payment_notes" name="notes" rows="3" 
                                                          placeholder="Describe your payment method (e.g., Bank transfer, Cash, M-Pesa, etc.) and any additional details"></textarea>
                                                <div class="form-text">Please provide details about how you made the payment</div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                                <i class="fas fa-upload me-2"></i>Submit Payment Proof
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 