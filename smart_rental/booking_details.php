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
    
    // Get related documents and payments
    $documents = $bookingController->getBookingDocuments($bookingId);
    $payments = $bookingController->getBookingPayments($bookingId);
    $hasReview = $bookingController->hasBookingReview($bookingId);
    $canReview = $booking['status'] === 'completed' && !$hasReview;
    
    // Get payment information
    $paymentStatus = 'pending';
    $paymentDate = null;
    if (!empty($payments)) {
        $latestPayment = $payments[0]; // First payment is the latest due to DESC ordering
        $paymentStatus = $latestPayment['status'] ?? 'pending';
        $paymentDate = $latestPayment['payment_date'] ?? null;
    }
    
    // Add payment information to booking array with fallbacks
    $booking['payment_status'] = $paymentStatus;
    $booking['payment_date'] = $paymentDate;
    
    // Ensure all required fields have fallback values
    $booking['property_price'] = $booking['property_price'] ?? 0;
    $booking['security_deposit'] = $booking['security_deposit'] ?? 0;
    $booking['start_date'] = $booking['start_date'] ?? date('Y-m-d');
    $booking['end_date'] = $booking['end_date'] ?? date('Y-m-d', strtotime('+1 year'));
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: my_bookings.php');
    exit();
}

    // Include header
    $pageTitle = 'Booking #' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT);
    include 'includes/header.php';
    
    // Debug information (remove in production)
    if (isset($_GET['debug']) && $_SESSION['is_admin']) {
        echo '<div class="alert alert-info">';
        echo '<strong>Debug Info:</strong><br>';
        echo 'Payment Status: ' . ($booking['payment_status'] ?? 'undefined') . '<br>';
        echo 'Payment Date: ' . ($booking['payment_date'] ?? 'null') . '<br>';
        echo 'Number of Payments: ' . count($payments) . '<br>';
        if (!empty($payments)) {
            echo 'Latest Payment: ' . json_encode($payments[0]) . '<br>';
        }
        echo '</div>';
    }
?>

