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

// Debug mode
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

if ($debug) {
    echo "<h1>DEBUG MODE - My Bookings</h1>\n";
    echo "<p>Session ID: " . session_id() . "</p>\n";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>\n";
}

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

if ($debug) {
    echo "<h2>DEBUG: Raw Bookings Data</h2>\n";
    echo "<p>Total bookings found: " . count($bookings) . "</p>\n";
    echo "<pre>" . print_r($bookings, true) . "</pre>\n";
}

// Get current month payment status for each booking
$processed_bookings = [];
foreach ($bookings as $booking) {
    // Create a copy of the booking to avoid reference issues
    $processed_booking = $booking;
    
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
            $processed_booking['current_month_status'] = $result;
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
                
                $processed_booking['current_month_status'] = [
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
                
                $processed_booking['current_month_status'] = [
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
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $processed_booking['current_month_status'] = $result;
        } else {
            $processed_booking['current_month_status'] = [
                'status' => 'not_started',
                'payment_date' => null,
                'amount' => null
            ];
        }
    }
    
    $processed_bookings[] = $processed_booking;
}

// Replace the original bookings array with the processed one
$bookings = $processed_bookings;

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
                            <?php 
                            $row_counter = 0;
                            foreach ($bookings as $booking): 
                                $row_counter++;
                                $statusClass = [
                                    'pending' => 'warning',
                                    'confirmed' => 'success',
                                    'cancelled' => 'danger',
                                    'completed' => 'secondary',
                                    'active' => 'info'
                                ][$booking['status']] ?? 'secondary';
                            ?>
                            <tr data-booking-id="<?php echo $booking['id']; ?>" data-row-number="<?php echo $row_counter; ?>">
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
                                            <?php if ($debug): ?>
                                            <br><small class="text-danger">DEBUG: Row #<?php echo $row_counter; ?>, Booking ID: <?php echo $booking['id']; ?></small>
                                            <?php endif; ?>
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
// Prevent duplicate event listeners by checking if already initialized
if (typeof window.bookingsPageInitialized === 'undefined') {
    window.bookingsPageInitialized = true;
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('My Bookings page initialized');
        
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
        
        // Handle monthly payments modal
        var monthlyPaymentsModal = document.getElementById('monthlyPaymentsModal');
        if (monthlyPaymentsModal) {
            monthlyPaymentsModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var bookingId = button.getAttribute('data-booking-id');
                
                console.log('Loading monthly payments for booking ID:', bookingId);
                
                // Show loading state
                var content = document.getElementById('monthlyPaymentsContent');
                content.innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading payment history...</p>
                    </div>
                `;
                
                // Load monthly payments data
                fetch('get_monthly_payments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'booking_id=' + bookingId
                })
                .then(response => {
                    console.log('Monthly payments response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Monthly payments data:', data);
                    
                    if (data.success) {
                        displayMonthlyPayments(data);
                    } else {
                        content.innerHTML = `
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                <h6 class="text-warning">Error Loading Payments</h6>
                                <p class="text-muted">${data.message || 'Failed to load payment history'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading monthly payments:', error);
                    content.innerHTML = `
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                            <h6 class="text-danger">Error Loading Payments</h6>
                            <p class="text-muted">Failed to load payment history. Please try again.</p>
                        </div>
                    `;
                });
            });
        }
        
        // Function to display monthly payments
        function displayMonthlyPayments(data) {
            var content = document.getElementById('monthlyPaymentsContent');
            var payments = data.data || [];
            var booking = data.booking || {};
            
            if (payments.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h6>No Payment History</h6>
                        <p class="text-muted">No monthly payments have been recorded for this booking.</p>
                    </div>
                `;
                return;
            }
            
            // Calculate summary
            var totalPaid = 0;
            var totalUnpaid = 0;
            var totalOverdue = 0;
            
            payments.forEach(function(payment) {
                if (payment.status === 'paid') {
                    totalPaid += parseFloat(payment.amount);
                } else if (payment.status === 'unpaid') {
                    totalUnpaid += parseFloat(payment.amount);
                } else if (payment.status === 'overdue') {
                    totalOverdue += parseFloat(payment.amount);
                }
            });
            
            var html = `
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <small class="text-muted">Total Months</small>
                                        <div class="fw-bold">${payments.length}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Paid</small>
                                        <div class="fw-bold text-success">${payments.filter(p => p.status === 'paid').length}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Unpaid</small>
                                        <div class="fw-bold text-warning">${payments.filter(p => p.status === 'unpaid').length}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Overdue</small>
                                        <div class="fw-bold text-danger">${payments.filter(p => p.status === 'overdue').length}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
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
                        <tbody>
            `;
            
            payments.forEach(function(payment) {
                var monthFormatted = new Date(payment.month).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long' 
                });
                
                var statusBadge = '';
                if (payment.status === 'paid') {
                    statusBadge = '<span class="badge bg-success">Paid</span>';
                } else if (payment.status === 'unpaid') {
                    statusBadge = '<span class="badge bg-warning">Unpaid</span>';
                } else if (payment.status === 'overdue') {
                    statusBadge = '<span class="badge bg-danger">Overdue</span>';
                } else {
                    statusBadge = '<span class="badge bg-secondary">' + payment.status + '</span>';
                }
                
                var paymentDate = payment.payment_date ? 
                    new Date(payment.payment_date).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '-';
                
                var paymentMethod = payment.payment_method || '-';
                
                html += `
                    <tr>
                        <td><strong>${monthFormatted}</strong></td>
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
            `;
            
            content.innerHTML = html;
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
                                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                            </div>
                            <div class="toast-body">${message}</div>
                        </div>
                    </div>
                `);
            }
            
            var toast = new bootstrap.Toast(document.querySelector('#toastContainer .toast'));
            toast.show();
        }
        
        // Debug: Log the number of booking rows found
        var bookingTable = document.querySelector('.card.mb-5 .table-responsive table tbody');
        var bookingRows = bookingTable ? bookingTable.querySelectorAll('tr') : [];
        console.log('Found ' + bookingRows.length + ' booking rows in the bookings table');
        
        // Debug: Log each booking row
        bookingRows.forEach(function(row, index) {
            var bookingId = row.getAttribute('data-booking-id');
            var rowNumber = row.getAttribute('data-row-number');
            var location = row.querySelector('small') ? row.querySelector('small').textContent : 'No location found';
            console.log('Row ' + index + ': Booking ID ' + bookingId + ', Row #' + rowNumber + ', Location: ' + location);
        });
        
        // Additional debug: Check for duplicate booking IDs
        var bookingIds = [];
        bookingRows.forEach(function(row) {
            var bookingId = row.getAttribute('data-booking-id');
            if (bookingId) {
                bookingIds.push(bookingId);
            }
        });
        
        // Check for duplicates
        var uniqueIds = [...new Set(bookingIds)];
        if (bookingIds.length !== uniqueIds.length) {
            console.error('DUPLICATE BOOKING IDs FOUND!');
            console.log('All booking IDs:', bookingIds);
            console.log('Unique booking IDs:', uniqueIds);
        } else {
            console.log('No duplicate booking IDs found');
        }
        
        // Debug: Check for duplicate row numbers
        var rowNumbers = [];
        bookingRows.forEach(function(row) {
            var rowNumber = row.getAttribute('data-row-number');
            if (rowNumber) {
                rowNumbers.push(rowNumber);
            }
        });
        
        console.log('Row numbers found:', rowNumbers);
        
        // Check if row numbers are sequential
        var expectedRowNumbers = [];
        for (var i = 1; i <= bookingRows.length; i++) {
            expectedRowNumbers.push(i.toString());
        }
        
        if (JSON.stringify(rowNumbers) !== JSON.stringify(expectedRowNumbers)) {
            console.error('ROW NUMBERS ARE NOT SEQUENTIAL!');
            console.log('Expected:', expectedRowNumbers);
            console.log('Found:', rowNumbers);
        } else {
            console.log('Row numbers are sequential');
        }
        
        // Additional debug: Check all tables on the page
        var allTables = document.querySelectorAll('table');
        console.log('Total tables found on page:', allTables.length);
        
        allTables.forEach(function(table, tableIndex) {
            var tableRows = table.querySelectorAll('tbody tr');
            console.log('Table ' + tableIndex + ' has ' + tableRows.length + ' rows');
        });
    });
} else {
    console.log('My Bookings page already initialized, skipping duplicate initialization');
}
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
