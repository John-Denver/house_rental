<?php
// Session is already started in auth.php
require_once '../config/db.php';
require_once '../config/auth.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$pageTitle = 'My Bookings & Viewings';

// Get user's scheduled viewings with house information
$stmt = $conn->prepare("
    SELECT pv.*, 
           h.house_no as property_title,
           h.location as location,
           h.main_image as main_image
    FROM property_viewings pv
    LEFT JOIN houses h ON pv.property_id = h.id
    WHERE pv.user_id = ?
    ORDER BY pv.viewing_date DESC, pv.viewing_time DESC
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$viewings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's rental bookings with house information
$stmt = $conn->prepare("
    SELECT rb.*, 
           h.house_no as property_title,
           h.location as location,
           h.main_image as main_image,
           h.price as monthly_rent
    FROM rental_bookings rb
    LEFT JOIN houses h ON rb.house_id = h.id
    WHERE rb.user_id = ?
    ORDER BY rb.created_at DESC
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Include header after all processing is done
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Bookings & Viewings</h1>
        <a href="index.php" class="btn btn-outline-primary">
            <i class="fas fa-home me-1"></i> Back to Properties
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

    <!-- Rental Bookings Section -->
    <div class="card mb-5">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-key me-2"></i>My Rental Bookings
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($bookings)): ?>
                <div class="text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-home fa-3x text-muted opacity-25"></i>
                    </div>
                    <h6 class="mb-2">No Rental Bookings</h6>
                    <p class="text-muted mb-3">You don't have any active rental bookings. Browse our properties to make a booking.</p>
                    <a href="index.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-search me-1"></i> Browse Properties
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Property</th>
                                <th>Move-in Date</th>
                                <th>Monthly Rent</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): 
                                $statusClass = [
                                    'pending' => 'warning',
                                    'confirmed' => 'success',
                                    'cancelled' => 'danger',
                                    'completed' => 'secondary',
                                    'active' => 'info'
                                ][$booking['status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($booking['main_image'])): 
                                            $imagePath = '../uploads/' . $booking['main_image'];
                                            if (file_exists($imagePath)): ?>
                                            <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                                 alt="<?php echo htmlspecialchars($booking['property_title']); ?>" 
                                                 class="rounded me-3" style="width: 60px; height: 45px; object-fit: cover;">
                                            <?php else: ?>
                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 45px;">
                                                <i class="fas fa-home text-muted"></i>
                                            </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 45px;">
                                                <i class="fas fa-home text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($booking['property_title']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['location']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div><?php echo date('M j, Y', strtotime($booking['start_date'])); ?></div>
                                        <small class="text-muted">Booking #<?php echo $booking['id']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold">KSh <?php echo number_format($booking['monthly_rent'] ?? $booking['price'], 2); ?></div>
                                    <small class="text-muted">per month</small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="property.php?id=<?php echo $booking['house_id']; ?>" 
                                           class="btn btn-outline-primary"
                                           title="View Property">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="booking_details.php?id=<?php echo $booking['id']; ?>" 
                                           class="btn btn-outline-info"
                                           title="View Details">
                                            <i class="fas fa-info-circle"></i>
                                        </a>
                                        <?php if ($booking['status'] === 'pending'): ?>
                                        <a href="booking_payment.php?booking_id=<?php echo $booking['id']; ?>" 
                                           class="btn btn-outline-success"
                                           title="Make Payment">
                                            <i class="fas fa-credit-card"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Property Viewings Section -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="fas fa-calendar-check me-2"></i>My Scheduled Viewings
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($viewings)): ?>
                <div class="text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-calendar-check fa-3x text-muted opacity-25"></i>
                    </div>
                    <h6 class="mb-2">No Scheduled Viewings</h6>
                    <p class="text-muted mb-3">You don't have any scheduled viewings. Browse our properties to schedule one.</p>
                    <a href="index.php" class="btn btn-info btn-sm">
                        <i class="fas fa-search me-1"></i> Browse Properties
                    </a>
                </div>
            <?php else: ?>
                <?php 
                // Filter viewings if status filter is applied
                $filteredViewings = $viewings;
                if (isset($_GET['status']) && $_GET['status'] !== 'all') {
                    $filteredViewings = array_filter($viewings, function($v) {
                        return $v['status'] === $_GET['status'];
                    });
                }
                
                if (empty($filteredViewings)): 
                ?>
                <div class="text-center py-4">
                    <div class="mb-3">
                        <i class="far fa-calendar-alt fa-3x text-muted opacity-25"></i>
                    </div>
                    <h6 class="mb-2">No Viewings Found</h6>
                    <p class="text-muted mb-3">No viewings match the selected filter.</p>
                    <a href="?status=all" class="btn btn-info btn-sm">
                        <i class="fas fa-undo me-1"></i> Reset Filter
                    </a>
                </div>
                <?php else: ?>
                    <div class="mb-3">
                        <div class="btn-group" role="group">
                            <a href="?status=all" class="btn btn-sm btn-outline-info <?php echo (!isset($_GET['status']) || $_GET['status'] === 'all') ? 'active' : ''; ?>">All</a>
                            <a href="?status=pending" class="btn btn-sm btn-outline-warning <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'active' : ''; ?>">Pending</a>
                            <a href="?status=confirmed" class="btn btn-sm btn-outline-success <?php echo (isset($_GET['status']) && $_GET['status'] === 'confirmed') ? 'active' : ''; ?>">Confirmed</a>
                            <a href="?status=cancelled" class="btn btn-sm btn-outline-danger <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelled') ? 'active' : ''; ?>">Cancelled</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Property</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredViewings as $viewing): 
                                    $statusClass = [
                                        'pending' => 'warning',
                                        'confirmed' => 'success',
                                        'cancelled' => 'danger',
                                        'completed' => 'secondary'
                                    ][$viewing['status']] ?? 'secondary';
                                    
                                    $viewingDateTime = new DateTime($viewing['viewing_date'] . ' ' . $viewing['viewing_time']);
                                    $now = new DateTime();
                                    $isPast = $viewingDateTime < $now;
                                ?>
                                <tr class="<?php echo $isPast ? 'text-muted' : ''; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($viewing['main_image'])): 
                                                $imagePath = '../uploads/' . $viewing['main_image'];
                                                if (file_exists($imagePath)): ?>
                                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                                     alt="<?php echo htmlspecialchars($viewing['property_title']); ?>" 
                                                     class="rounded me-3" style="width: 60px; height: 45px; object-fit: cover;">
                                                <?php else: ?>
                                                <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 45px;">
                                                    <i class="fas fa-home text-muted"></i>
                                                </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 45px;">
                                                    <i class="fas fa-home text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($viewing['property_title']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($viewing['location']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div><?php echo $viewingDateTime->format('F j, Y'); ?></div>
                                            <small class="text-muted"><?php echo $viewingDateTime->format('g:i A'); ?></small>
                                            <?php if ($isPast): ?>
                                            <div><small class="text-danger">(Past)</small></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($viewing['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="property.php?id=<?php echo $viewing['property_id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="View Property">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($viewing['status'] === 'pending' && !$isPast): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger cancel-viewing" 
                                                    data-id="<?php echo $viewing['id']; ?>"
                                                    title="Cancel Viewing">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cancel Viewing Modal -->
<div class="modal fade" id="cancelViewingModal" tabindex="-1" aria-labelledby="cancelViewingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="cancelViewingForm" action="cancel_viewing.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelViewingModalLabel">Cancel Viewing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="viewing_id" id="cancelViewingId">
                    <p>Are you sure you want to cancel this viewing? This action cannot be undone.</p>
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
                        <i class="fas fa-times me-1"></i> Cancel Viewing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- Include jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle cancel viewing modal
    var cancelViewingModal = document.getElementById('cancelViewingModal');
    if (cancelViewingModal) {
        cancelViewingModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var viewingId = button.getAttribute('data-id');
            var modalInput = cancelViewingModal.querySelector('#cancelViewingId');
            modalInput.value = viewingId;
            
            // Reset form
            var form = cancelViewingModal.querySelector('form');
            if (form) {
                form.reset();
            }
        });
    }
    
    // Handle form submission via AJAX
    $('#cancelViewingForm').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var originalBtnText = submitBtn.html();
        
        // Disable button and show loading state
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showToast('Success', response.message, 'success');
                    
                    // Close modal
                    var modal = bootstrap.Modal.getInstance(cancelViewingModal);
                    modal.hide();
                    
                    // Reload page after a short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    showToast('Error', response.message || 'An error occurred. Please try again.', 'error');
                    submitBtn.prop('disabled', false).html(originalBtnText);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showToast('Error', 'An error occurred while processing your request. Please try again.', 'error');
                submitBtn.prop('disabled', false).html(originalBtnText);
            }
        });
    });
    
    // Function to show toast notifications
    function showToast(title, message, type = 'info') {
        // Create toast HTML if it doesn't exist
        if (!$('#toastContainer').length) {
            $('body').append(`
                <div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 1100;">
                    <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="toast-header">
                            <strong class="me-auto">${title}</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body">
                            ${message}
                        </div>
                    </div>
                </div>
            `);
        }
        
        // Set toast type
        var toast = $('.toast');
        toast.removeClass('bg-success bg-danger bg-info bg-warning');
        
        switch(type) {
            case 'success':
                toast.addClass('bg-success text-white');
                break;
            case 'error':
                toast.addClass('bg-danger text-white');
                break;
            case 'warning':
                toast.addClass('bg-warning text-dark');
                break;
            default:
                toast.addClass('bg-info text-white');
        }
        
        // Show toast
        var bsToast = new bootstrap.Toast(toast[0], { autohide: true, delay: 5000 });
        bsToast.show();
    }
    
    // Add click handler for cancel viewing buttons
    document.querySelectorAll('.cancel-viewing').forEach(button => {
        button.addEventListener('click', function() {
            const viewingId = this.getAttribute('data-id');
            const modal = new bootstrap.Modal(document.getElementById('cancelViewingModal'));
            document.getElementById('cancelViewingId').value = viewingId;
            modal.show();
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
