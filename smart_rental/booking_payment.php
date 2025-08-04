<?php
session_start();
require_once '../config/db.php';
require_once 'controllers/BookingController.php';
require_once 'mpesa_config.php';

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
                        
                        <!-- Payment Methods -->
                        <div class="col-md-7">
                            <div class="payment-methods">
                                <h5 class="mb-4 text-primary">
                                    <i class="fas fa-mobile-alt me-2"></i>Select Payment Method
                                </h5>
                                
                                <!-- M-Pesa Payment -->
                                <div class="card mb-4 border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-mobile-alt me-2"></i>M-Pesa Mobile Money
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">
                                            Pay securely using M-Pesa. You'll receive an STK Push notification on your phone.
                                        </p>
                                        
                                        <form id="mpesaPaymentForm">
                                            <div class="mb-3">
                                                <label for="phoneNumber" class="form-label">M-Pesa Phone Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">+254</span>
                                                    <input type="tel" class="form-control" id="phoneNumber" 
                                                           placeholder="700000000" required 
                                                           pattern="[0-9]{9}" maxlength="9">
                                                </div>
                                                <div class="form-text">Enter your M-Pesa registered phone number</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Amount to Pay</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">KSh</span>
                                                    <input type="text" class="form-control" id="paymentAmount" 
                                                           value="<?php echo number_format($totalAmount, 2); ?>" 
                                                           readonly>
                                                </div>
                                                <div class="form-text">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <?php echo $paymentDescription; ?>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary btn-lg w-100" id="payButton">
                                                <i class="fas fa-mobile-alt me-2"></i>Pay with M-Pesa
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Manual Payment Method -->
                                <div class="card border-secondary">
                                    <div class="card-header bg-secondary text-white">
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
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="payment_notes" class="form-label">Payment Notes</label>
                                                <textarea class="form-control" id="payment_notes" name="notes" rows="3" 
                                                          placeholder="Describe your payment method (e.g., Bank transfer, Cash, etc.)"></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-secondary btn-lg w-100">
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

