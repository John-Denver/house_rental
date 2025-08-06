<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['booking_id'])) {
    $booking_id = intval($_POST['booking_id']);
    $action = $_POST['action'];
    
    // Verify the booking belongs to this landlord
    $stmt = $conn->prepare("SELECT rb.* FROM rental_bookings rb 
                           JOIN houses h ON rb.house_id = h.id 
                           WHERE rb.id = ? AND h.landlord_id = ?");
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if ($booking) {
        $new_status = '';
        $message = '';
        
        switch ($action) {
            case 'approve':
                $new_status = 'confirmed';
                $message = 'Booking approved successfully!';
                break;
            case 'reject':
                $new_status = 'cancelled';
                $message = 'Booking rejected successfully!';
                break;
            case 'complete':
                $new_status = 'completed';
                $message = 'Booking marked as completed!';
                break;
        }
        
        if ($new_status) {
            $stmt = $conn->prepare("UPDATE rental_bookings SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $new_status, $booking_id);
            if ($stmt->execute()) {
                $success_message = $message;
            } else {
                $error_message = "Failed to update booking status.";
            }
        }
    } else {
        $error_message = "Booking not found or you don't have permission to modify it.";
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build the query with filters
$where_conditions = ["h.landlord_id = ?"];
$params = [$_SESSION['user_id']];
$param_types = 'i';

if ($status_filter) {
    $where_conditions[] = "rb.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($search) {
    $where_conditions[] = "(u.name LIKE ? OR h.house_no LIKE ? OR h.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = implode(' AND ', $where_conditions);

// Get landlord's rental bookings with enhanced data
$query = "SELECT rb.*, h.house_no, h.description, h.price, h.location,
          u.name as tenant_name, u.username as tenant_email, u.phone_number as tenant_phone,
          TIMESTAMPDIFF(MONTH, rb.start_date, rb.end_date) as rental_months,
          (SELECT COUNT(*) FROM booking_payments bp WHERE bp.booking_id = rb.id) as payment_count,
          (SELECT SUM(amount) FROM booking_payments bp WHERE bp.booking_id = rb.id) as total_paid
          FROM rental_bookings rb 
          LEFT JOIN houses h ON rb.house_id = h.id 
          LEFT JOIN users u ON rb.user_id = u.id 
          WHERE $where_clause
          ORDER BY rb.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get booking statistics
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_bookings,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings
    FROM rental_bookings rb 
    JOIN houses h ON rb.house_id = h.id 
    WHERE h.landlord_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .nav-link {
            color: #495057;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin: 0.25rem 0;
        }
        .nav-link:hover, .nav-link.active {
            background-color: #e9ecef;
            color: #0d6efd;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            border-radius: 0.5rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stats-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stats-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .booking-row:hover {
            background-color: #f8f9fa;
        }
        .avatar-sm {
            width: 32px;
            height: 32px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include('./includes/header.php'); ?>

    <div class="page-wrapper">
        <!-- Sidebar -->
        <?php include('./includes/sidebar.php'); ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid">
                <div class="page-content" style="margin-top: 80px;">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">
                            <i class="fas fa-book me-2 text-primary"></i>
                            Manage Bookings
                        </h1>
                    </div>

                <!-- Success/Error Messages -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stats-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-book fa-2x mb-2"></i>
                                <h4 class="mb-0"><?php echo $stats['total_bookings']; ?></h4>
                                <small>Total Bookings</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stats-card warning h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h4 class="mb-0"><?php echo $stats['pending_bookings']; ?></h4>
                                <small>Pending</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stats-card success h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h4 class="mb-0"><?php echo $stats['confirmed_bookings']; ?></h4>
                                <small>Confirmed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stats-card info h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-flag-checkered fa-2x mb-2"></i>
                                <h4 class="mb-0"><?php echo $stats['completed_bookings']; ?></h4>
                                <small>Completed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-danger text-white h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-times-circle fa-2x mb-2"></i>
                                <h4 class="mb-0"><?php echo $stats['cancelled_bookings']; ?></h4>
                                <small>Cancelled</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search by tenant, property...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i> Filter
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <a href="bookings.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Rental Bookings (<?php echo count($bookings); ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($bookings)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No bookings found</h5>
                                <p class="text-muted">There are no rental bookings matching your criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tenant</th>
                                            <th>Property</th>
                                            <th>Rental Period</th>
                                            <th>Monthly Rent</th>
                                            <th>Payment Status</th>
                                            <th>Booking Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): ?>
                                        <tr class="booking-row">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($booking['tenant_name']); ?></div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-envelope me-1"></i>
                                                            <?php echo htmlspecialchars($booking['tenant_email']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($booking['house_no']); ?></div>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($booking['location']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo $booking['rental_months']; ?> months</div>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($booking['start_date'])); ?> - 
                                                    <?php echo date('M d, Y', strtotime($booking['end_date'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-success">KSh <?php echo number_format($booking['price'], 2); ?></div>
                                                <small class="text-muted">per month</small>
                                            </td>
                                            <td>
                                                <?php 
                                                $paymentStatus = $booking['payment_status'] ?? 'pending';
                                                $paymentColor = $paymentStatus === 'paid' ? 'success' : ($paymentStatus === 'partial' ? 'warning' : 'danger');
                                                $paymentIcon = $paymentStatus === 'paid' ? 'check-circle' : ($paymentStatus === 'partial' ? 'clock' : 'times-circle');
                                                ?>
                                                <span class="badge bg-<?php echo $paymentColor; ?>">
                                                    <i class="fas fa-<?php echo $paymentIcon; ?> me-1"></i>
                                                    <?php echo ucfirst($paymentStatus); ?>
                                                </span>
                                                <?php if ($booking['total_paid']): ?>
                                                    <div class="small text-muted">
                                                        Paid: KSh <?php echo number_format($booking['total_paid'], 2); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $statusColor = $booking['status'] === 'confirmed' ? 'success' : 
                                                             ($booking['status'] === 'pending' ? 'warning' : 
                                                             ($booking['status'] === 'completed' ? 'info' : 'danger'));
                                                ?>
                                                <span class="badge bg-<?php echo $statusColor; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#bookingModal<?php echo $booking['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if ($booking['status'] === 'pending'): ?>
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to approve this booking?')">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to reject this booking?')">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Mark this booking as completed?')">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <button type="submit" class="btn btn-sm btn-outline-info">
                                                                <i class="fas fa-flag-checkered"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Booking Details Modal -->
                                        <div class="modal fade" id="bookingModal<?php echo $booking['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-book me-2"></i>
                                                            Booking Details
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6><i class="fas fa-user me-2"></i>Tenant Information</h6>
                                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['tenant_name']); ?></p>
                                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['tenant_email']); ?></p>
                                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['tenant_phone']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6><i class="fas fa-home me-2"></i>Property Information</h6>
                                                                <p><strong>Property:</strong> <?php echo htmlspecialchars($booking['house_no']); ?></p>
                                                                <p><strong>Location:</strong> <?php echo htmlspecialchars($booking['location']); ?></p>
                                                                <p><strong>Description:</strong> <?php echo htmlspecialchars($booking['description']); ?></p>
                                                            </div>
                                                        </div>
                                                        <hr>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6><i class="fas fa-calendar me-2"></i>Rental Period</h6>
                                                                <p><strong>Start Date:</strong> <?php echo date('F d, Y', strtotime($booking['start_date'])); ?></p>
                                                                <p><strong>End Date:</strong> <?php echo date('F d, Y', strtotime($booking['end_date'])); ?></p>
                                                                <p><strong>Duration:</strong> <?php echo $booking['rental_months']; ?> months</p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6><i class="fas fa-money-bill me-2"></i>Financial Details</h6>
                                                                <p><strong>Monthly Rent:</strong> KSh <?php echo number_format($booking['price'], 2); ?></p>
                                                                <p><strong>Total Rent:</strong> KSh <?php echo number_format($booking['price'] * $booking['rental_months'], 2); ?></p>
                                                                <p><strong>Security Deposit:</strong> KSh <?php echo number_format($booking['security_deposit'] ?? 0, 2); ?></p>
                                                            </div>
                                                        </div>
                                                        <hr>
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <h6><i class="fas fa-info-circle me-2"></i>Status Information</h6>
                                                                <p><strong>Booking Status:</strong> 
                                                                    <span class="badge bg-<?php echo $statusColor; ?>">
                                                                        <?php echo ucfirst($booking['status']); ?>
                                                                    </span>
                                                                </p>
                                                                <p><strong>Payment Status:</strong> 
                                                                    <span class="badge bg-<?php echo $paymentColor; ?>">
                                                                        <?php echo ucfirst($paymentStatus); ?>
                                                                    </span>
                                                                </p>
                                                                <p><strong>Created:</strong> <?php echo date('F d, Y \a\t g:i A', strtotime($booking['created_at'])); ?></p>
                                                                <?php if ($booking['updated_at']): ?>
                                                                    <p><strong>Last Updated:</strong> <?php echo date('F d, Y \a\t g:i A', strtotime($booking['updated_at'])); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if ($booking['status'] === 'pending'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit" class="btn btn-success">
                                                                    <i class="fas fa-check me-1"></i> Approve
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html> 