<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="my_bookings.php">My Bookings</a></li>
            <li class="breadcrumb-item active">Booking #<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></li>
        </ol>
    </nav>
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Booking Details</h1>
        <a href="my_bookings.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Bookings
        </a>
    </div>
    
    <!-- Status Alert -->
    <?php if ($booking['status'] === 'pending'): ?>
        <div class="alert alert-warning">
            <i class="fas fa-info-circle me-2"></i>
            Your booking is pending approval from the property owner.
        </div>
    <?php elseif ($booking['status'] === 'confirmed' && ($booking['payment_status'] !== 'completed' && $booking['payment_status'] !== 'paid')): ?>
        <div class="alert alert-info">
            <i class="fas fa-credit-card me-2"></i>
            Your booking is confirmed! Please complete your payment.
            <a href="booking_payment.php?id=<?php echo $booking['id']; ?>" class="alert-link ms-2">
                Pay Now <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    <?php elseif ($booking['status'] === 'pending' && ($booking['payment_status'] === 'pending' || $booking['payment_status'] === 'unpaid')): ?>
        <div class="alert alert-warning">
            <i class="fas fa-clock me-2"></i>
            Your booking is pending. Please complete your payment to secure your booking.
            <a href="booking_payment.php?id=<?php echo $booking['id']; ?>" class="alert-link ms-2">
                Pay Now <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Property Card -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Property Information</h5>
                    <span class="badge bg-<?php 
                        echo $booking['status'] === 'confirmed' ? 'success' : 
                             ($booking['status'] === 'pending' ? 'warning' : 'danger'); 
                    ?>">
                        <?php echo ucfirst($booking['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="position-relative" style="height: 200px; overflow: hidden; border-radius: 8px;">
                                <?php if (!empty($booking['main_image'])): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($booking['main_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($booking['house_no'] ?? $booking['property_name']); ?>" 
                                         class="w-100 h-100 object-fit-cover">
                                <?php else: ?>
                                    <div class="w-100 h-100 bg-light d-flex align-items-center justify-content-center">
                                        <i class="fas fa-home fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <h4 class="card-title"><?php echo htmlspecialchars($booking['house_no'] ?? $booking['property_name']); ?></h4>
                            <p class="text-muted mb-3">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <?php echo htmlspecialchars($booking['location'] ?? $booking['property_location']); ?>
                            </p>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light p-2 rounded me-3">
                                            <i class="fas fa-bed text-primary"></i>
                                        </div>
                                        <div>
                                            <p class="mb-0 small text-muted">Bedrooms</p>
                                            <p class="mb-0 fw-bold"><?php echo $booking['bedrooms'] ?? 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light p-2 rounded me-3">
                                            <i class="fas fa-bath text-primary"></i>
                                        </div>
                                        <div>
                                            <p class="mb-0 small text-muted">Bathrooms</p>
                                            <p class="mb-0 fw-bold"><?php echo $booking['bathrooms'] ?? 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <a href="property.php?id=<?php echo $booking['house_id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i> View Property
                                </a>
                                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Booking Timeline -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Booking Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php
                        $timeline = [
                            [
                                'icon' => 'calendar-check',
                                'date' => $booking['created_at'],
                                'title' => 'Booking Requested',
                                'description' => 'Your booking request was submitted.'
                            ],
                            [
                                'icon' => 'user-check',
                                'date' => $booking['status'] !== 'pending' ? $booking['updated_at'] : null,
                                'title' => 'Booking ' . ucfirst($booking['status']),
                                'description' => 'Your booking has been ' . $booking['status'] . '.'
                            ],
                            [
                                'icon' => 'money-bill-wave',
                                'date' => ($booking['payment_status'] === 'completed' || $booking['payment_status'] === 'paid') && !empty($booking['payment_date']) ? $booking['payment_date'] : null,
                                'title' => ($booking['payment_status'] === 'completed' || $booking['payment_status'] === 'paid') ? 'Payment Completed' : 'Payment Pending',
                                'description' => ($booking['payment_status'] === 'completed' || $booking['payment_status'] === 'paid') 
                                    ? 'Payment of KSh ' . number_format(floatval($booking['property_price']) + floatval($booking['security_deposit'] ?? 0), 2) . ' received.'
                                    : 'Complete your payment to secure your booking.'
                            ]
                        ];
                        
                        foreach ($timeline as $event): 
                            if (!$event['date'] && $event['title'] !== 'Booking Requested') continue;
                            // Skip payment event if no payment date and status is not completed/paid
                            if ($event['icon'] === 'money-bill-wave' && !$event['date'] && 
                                ($booking['payment_status'] !== 'completed' && $booking['payment_status'] !== 'paid')) {
                                continue;
                            }
                        ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas fa-<?php echo $event['icon']; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1"><?php echo $event['title']; ?></h6>
                                        <?php if (!empty($event['date'])): ?>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($event['date'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-0"><?php echo $event['description']; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Booking Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Booking Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Booking ID:</span>
                        <span>#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Check-in:</span>
                        <span><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Check-out:</span>
                        <span><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Monthly Rent:</span>
                        <span>KSh <?php echo number_format(floatval($booking['property_price']), 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Security Deposit (One-time):</span>
                        <span>KSh <?php echo number_format(floatval($booking['security_deposit'] ?? 0), 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold mt-3 pt-2 border-top">
                        <span>Initial Payment Required:</span>
                        <span>KSh <?php echo number_format(floatval($booking['property_price']) + floatval($booking['security_deposit'] ?? 0), 2); ?></span>
                    </div>
                    
                    <?php if (($booking['payment_status'] !== 'completed' && $booking['payment_status'] !== 'paid') && $booking['status'] === 'confirmed'): ?>
                        <a href="booking_payment.php?id=<?php echo $booking['id']; ?>" class="btn btn-success w-100 mt-3">
                            <i class="fas fa-credit-card me-1"></i> Make Payment
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($canReview): ?>
                        <button class="btn btn-outline-primary w-100 mt-2" 
                                data-bs-toggle="modal" 
                                data-bs-target="#leaveReviewModal">
                            <i class="far fa-star me-1"></i> Leave a Review
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <h6>Property Owner</h6>
                    <p class="mb-1"><?php echo htmlspecialchars($booking['landlord_name'] ?? 'Property Owner'); ?></p>
                    <?php if (!empty($booking['landlord_phone']) && $booking['landlord_phone'] !== 'N/A'): ?>
                        <p class="mb-1">
                            <i class="fas fa-phone me-2"></i>
                            <a href="tel:<?php echo htmlspecialchars($booking['landlord_phone']); ?>">
                                <?php echo htmlspecialchars($booking['landlord_phone']); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($booking['landlord_email']) && $booking['landlord_email'] !== 'contact@property.com'): ?>
                        <p class="mb-0">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:<?php echo htmlspecialchars($booking['landlord_email']); ?>">
                                <?php echo htmlspecialchars($booking['landlord_email']); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="leaveReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="reviewForm" action="submit_review.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Leave a Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                    <input type="hidden" name="property_id" value="<?php echo $booking['house_id']; ?>">
                    
                    <div class="text-center mb-4">
                        <div class="rating-stars mb-2">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" 
                                       <?php echo $i === 5 ? 'checked' : ''; ?>>
                                <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted small">Rate your experience</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reviewTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="reviewTitle" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reviewText" class="form-label">Your Review</label>
                        <textarea class="form-control" id="reviewText" name="review" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline:before {
    content: '';
    position: absolute;
    left: 12px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-icon {
    position: absolute;
    left: -30px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #0d6efd;
    color: #0d6efd;
    display: flex;
    align-items: center;
    justify-content: center;
}
.timeline-content {
    padding-left: 15px;
}
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle review form submission
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Here you would typically submit the form via AJAX
            // For demo, we'll just show an alert and close the modal
            alert('Thank you for your review!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('leaveReviewModal'));
            modal.hide();
            
            // In a real app, you would submit the form data to the server
            // this.submit();
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
