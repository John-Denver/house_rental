<?php
session_start();
require_once '../config/db.php';
require_once '../controllers/BookingController.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit();
}

$bookingController = new BookingController($conn);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $bookingId = $_POST['booking_id'] ?? null;
        $status = $_POST['status'] ?? null;
        $reason = $_POST['reason'] ?? null;
        
        if (!$bookingId || !$status) {
            throw new Exception('Missing required parameters');
        }
        
        $bookingController->updateBookingStatus($bookingId, $status, $reason, $_SESSION['user_id']);
        $_SESSION['success'] = 'Booking status updated successfully';
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    // Redirect to avoid form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get bookings with filters
$bookings = [];
$totalBookings = 0;

try {
    // Build the query
    $query = "
        SELECT SQL_CALC_FOUND_ROWS
            b.*, 
            h.house_no, 
            h.location,
            u.name as user_name,
            u.username as user_email,
            l.name as landlord_name,
            (SELECT status FROM booking_payments WHERE booking_id = b.id ORDER BY payment_date DESC LIMIT 1) as payment_status
        FROM rental_bookings b
        JOIN houses h ON b.house_id = h.id
        JOIN users u ON b.user_id = u.id
        JOIN users l ON b.landlord_id = l.id
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    // Apply status filter
    if ($status !== 'all') {
        $query .= " AND b.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (
            b.id = ? OR 
            h.house_no LIKE ? OR 
            u.name LIKE ? OR 
            u.username LIKE ? OR
            l.name LIKE ?
        )";
        
        $searchTerm = "%$search%";
        $params = array_merge($params, [
            $search,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm
        ]);
        $types .= 'issss';
    }
    
    // Add sorting and pagination
    $query .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get total count
    $totalResult = $conn->query("SELECT FOUND_ROWS() as total");
    $totalBookings = $totalResult->fetch_assoc()['total'];
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Include header
$pageTitle = 'Manage Bookings';
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Manage Bookings</h1>
        <div>
            <a href="export_bookings.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-download me-1"></i> Export
            </a>
        </div>
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
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Search by ID, property, or customer" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="?" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bookings Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Property</th>
                            <th>Customer</th>
                            <th>Dates</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="mb-0">No bookings found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-2">
                                                <div class="bg-light rounded" style="width: 40px; height: 40px;">
                                                    <?php if (!empty($booking['main_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($booking['main_image']); ?>" 
                                                             alt="<?php echo htmlspecialchars($booking['house_no']); ?>" 
                                                             class="img-fluid rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                                            <i class="fas fa-home"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold"><?php echo htmlspecialchars($booking['house_no']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['location']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($booking['user_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($booking['user_email']); ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></div>
                                        <small class="text-muted">to <?php echo date('M d, Y', strtotime($booking['end_date'])); ?></small>
                                    </td>
                                    <td>KSh <?php echo number_format($booking['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $booking['status'] === 'confirmed' ? 'success' : 
                                                ($booking['status'] === 'pending' ? 'warning' : 
                                                ($booking['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $booking['payment_status'] === 'paid' ? 'success' : 
                                                ($booking['payment_status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($booking['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="booking_details.php?id=<?php echo $booking['id']; ?>">
                                                        <i class="fas fa-eye me-2"></i> View Details
                                                    </a>
                                                </li>
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <li>
                                                        <form method="post" class="d-inline" 
                                                              onsubmit="return confirm('Are you sure you want to confirm this booking?');">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button type="submit" class="dropdown-item text-success">
                                                                <i class="fas fa-check me-2"></i> Confirm
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if ($booking['status'] !== 'cancelled' && $booking['status'] !== 'completed'): ?>
                                                    <li>
                                                        <button type="button" class="dropdown-item text-danger" 
                                                                data-bs-toggle="modal" data-bs-target="#cancelModal"
                                                                data-booking-id="<?php echo $booking['id']; ?>">
                                                            <i class="fas fa-times me-2"></i> Cancel
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if ($booking['status'] === 'confirmed'): ?>
                                                    <li>
                                                        <form method="post" class="d-inline" 
                                                              onsubmit="return confirm('Mark this booking as completed?');">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="status" value="completed">
                                                            <button type="submit" class="dropdown-item text-primary">
                                                                <i class="fas fa-check-double me-2"></i> Complete
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a href="../booking_invoice.php?id=<?php echo $booking['id']; ?>" target="_blank" class="dropdown-item">
                                                        <i class="fas fa-file-invoice me-2"></i> View Invoice
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalBookings > $limit): ?>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing <?php echo count($bookings); ?> of <?php echo $totalBookings; ?> bookings
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            $totalPages = ceil($totalBookings / $limit);
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            $startPage = max(1, $endPage - 4);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cancel Booking Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="status" value="cancelled">
                    <input type="hidden" name="booking_id" id="cancelBookingId">
                    
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" id="cancelReason" name="reason" rows="3" required></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. The customer will be notified of the cancellation.
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize cancel modal
    var cancelModal = document.getElementById('cancelModal');
    if (cancelModal) {
        cancelModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var bookingId = button.getAttribute('data-booking-id');
            var modalInput = cancelModal.querySelector('#cancelBookingId');
            modalInput.value = bookingId;
        });
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