<!-- Payment Status Modal -->
<div class="modal fade" id="paymentStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Processing Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="processingStatus">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h6>Initiating M-Pesa Payment</h6>
                    <p class="text-muted">Please wait while we process your payment request...</p>
                </div>
                
                <div id="successStatus" style="display: none;">
                    <i class="fas fa-mobile-alt fa-3x text-primary mb-3"></i>
                    <h6 class="text-primary">Payment Request Sent!</h6>
                    <p class="text-muted">Check your phone for the M-Pesa STK Push notification.</p>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Important:</strong> Please complete the payment on your phone. The system will automatically detect when your payment is successful.
                    </div>
                </div>
                
                <div id="errorStatus" style="display: none;">
                    <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                    <h6 class="text-danger">Payment Failed</h6>
                    <p class="text-muted" id="errorMessage">An error occurred while processing your payment.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="closeModal">Close</button>
                <button type="button" class="btn btn-primary" id="checkStatusBtn" style="display: none;">
                    Check Payment Status
                </button>
                <button type="button" class="btn btn-warning" id="tryAgainBtn" style="display: none;">
                    <i class="fas fa-redo me-2"></i>Try Again
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mpesaForm = document.getElementById('mpesaPaymentForm');
    const payButton = document.getElementById('payButton');
    const paymentStatusModal = new bootstrap.Modal(document.getElementById('paymentStatusModal'));
    const processingStatus = document.getElementById('processingStatus');
    const successStatus = document.getElementById('successStatus');
    const errorStatus = document.getElementById('errorStatus');
    const errorMessage = document.getElementById('errorMessage');
    const closeModal = document.getElementById('closeModal');
    const checkStatusBtn = document.getElementById('checkStatusBtn');
    const tryAgainBtn = document.getElementById('tryAgainBtn');
    
    let checkoutRequestId = null;
    
    // Format phone number input
    const phoneInput = document.getElementById('phoneNumber');
    phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 9) {
            value = value.substring(0, 9);
        }
        e.target.value = value;
    });
    
    // Handle M-Pesa payment form submission
    mpesaForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const phoneNumber = phoneInput.value;
        const amount = <?php echo $totalAmount; ?>;
        
        if (!phoneNumber || phoneNumber.length !== 9) {
            alert('Please enter a valid 9-digit phone number');
            return;
        }
        
        // Show processing modal
        processingStatus.style.display = 'block';
        successStatus.style.display = 'none';
        errorStatus.style.display = 'none';
        checkStatusBtn.style.display = 'none';
        paymentStatusModal.show();
        
        // Disable pay button
        payButton.disabled = true;
        payButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        
        try {
            const response = await fetch('mpesa_stk_push.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    booking_id: <?php echo $bookingId; ?>,
                    phone_number: '254' + phoneNumber,
                    amount: amount
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                checkoutRequestId = result.data.checkout_request_id;
                
                // Show success status
                processingStatus.style.display = 'none';
                successStatus.style.display = 'block';
                checkStatusBtn.style.display = 'none'; // Hide initially
                
                // Update modal title
                document.getElementById('modalTitle').textContent = 'Payment Request Sent';
                
                // Start polling for payment status
                pollPaymentStatus();
                
                // Show check status button after 30 seconds if still pending
                setTimeout(() => {
                    if (successStatus.style.display !== 'none') {
                        checkStatusBtn.style.display = 'inline-block';
                        checkStatusBtn.innerHTML = '<i class="fas fa-sync-alt me-2"></i>Check Payment Status';
                    }
                }, 30000);
                
                // Show force check button after 2 minutes if still pending
                setTimeout(() => {
                    if (successStatus.style.display !== 'none') {
                        // Add a more prominent message
                        const infoAlert = document.createElement('div');
                        infoAlert.className = 'alert alert-warning mt-3';
                        infoAlert.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Payment Status Check</strong><br>
                            <small>If you've completed the payment on your phone but don't see confirmation here, click "Check Payment Status" above.</small>
                        `;
                        successStatus.appendChild(infoAlert);
                    }
                }, 120000);
                
            } else {
                throw new Error(result.message || 'Payment initiation failed');
            }
            
        } catch (error) {
            console.error('Payment error:', error);
            
            // Show error status
            processingStatus.style.display = 'none';
            errorStatus.style.display = 'block';
            errorMessage.textContent = error.message || 'An error occurred while processing your payment.';
            
            // Update modal title
            document.getElementById('modalTitle').textContent = 'Payment Failed';
        }
        
        // Re-enable pay button
        payButton.disabled = false;
        payButton.innerHTML = '<i class="fas fa-mobile-alt me-2"></i>Pay with M-Pesa';
    });
    
    // Poll payment status
    function pollPaymentStatus() {
        if (!checkoutRequestId) return;
        
        const pollInterval = setInterval(async function() {
            try {
                const response = await fetch('mpesa_payment_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        checkout_request_id: checkoutRequestId
                    })
                });
                
                let result;
                try {
                    const responseText = await response.text();
                    console.log('Raw response:', responseText);
                    
                    result = JSON.parse(responseText);
                    console.log('Payment status check result:', result);
                } catch (parseError) {
                    console.error('Failed to parse JSON response:', parseError);
                    console.error('Response text:', responseText);
                    throw new Error('Invalid response from server');
                }
                
                if (result.success && result.data.status === 'completed') {
                    clearInterval(pollInterval);
                    
                    // Show success and redirect
                    successStatus.innerHTML = `
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h6 class="text-success">Payment Successful!</h6>
                        <p class="text-muted">Your payment has been processed successfully.</p>
                        <p class="text-muted small">Receipt: ${result.data.receipt_number}</p>
                    `;
                    
                    checkStatusBtn.style.display = 'none';
                    closeModal.textContent = 'Continue';
                    
                    // Redirect after 3 seconds
                    setTimeout(() => {
                        window.location.href = 'booking_confirmation.php?id=<?php echo $bookingId; ?>';
                    }, 3000);
                    
                } else if (result.success && (result.data.status === 'failed' || result.data.status === 'cancelled')) {
                    clearInterval(pollInterval);
                    
                    // Show failure or cancellation
                    errorStatus.style.display = 'block';
                    successStatus.style.display = 'none';
                    
                    if (result.data.status === 'cancelled') {
                        errorMessage.innerHTML = `
                            <i class="fas fa-times-circle text-warning me-2"></i>
                            <strong>Payment Cancelled</strong><br>
                            <small class="text-muted">You cancelled the M-Pesa payment prompt. You can try again anytime.</small>
                        `;
                        document.getElementById('modalTitle').textContent = 'Payment Cancelled';
                        tryAgainBtn.style.display = 'inline-block';
                        checkStatusBtn.style.display = 'none';
                    } else {
                        if (result.data.is_expired) {
                            errorMessage.innerHTML = `
                                <i class="fas fa-clock text-warning me-2"></i>
                                <strong>Payment Request Expired</strong><br>
                                <small class="text-muted">The M-Pesa prompt expired. Please try again.</small>
                            `;
                            document.getElementById('modalTitle').textContent = 'Payment Expired';
                            tryAgainBtn.style.display = 'inline-block';
                        } else {
                            errorMessage.textContent = result.data.message || 'Payment was not completed.';
                            document.getElementById('modalTitle').textContent = 'Payment Failed';
                            tryAgainBtn.style.display = 'none';
                        }
                        checkStatusBtn.style.display = 'none';
                    }
                }
                
            } catch (error) {
                console.error('Status check error:', error);
            }
        }, 3000); // Check every 3 seconds for faster response
        
        // Stop polling after 3 minutes and 30 seconds (give extra time)
        setTimeout(() => {
            clearInterval(pollInterval);
            // If still pending after timeout, show manual check option
            if (successStatus.style.display !== 'none') {
                errorStatus.style.display = 'block';
                successStatus.style.display = 'none';
                errorMessage.innerHTML = `
                    <i class="fas fa-clock text-warning me-2"></i>
                    <strong>Payment Timeout</strong><br>
                    <small class="text-muted">The payment request has timed out. Please check your payment status or try again.</small>
                `;
                document.getElementById('modalTitle').textContent = 'Payment Timeout';
                checkStatusBtn.style.display = 'inline-block';
            }
        }, 210000); // 3.5 minutes
    }
    
    // Handle modal close
    closeModal.addEventListener('click', function() {
        if (checkoutRequestId) {
            window.location.href = 'booking_details.php?id=<?php echo $bookingId; ?>';
        }
    });
    
    // Handle try again button for cancelled payments
    tryAgainBtn.addEventListener('click', function() {
        // Hide the modal and trigger the payment form again
        paymentStatusModal.hide();
        
        // Reset form and trigger payment
        setTimeout(() => {
            mpesaForm.dispatchEvent(new Event('submit'));
        }, 500);
    });
    
    // Handle check status button
    checkStatusBtn.addEventListener('click', async function() {
        if (!checkoutRequestId) return;
        
        // Show loading state
        checkStatusBtn.disabled = true;
        checkStatusBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking...';
        
        try {
            const response = await fetch('mpesa_payment_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    checkout_request_id: checkoutRequestId
                })
            });
            
            let result;
            try {
                const responseText = await response.text();
                console.log('Manual check - Raw response:', responseText);
                
                result = JSON.parse(responseText);
                console.log('Manual check - Parsed result:', result);
            } catch (parseError) {
                console.error('Manual check - Failed to parse JSON:', parseError);
                console.error('Manual check - Response text:', responseText);
                alert('Error: Invalid response from server. Please try again.');
                return;
            }
            
            if (result.success && result.data.status === 'completed') {
                // Payment was successful
                successStatus.innerHTML = `
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h6 class="text-success">Payment Successful!</h6>
                    <p class="text-muted">Your payment has been processed successfully.</p>
                    <p class="text-muted small">Receipt: ${result.data.receipt_number}</p>
                `;
                
                checkStatusBtn.style.display = 'none';
                closeModal.textContent = 'Continue';
                
                // Redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = 'booking_confirmation.php?id=<?php echo $bookingId; ?>';
                }, 3000);
                
            } else if (result.success && result.data.status === 'pending') {
                // Still pending
                alert('Payment is still being processed. Please complete the payment on your phone and try checking again.');
            } else if (result.success && result.data.status === 'failed') {
                // Failed
                alert('Payment was not completed: ' + (result.data.message || 'Unknown error') + '. Please try again.');
            } else if (result.success && result.data.status === 'cancelled') {
                // Cancelled
                alert('Payment was cancelled. You can try again anytime.');
            } else {
                // Unknown status
                alert('Unable to determine payment status. Please try again or contact support.');
            }
            
        } catch (error) {
            console.error('Manual status check error:', error);
            alert('Error checking payment status. Please try again.');
        }
        
        // Reset button
        checkStatusBtn.disabled = false;
        checkStatusBtn.innerHTML = '<i class="fas fa-sync-alt me-2"></i>Check Payment Status';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
