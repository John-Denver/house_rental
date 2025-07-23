<?php
session_start();
require_once 'config/db.php';
require_once 'controllers/BookingController.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$bookingController = new BookingController($conn);

// Get user's bookings
$bookings = $bookingController->getUserBookings($_SESSION['user_id']);

// Include header
$pageTitle = 'My Bookings';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Bookings</h1>
        <a href="browse.php" class="btn btn-outline-primary">
            <i class="fas fa-plus me-1"></i> Book Another Property
        </a>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (empty($bookings)): ?>
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-calendar-check fa-4x text-muted opacity-25"></i>
            </div>
            <h4>No Bookings Yet</h4>
            <p class="text-muted">You haven't made any bookings yet. Browse our properties to get started!</p>
            <a href="browse.php" class="btn btn-primary mt-3">
                <i class="fas fa-search me-1"></i> Browse Properties
            </a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filter Bookings</h5>
                        <div class="list-group list-group-flush">
                            <a href="?status=all" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo (!isset($_GET['status']) || $_GET['status'] === 'all') ? 'active' : ''; ?>">
                                All Bookings
                                <span class="badge bg-primary rounded-pill"><?php echo count($bookings); ?></span>
                            </a>
                            <a href="?status=pending" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'active' : ''; ?>">
                                Pending
                                <span class="badge bg-warning rounded-pill"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'pending')); ?></span>
                            </a>
                            <a href="?status=confirmed" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo (isset($_GET['status']) && $_GET['status'] === 'confirmed') ? 'active' : ''; ?>">
                                Confirmed
                                <span class="badge bg-success rounded-pill"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed')); ?></span>
                            </a>
                            <a href="?status=cancelled" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelled') ? 'active' : ''; ?>">
                                Cancelled
                                <span class="badge bg-danger rounded-pill"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled')); ?></span>
                            </a>
                            <a href="?status=completed" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo (isset($_GET['status']) && $_GET['status'] === 'completed') ? 'active' : ''; ?>">
                                Completed
                                <span class="badge bg-secondary rounded-pill"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'completed')); ?></span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Need Help?</h5>
                        <p class="small text-muted">If you have any questions about your bookings, please contact our support team.</p>
                        <a href="contact.php" class="btn btn-outline-primary btn-sm w-100">
                            <i class="fas fa-headset me-1"></i> Contact Support
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8 col-lg-9">
                <?php 
                // Filter bookings if status filter is applied
                $filteredBookings = $bookings;
                if (isset($_GET['status']) && $_GET['status'] !== 'all') {
                    $filteredBookings = array_filter($bookings, fn($b) => $b['status'] === $_GET['status']);
                }
                
                if (empty($filteredBookings)): 
                ?>
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-inbox fa-4x text-muted opacity-25"></i>
                        </div>
                        <h4>No Bookings Found</h4>
                        <p class="text-muted">You don't have any <?php echo htmlspecialchars($_GET['status'] ?? ''); ?> bookings.</p>
                        <a href="browse.php" class="btn btn-primary mt-3">
                            <i class="fas fa-search me-1"></i> Browse Properties
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($filteredBookings as $booking): 
                        $statusClass = [
                            'pending' => 'warning',
                            'confirmed' => 'success',
                            'cancelled' => 'danger',
                            'completed' => 'secondary',
                            'rejected' => 'danger'
                        ][$booking['status']] ?? 'secondary';
                        
                        $paymentStatusClass = [
                            'pending' => 'warning',
                            'partial' => 'info',
                            'paid' => 'success',
                            'failed' => 'danger',
                            'refunded' => 'secondary'
                        ][$booking['payment_status']] ?? 'secondary';
                    ?>
                        <div class="card mb-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-<?php echo $statusClass; ?> me-2">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                    <span class="badge bg-<?php echo $paymentStatusClass; ?>">
                                        <?php echo ucfirst($booking['payment_status'] ?? 'pending'); ?>
                                    </span>
                                </div>
                                <div class="text-muted small">
                                    #<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3 mb-md-0">
                                        <div class="position-relative" style="height: 180px; overflow: hidden; border-radius: 8px;">
                                            <?php if ($booking['main_image']): ?>
                                                <img src="<?php echo htmlspecialchars($booking['main_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($booking['house_no']); ?>" 
                                                     class="w-100 h-100 object-fit-cover">
                                            <?php else: ?>
                                                <div class="w-100 h-100 bg-light d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-home fa-3x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="position-absolute bottom-0 start-0 w-100 p-2" style="background: rgba(0,0,0,0.5);">
                                                <h6 class="text-white mb-0"><?php echo htmlspecialchars($booking['house_no']); ?></h6>
                                                <p class="text-white-50 small mb-0"><?php echo htmlspecialchars($booking['location']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <h5 class="card-title"><?php echo htmlspecialchars($booking['house_no']); ?></h5>
                                        <p class="card-text text-muted mb-2">
                                            <i class="far fa-calendar-alt me-2"></i>
                                            <?php echo date('M d, Y', strtotime($booking['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($booking['end_date'])); ?>
                                        </p>
                                        <p class="card-text text-muted mb-2">
                                            <i class="fas fa-clock me-2"></i>
                                            <?php echo $booking['rental_period']; ?> months
                                        </p>
                                        <p class="card-text text-muted mb-2">
                                            <i class="fas fa-money-bill-wave me-2"></i>
                                            KSh <?php echo number_format($booking['total_amount'], 2); ?> total
                                        </p>
                                        <?php if ($booking['status'] === 'cancelled' && $booking['cancellation_reason']): ?>
                                            <div class="alert alert-danger p-2 small mb-0 mt-2">
                                                <strong>Cancellation Reason:</strong> 
                                                <?php echo htmlspecialchars($booking['cancellation_reason']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 d-flex flex-column">
                                        <div class="mt-auto">
                                            <a href="booking_details.php?id=<?php echo $booking['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary w-100 mb-2">
                                                <i class="far fa-eye me-1"></i> View Details
                                            </a>
                                            
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-outline-danger w-100 mb-2" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#cancelBookingModal"
                                                        data-booking-id="<?php echo $booking['id']; ?>">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['payment_status'] !== 'paid' && $booking['status'] !== 'cancelled'): ?>
                                                <a href="booking_payment.php?id=<?php echo $booking['id']; ?>" 
                                                   class="btn btn-sm btn-success w-100">
                                                    <i class="fas fa-credit-card me-1"></i> Pay Now
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['status'] === 'completed' && $booking['can_review'] ?? false): ?>
                                                <button class="btn btn-sm btn-outline-info w-100 mt-2" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#leaveReviewModal"
                                                        data-booking-id="<?php echo $booking['id']; ?>">
                                                    <i class="far fa-star me-1"></i> Leave a Review
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Cancel Booking Modal -->
<div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-labelledby="cancelBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="cancelBookingForm" action="cancel_booking.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelBookingModalLabel">Cancel Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="booking_id" id="cancelBookingId">
                    <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
                    <div class="mb-3">
                        <label for="cancellationReason" class="form-label">Reason for cancellation</label>
                        <textarea class="form-control" id="cancellationReason" name="reason" rows="3" required></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cancellation may be subject to fees as per our cancellation policy.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-1"></i> Cancel Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Leave Review Modal -->
<div class="modal fade" id="leaveReviewModal" tabindex="-1" aria-labelledby="leaveReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="reviewForm" action="submit_review.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveReviewModalLabel">Leave a Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="booking_id" id="reviewBookingId">
                    
                    <div class="text-center mb-4">
                        <div class="rating-stars mb-2">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" 
                                       <?php echo $i === 5 ? 'checked' : ''; ?>>
                                <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted small">How would you rate your experience?</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reviewTitle" class="form-label">Review Title</label>
                        <input type="text" class="form-control" id="reviewTitle" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reviewText" class="form-label">Your Review</label>
                        <textarea class="form-control" id="reviewText" name="review" rows="4" required></textarea>
                        <div class="form-text">Share details about your experience with this property.</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="anonymousReview" name="is_anonymous">
                        <label class="form-check-label" for="anonymousReview">
                            Post anonymously
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="far fa-paper-plane me-1"></i> Submit Review
                    </button>
                </div>
            </form>
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
    
    // Handle cancel booking modal
    var cancelBookingModal = document.getElementById('cancelBookingModal');
    if (cancelBookingModal) {
        cancelBookingModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var bookingId = button.getAttribute('data-booking-id');
            var modalInput = cancelBookingModal.querySelector('#cancelBookingId');
            modalInput.value = bookingId;
        });
    }
    
    // Handle leave review modal
    var leaveReviewModal = document.getElementById('leaveReviewModal');
    if (leaveReviewModal) {
        leaveReviewModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var bookingId = button.getAttribute('data-booking-id');
            var modalInput = leaveReviewModal.querySelector('#reviewBookingId');
            modalInput.value = bookingId;
        });
    }
    
    // Handle star rating
    const stars = document.querySelectorAll('.rating-stars input');
    stars.forEach(star => {
        star.addEventListener('change', function() {
            const rating = this.value;
            // You can add any additional logic here when a star is selected
            console.log('Selected rating:', rating);
        });
    });
    
    // Handle form submissions with AJAX for better UX
    const forms = document.querySelectorAll('form.ajax-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
            
            // Simulate form submission (replace with actual fetch/AJAX call)
            setTimeout(() => {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                // Show success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success alert-dismissible fade show mt-3';
                alert.role = 'alert';
                alert.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>
                    Your request has been processed successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                // Insert alert before the form
                this.parentNode.insertBefore(alert, this);
                
                // Close modal if this is a modal form
                const modal = bootstrap.Modal.getInstance(this.closest('.modal'));
                if (modal) {
                    setTimeout(() => {
                        modal.hide();
                        // Refresh the page after a short delay
                        setTimeout(() => window.location.reload(), 500);
                    }, 1500);
                } else {
                    // If not in a modal, reset the form
                    this.reset();
                }
            }, 1500);
        });
    });
});
</script>

<style>
.rating-stars {
    direction: rtl;
    unicode-bidi: bidi-override;
    text-align: center;
}
.rating-stars input {
    display: none;
}
.rating-stars label {
    color: #ddd;
    font-size: 1.5rem;
    padding: 0 5px;
    cursor: pointer;
}
.rating-stars label:hover,
.rating-stars label:hover ~ label,
.rating-stars input:checked ~ label {
    color: #ffc107;
}
.rating-stars input:checked ~ label {
    color: #ffc107;
}
</style>

<?php include 'includes/footer.php'; ?>
