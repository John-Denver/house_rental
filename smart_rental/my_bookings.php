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

// Get current month payment status for each booking
foreach ($bookings as &$booking) {
    // Get the current month (today's month)
    $currentMonth = date('Y-m-01');
    $today = date('Y-m-d');
    $moveInDate = strtotime($booking['start_date']);
    $moveInMonth = date('Y-m-01', $moveInDate);
    
    // Check if the booking has started (current date is after or equal to start date)
    $bookingHasStarted = $today >= $booking['start_date'];
    
    if ($bookingHasStarted) {
        // Booking has started - check current month payment
        $stmt = $conn->prepare("
            SELECT status, payment_date, amount 
            FROM monthly_rent_payments 
            WHERE booking_id = ? AND month = ?
        ");
        $stmt->bind_param('is', $booking['id'], $currentMonth);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $booking['current_month_status'] = $result;
        } else {
            // Check if there's a payment in booking_payments for current month
            $stmt = $conn->prepare("
                SELECT payment_date, amount, payment_method, transaction_id
                FROM booking_payments 
                WHERE booking_id = ? AND status = 'completed' 
                AND DATE_FORMAT(payment_date, '%Y-%m-01') = ?
            ");
            $stmt->bind_param('is', $booking['id'], $currentMonth);
            $stmt->execute();
            $bookingPayment = $stmt->get_result()->fetch_assoc();
            
            if ($bookingPayment) {
                // Payment exists in booking_payments but not in monthly_rent_payments
                // Create the monthly_rent_payments record
                $insertStmt = $conn->prepare("
                    INSERT INTO monthly_rent_payments 
                    (booking_id, month, amount, status, payment_date, payment_method, mpesa_receipt_number, notes, is_first_payment, payment_type)
                    VALUES (?, ?, ?, 'paid', ?, ?, ?, ?, 0, 'monthly_rent')
                ");
                $insertStmt->bind_param('isdsisss', 
                    $booking['id'], 
                    $currentMonth, 
                    $bookingPayment['amount'],
                    $bookingPayment['payment_date'],
                    $bookingPayment['payment_method'],
                    $bookingPayment['transaction_id'],
                    'Auto-synced from booking_payments'
                );
                $insertStmt->execute();
                
                $booking['current_month_status'] = [
                    'status' => 'paid',
                    'payment_date' => $bookingPayment['payment_date'],
                    'amount' => $bookingPayment['amount']
                ];
            } else {
                // No payment for current month - check if it's overdue
                $currentMonthDay15 = date('Y-m-4');
                
                if ($today > $currentMonthDay15) {
                    $status = 'overdue';
                } else {
                    $status = 'unpaid';
                }
                
                $booking['current_month_status'] = [
                    'status' => $status,
                    'payment_date' => null,
                    'amount' => null
                ];
            }
        }
    } else {
        // Booking hasn't started yet - check if initial payment was made
        $stmt = $conn->prepare("
            SELECT status, payment_date, amount 
            FROM monthly_rent_payments 
            WHERE booking_id = ? AND is_first_payment = 1 AND status = 'paid'
        ");
        $stmt->bind_param('i', $booking['id']);
        $stmt->execute();
        $initialPayment = $stmt->get_result()->fetch_assoc();
        
        if ($initialPayment) {
            // Initial payment was made, so the move-in month is paid
            $booking['current_month_status'] = [
                'status' => 'paid',
                'payment_date' => $initialPayment['payment_date'],
                'amount' => $initialPayment['amount']
            ];
        } else {
            // No initial payment made yet
            $booking['current_month_status'] = [
                'status' => 'unpaid',
                'payment_date' => null,
                'amount' => null
            ];
        }
    }
}

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
    
    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Success!</strong> 
            <?php if (isset($_GET['payment']) && $_GET['payment'] == '1'): ?>
                Your booking payment has been processed successfully.
            <?php else: ?>
                Your booking has been created successfully.
            <?php endif; ?>
            <?php if (isset($_GET['booking_id'])): ?>
                <br><small class="text-muted">Booking ID: <?php echo htmlspecialchars($_GET['booking_id']); ?></small>
            <?php endif; ?>
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
                                <th>Current Month</th>
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
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary payment-status-btn" 
                                            data-booking-id="<?php echo $booking['id']; ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#monthlyPaymentsModal"
                                            title="View Payment History">
                                        <?php 
                                        $statusClass = [
                                            'paid' => 'success',
                                            'unpaid' => 'warning',
                                            'overdue' => 'danger'
                                        ][$booking['current_month_status']['status']] ?? 'secondary';
                                        
                                        $statusText = [
                                            'paid' => 'Paid',
                                            'unpaid' => 'Unpaid',
                                            'overdue' => 'Overdue'
                                        ][$booking['current_month_status']['status']] ?? ucfirst($booking['current_month_status']['status']);
                                        
                                        echo '<span class="badge bg-' . $statusClass . '">' . $statusText . '</span>';
                                        ?>
                                    </button>
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
                                        <?php if ($booking['status'] === 'confirmed' || $booking['status'] === 'active'): ?>
                                        <?php 
                                        // Use the new monthly payment tracker
                                        require_once 'monthly_payment_tracker.php';
                                        $tracker = new MonthlyPaymentTracker($conn);
                                        
                                        // Get next payment due
                                        $nextPaymentDue = $tracker->getNextPaymentDue($booking['id']);
                                        
                                        if ($nextPaymentDue) {
                                            $monthName = date('F Y', strtotime($nextPaymentDue['month']));
                                            $amount = number_format($nextPaymentDue['amount'], 2);
                                        ?>
                                        <a href="booking_payment.php?id=<?php echo $booking['id']; ?>" 
                                           class="btn btn-outline-primary"
                                           title="Pay <?php echo $monthName; ?> - KSh <?php echo $amount; ?>">
                                            <i class="fas fa-credit-card"></i>
                                        </a>
                                        <?php 
                                        } else {
                                        ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> All Paid
                                        </span>
                                        <?php 
                                        }
                                        ?>
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

<!-- Monthly Payments Modal -->
<div class="modal fade" id="monthlyPaymentsModal" tabindex="-1" aria-labelledby="monthlyPaymentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="monthlyPaymentsModalLabel">Payment History & Monthly Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="monthlyPaymentsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading payment history...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
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
    
    // Handle monthly payments modal
    var monthlyPaymentsModal = document.getElementById('monthlyPaymentsModal');
    if (monthlyPaymentsModal) {
        monthlyPaymentsModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var bookingId = button.getAttribute('data-booking-id');
            
            // Load monthly payments data
            loadMonthlyPayments(bookingId);
        });
    }
    
    // Function to load monthly payments
    function loadMonthlyPayments(bookingId) {
        $('#monthlyPaymentsContent').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading payment history...</p>
            </div>
        `);
        
        $.ajax({
            url: 'get_monthly_payments_new.php',
            type: 'POST',
            data: { booking_id: bookingId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayMonthlyPayments(response.data);
                } else {
                    $('#monthlyPaymentsContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${response.message || 'Failed to load payment history'}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                $('#monthlyPaymentsContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        An error occurred while loading payment history.<br>
                        <small class="text-muted">Error: ${error}</small>
                    </div>
                `);
            }
        });
    }
    
    // Function to display monthly payments
    function displayMonthlyPayments(data) {
        console.log('Displaying payments:', data);
        
        const payments = data.payments || [];
        const summary = data.summary || {};
        const nextPaymentDue = data.next_payment_due;
        const booking = data.booking || {};
        
        if (payments.length === 0) {
            $('#monthlyPaymentsContent').html(`
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h6>No Payment History</h6>
                    <p class="text-muted">No monthly payments have been recorded for this booking.</p>
                </div>
            `);
            return;
        }
        
        // Sort payments: current month first, then chronological order (oldest to newest)
        const currentMonth = new Date().toISOString().slice(0, 7) + '-01';
        payments.sort(function(a, b) {
            // If a is current month, it comes first
            if (a.month === currentMonth) return -1;
            // If b is current month, it comes first
            if (b.month === currentMonth) return 1;
            // Otherwise, sort by date (oldest first - chronological order)
            return new Date(a.month) - new Date(b.month);
        });
        
        // Show current month and one previous month initially
        const currentMonthIndex = payments.findIndex(p => p.month === currentMonth);
        
        // Get current month and one previous month
        let recentMonths = [];
        let olderMonths = [];
        
        if (currentMonthIndex !== -1) {
            // Current month is found
            recentMonths.push(payments[currentMonthIndex]);
            
            // Add one previous month if available
            if (currentMonthIndex + 1 < payments.length) {
                recentMonths.push(payments[currentMonthIndex + 1]);
                olderMonths = payments.filter((_, index) => index !== currentMonthIndex && index !== currentMonthIndex + 1);
            } else {
                // No previous month available
                olderMonths = payments.filter((_, index) => index !== currentMonthIndex);
            }
        } else {
            // Current month not found, show first 2 months
            recentMonths = payments.slice(0, 2);
            olderMonths = payments.slice(2);
        }
        
        console.log('Recent months (current + 1 previous):', recentMonths.length, 'Older months:', olderMonths.length);
        
        // Use summary data if available, otherwise calculate from payments
        const totalMonths = summary.total_months || payments.length;
        const paidMonths = summary.paid_months || payments.filter(p => p.status === 'paid').length;
        const unpaidMonths = summary.unpaid_months || payments.filter(p => p.status === 'unpaid').length;
        const overdueMonths = payments.filter(p => p.status === 'overdue').length;
        const totalAmount = (summary.total_paid || 0) + (summary.total_unpaid || 0);
        const paidAmount = summary.total_paid || payments.filter(p => p.status === 'paid').reduce((sum, p) => sum + parseFloat(p.amount), 0);
        
        let html = `
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <div class="row text-center">
                                <div class="col-md-2">
                                    <small class="text-muted">Total Months</small>
                                    <div class="fw-bold">${totalMonths}</div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Paid</small>
                                    <div class="fw-bold text-success">${paidMonths}</div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Unpaid</small>
                                    <div class="fw-bold text-warning">${unpaidMonths}</div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Overdue</small>
                                    <div class="fw-bold text-danger">${overdueMonths}</div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Total Amount</small>
                                    <div class="fw-bold">KSh ${totalAmount.toLocaleString()}</div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Paid Amount</small>
                                    <div class="fw-bold text-success">KSh ${paidAmount.toLocaleString()}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            ${nextPaymentDue ? `
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Next Payment Due</h6>
                                <p class="mb-0">${nextPaymentDue.month_display} - KSh ${parseFloat(nextPaymentDue.amount).toLocaleString()}</p>
                            </div>
                            <div>
                                <button type="button" class="btn btn-primary btn-sm" onclick="makePayment(${booking.id}, ${nextPaymentDue.amount})">
                                    <i class="fas fa-credit-card me-2"></i>Make Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            ` : ''}
            
            <div class="row">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Month</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment Date</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody id="paymentsTableBody">
        `;
        
        recentMonths.forEach(function(payment) {
            const monthFormatted = new Date(payment.month).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long' 
            });
            const statusBadge = getPaymentStatusBadge(payment.status);
            const paymentDate = payment.payment_date ? 
                new Date(payment.payment_date).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }) : '-';
            const paymentMethod = payment.payment_method || '-';
            
            // Add special styling for current month
            const isCurrentMonth = payment.month === currentMonth;
            const rowClass = isCurrentMonth ? 'table-primary' : '';
            const currentMonthIndicator = isCurrentMonth ? ' <span class="badge bg-primary">Current</span>' : '';
            
            html += `
                <tr class="${rowClass}">
                    <td><strong>${monthFormatted}${currentMonthIndicator}</strong></td>
                    <td>KSh ${parseFloat(payment.amount).toLocaleString()}</td>
                    <td>${statusBadge}</td>
                    <td>${paymentDate}</td>
                    <td>${paymentMethod}</td>
                </tr>
            `;
        });
        
        html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        // Add "View Previous Months" button if there are older months
        if (olderMonths.length > 0) {
            console.log('Adding View Previous Months button with', olderMonths.length, 'months');
            console.log('Older months data:', olderMonths);
            
            // Store the data in a global variable instead of data attribute
            window.olderMonthsData = olderMonths;
            
                           html += `
                   <div class="row mt-3">
                       <div class="col-12 text-center">
                           <button type="button" class="btn btn-outline-secondary" id="viewPreviousMonths">
                               <i class="fas fa-history me-2"></i>
                               View All Previous Months (${olderMonths.length} more)
                           </button>
                       </div>
                   </div>
               `;
        } else {
            console.log('No older months to show');
        }
        
        $('#monthlyPaymentsContent').html(html);
        
        // Debug: Check if button exists
        setTimeout(function() {
            if ($('#viewPreviousMonths').length > 0) {
                console.log('✓ View Previous Months button found');
            } else {
                console.log('✗ View Previous Months button not found');
            }
        }, 100);
        
        // Add click handler for "View Previous Months" button using event delegation
        $(document).off('click', '#viewPreviousMonths').on('click', '#viewPreviousMonths', function() {
            console.log('View Previous Months clicked');
            const olderMonths = window.olderMonthsData;
            console.log('Older months:', olderMonths);
            
            let additionalRows = '';
            olderMonths.forEach(function(payment) {
                const monthFormatted = new Date(payment.month).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long' 
                });
                const statusBadge = getPaymentStatusBadge(payment.status);
                const paymentDate = payment.payment_date ? 
                    new Date(payment.payment_date).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '-';
                const paymentMethod = payment.payment_method || '-';
                
                additionalRows += `
                    <tr class="table-light">
                        <td><strong>${monthFormatted}</strong></td>
                        <td>KSh ${parseFloat(payment.amount).toLocaleString()}</td>
                        <td>${statusBadge}</td>
                        <td>${paymentDate}</td>
                        <td>${paymentMethod}</td>
                    </tr>
                `;
            });
            
            $('#paymentsTableBody').append(additionalRows);
            $(this).remove();
        });
    }
    
    // Helper function to get payment status badge HTML
    function getPaymentStatusBadge(status) {
        const statusClass = {
            'paid': 'success',
            'unpaid': 'warning',
            'overdue': 'danger'
        }[status] || 'secondary';
        
        const statusText = {
            'paid': 'Paid',
            'unpaid': 'Unpaid',
            'overdue': 'Overdue'
        }[status] || status.charAt(0).toUpperCase() + status.slice(1);
        
        return `<span class="badge bg-${statusClass}">${statusText}</span>`;
    }
    
    // Function to handle payment processing
    function makePayment(bookingId, amount) {
        // Redirect to the payment page
        window.location.href = `booking_payment.php?id=${bookingId}&amount=${amount}`;
    }
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
