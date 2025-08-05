<?php
session_start();
require_once '../config/db.php';
require_once '../smart_rental/controllers/BookingController.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
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
        
        // Get current property units before update for logging
        $stmt = $conn->prepare("
            SELECT h.available_units, h.total_units, h.house_no, b.status as current_status
            FROM rental_bookings b
            JOIN houses h ON b.house_id = h.id
            WHERE b.id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $propertyInfo = $stmt->get_result()->fetch_assoc();
        
        if ($propertyInfo) {
            $oldUnits = $propertyInfo['available_units'];
            $oldStatus = $propertyInfo['current_status'];
        }
        
        // Update booking status (this will trigger unit automation)
        $bookingController->updateBookingStatus($bookingId, $status, $reason, $_SESSION['user_id']);
        
        // Get updated property units for feedback
        if ($propertyInfo) {
            $stmt = $conn->prepare("
                SELECT h.available_units, h.total_units
                FROM rental_bookings b
                JOIN houses h ON b.house_id = h.id
                WHERE b.id = ?
            ");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $updatedProperty = $stmt->get_result()->fetch_assoc();
            
            if ($updatedProperty) {
                $newUnits = $updatedProperty['available_units'];
                $unitChange = $newUnits - $oldUnits;
                
                $_SESSION['success'] = "Booking #$bookingId status updated from '$oldStatus' to '$status'. " .
                                      "Property units changed from $oldUnits to $newUnits (change: $unitChange)";
            } else {
                $_SESSION['success'] = 'Booking status updated successfully';
            }
        } else {
            $_SESSION['success'] = 'Booking status updated successfully';
        }
        
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
            h.available_units,
            h.total_units,
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
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get total count
    $totalResult = $conn->query("SELECT FOUND_ROWS() as total");
    $totalBookings = $totalResult->fetch_assoc()['total'];
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error fetching bookings: ' . $e->getMessage();
}

$totalPages = ceil($totalBookings / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'navbar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Bookings</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="test_unit_automation.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-cogs"></i> Test Unit Automation
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by booking ID, property, tenant, or landlord">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Bookings (<?php echo $totalBookings; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Property</th>
                                        <th>Tenant</th>
                                        <th>Landlord</th>
                                        <th>Dates</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Units</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($bookings)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
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
                                                <td><?php echo htmlspecialchars($booking['landlord_name']); ?></td>
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
                                                    <span class="badge bg-<?php echo $booking['available_units'] > 0 ? 'success' : 'danger'; ?>">
                                                        <?php echo $booking['available_units']; ?>/<?php echo $booking['total_units']; ?>
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
                                                                <a class="dropdown-item" href="../smart_rental/booking_details.php?id=<?php echo $booking['id']; ?>" target="_blank">
                                                                    <i class="fas fa-eye me-2"></i> View Details
                                                                </a>
                                                            </li>
                                                            <?php if ($booking['status'] === 'pending'): ?>
                                                                <li>
                                                                    <form method="post" class="d-inline" 
                                                                          onsubmit="return confirm('Confirm this booking? This will DECREMENT available units.');">
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
                                                                          onsubmit="return confirm('Mark as completed?');">
                                                                        <input type="hidden" name="action" value="update_status">
                                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                        <input type="hidden" name="status" value="completed">
                                                                        <button type="submit" class="dropdown-item text-info">
                                                                            <i class="fas fa-flag-checkered me-2"></i> Complete
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
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
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($status) && $status !== 'all' ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($status) && $status !== 'all' ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($status) && $status !== 'all' ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
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
                        <input type="hidden" name="booking_id" id="cancelBookingId">
                        <input type="hidden" name="status" value="cancelled">
                        
                        <p>Are you sure you want to cancel this booking?</p>
                        <p class="text-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            This will INCREMENT the available units for the property.
                        </p>
                        
                        <div class="mb-3">
                            <label for="cancelReason" class="form-label">Cancellation Reason (Optional)</label>
                            <textarea class="form-control" id="cancelReason" name="reason" rows="3" 
                                      placeholder="Enter reason for cancellation..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Cancel Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle cancel modal
        document.getElementById('cancelModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var bookingId = button.getAttribute('data-booking-id');
            document.getElementById('cancelBookingId').value = bookingId;
        });
    </script>
</body>
</html> 