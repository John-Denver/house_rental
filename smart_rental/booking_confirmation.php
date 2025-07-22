<?php
session_start();
require_once 'config/db.php';
require_once 'controllers/BookingController.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if booking ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$bookingController = new BookingController($conn);

try {
    // Get booking details
    $booking = $bookingController->getBookingDetails($bookingId);
    
    // Verify that the current user is the one who made the booking
    if ($booking['user_id'] != $_SESSION['user_id']) {
        throw new Exception('Unauthorized access to this booking');
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: my_bookings.php');
    exit();
}

// Include header
$pageTitle = 'Booking Confirmation';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <!-- Success Icon -->
                    <div class="text-center mb-4">
                        <div class="bg-success bg-opacity-10 d-inline-flex p-3 rounded-circle mb-3">
                            <i class="fas fa-check-circle fa-4x text-success"></i>
                        </div>
                        <h2 class="fw-bold">Booking Confirmed!</h2>
                        <p class="text-muted">Your booking has been received and is being processed.</p>
                    </div>
                    
                    <!-- Booking Details -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Booking #<?php echo $booking['id']; ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Property Details</h6>
                                    <p class="mb-1">
                                        <i class="fas fa-home me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($booking['property_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($booking['location']); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Booking Dates</h6>
                                    <p class="mb-1">
                                        <i class="far fa-calendar-alt me-2 text-primary"></i>
                                        <?php echo date('M d, Y', strtotime($booking['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($booking['end_date'])); ?>
                                    </p>
                                    <p class="mb-0">
                                        <i class="far fa-clock me-2 text-primary"></i>
                                        <?php echo $booking['rental_period']; ?> months
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Payment Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <td>Monthly Rent (<?php echo $booking['rental_period']; ?> months)</td>
                                            <td class="text-end"><?php echo 'KSh ' . number_format($booking['property_price'] * $booking['rental_period'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Security Deposit (2 months)</td>
                                            <td class="text-end"><?php echo 'KSh ' . number_format($booking['property_price'] * 2, 2); ?></td>
                                        </tr>
                                        <tr class="table-active">
                                            <th>Total Amount Due</th>
                                            <th class="text-end"><?php echo 'KSh ' . number_format(($booking['property_price'] * $booking['rental_period']) + ($booking['property_price'] * 2), 2); ?></th>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Payment Instructions</h6>
                                <p class="mb-1">Please make payment to the following account:</p>
                                <p class="mb-1">Bank: Equity Bank Kenya</p>
                                <p class="mb-1">Account Name: Smart Rental Solutions</p>
                                <p class="mb-1">Account Number: 1234567890</p>
                                <p class="mb-0">Reference: BOOKING-<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></p>
                            </div>
                            
                            <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#uploadPaymentProof">
                                <i class="fas fa-upload me-2"></i>Upload Payment Proof
                            </button>
                        </div>
                    </div>
                    
                    <!-- Next Steps -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">What's Next?</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-1">Complete Payment</h6>
                                    <p class="mb-0 text-muted small">Upload your payment proof to confirm your booking.</p>
                                </div>
                            </div>
                            
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-file-signature"></i>
                                    </div>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-1">Sign Lease Agreement</h6>
                                    <p class="mb-0 text-muted small">Review and sign the digital lease agreement.</p>
                                </div>
                            </div>
                            
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-key"></i>
                                    </div>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-1">Move In</h6>
                                    <p class="mb-0 text-muted small">Schedule your move-in date and get the keys to your new home!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="my_bookings.php" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-list me-1"></i> View All Bookings
                        </a>
                        <a href="property.php?id=<?php echo $booking['house_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-home me-1"></i> View Property
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Payment Proof Modal -->
<div class="modal fade" id="uploadPaymentProof" tabindex="-1" aria-labelledby="uploadPaymentProofLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadPaymentProofLabel">Upload Payment Proof</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="paymentProofForm" action="upload_payment_proof.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="paymentMethod" class="form-label">Payment Method</label>
                        <select class="form-select" id="paymentMethod" name="payment_method" required>
                            <option value="">Select payment method</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash_deposit">Cash Deposit</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transactionId" class="form-label">Transaction/Reference Number</label>
                        <input type="text" class="form-control" id="transactionId" name="transaction_id" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amountPaid" class="form-label">Amount Paid (KES)</label>
                        <input type="number" class="form-control" id="amountPaid" name="amount" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="proofFile" class="form-label">Upload Proof of Payment</label>
                        <input class="form-control" type="file" id="proofFile" name="proof_file" accept="image/*,.pdf" required>
                        <div class="form-text">Upload a clear image or PDF of your payment receipt (max 5MB)</div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="termsCheck" required>
                        <label class="form-check-label" for="termsCheck">
                            I confirm that the information provided is accurate
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i> Upload Proof
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-success text-white">
            <strong class="me-auto">Success</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            <i class="fas fa-check-circle me-2"></i> Your payment proof has been uploaded successfully!
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle form submission
    const paymentProofForm = document.getElementById('paymentProofForm');
    if (paymentProofForm) {
        paymentProofForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Simulate form submission (in a real app, this would be an AJAX call)
            const formData = new FormData(this);
            
            // Here you would typically make an AJAX call to upload the file
            // For example:
            /*
            fetch('upload_payment_proof.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const toast = new bootstrap.Toast(document.getElementById('successToast'));
                    toast.show();
                    
                    // Close modal after delay
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('uploadPaymentProof'));
                        modal.hide();
                    }, 2000);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
            */
            
            // For demo purposes, just show the success toast
            const toast = new bootstrap.Toast(document.getElementById('successToast'));
            toast.show();
            
            // Close modal after delay
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('uploadPaymentProof'));
                modal.hide();
            }, 2000);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
