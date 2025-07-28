<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Get all scheduled viewings for properties owned by this landlord
$sql = "SELECT pv.*, h.house_no, h.location, u.name as user_name, 
               u.phone_number as user_phone, h.id as property_id
        FROM property_viewings pv
        JOIN houses h ON pv.property_id = h.id
        LEFT JOIN users u ON pv.user_id = u.id
        WHERE h.landlord_id = ?
        ORDER BY pv.viewing_date DESC, pv.viewing_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$viewings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Viewings - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-confirmed { background-color: #198754; color: #fff; }
        .status-completed { background-color: #6c757d; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
        .viewing-card {
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            border-left: 4px solid #0d6efd;
        }
        .viewing-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .action-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include('./includes/header.php'); ?>

    <div class="page-wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="position-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="properties.php">
                            <i class="fas fa-home"></i> My Properties
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">
                            <i class="fas fa-book"></i> Bookings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tenants.php">
                            <i class="fas fa-users"></i> Tenants
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-money-bill"></i> Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="scheduled_viewings.php">
                            <i class="fas fa-calendar-alt"></i> Scheduled Viewings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content mt-4">
            <div class="container-fluid">
                <div class="page-content">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Scheduled Viewings</h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i> Print Schedule
                            </button>
                        </div>
                    </div>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars(urldecode($_GET['success'])); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-12">
                            <?php if ($viewings->num_rows > 0): ?>
                                <?php while ($viewing = $viewings->fetch_assoc()): 
                                    $viewing_datetime = new DateTime($viewing['viewing_date'] . ' ' . $viewing['viewing_time']);
                                    $now = new DateTime();
                                    $is_past = $viewing_datetime < $now;
                                ?>
                                    <div class="card viewing-card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <h5 class="card-title mb-1">
                                                        <a href="property.php?id=<?php echo $viewing['property_id']; ?>">
                                                            <?php echo htmlspecialchars($viewing['house_no']); ?> - 
                                                            <?php echo htmlspecialchars($viewing['location']); ?>
                                                        </a>
                                                    </h5>
                                                    <p class="text-muted mb-2">
                                                        <i class="far fa-calendar-alt me-2"></i>
                                                        <?php echo date('l, F j, Y', strtotime($viewing['viewing_date'])); ?> at 
                                                        <?php echo date('g:i A', strtotime($viewing['viewing_time'])); ?>
                                                        <?php if ($is_past): ?>
                                                            <span class="badge bg-secondary ms-2">Past</span>
                                                        <?php endif; ?>
                                                    </p>
                                                    
                                                    <div class="mb-2">
                                                        <span class="badge status-badge status-<?php echo $viewing['status']; ?>">
                                                            <?php echo ucfirst($viewing['status']); ?>
                                                        </span>
                                                    </div>

                                                    <?php if ($viewing['user_id']): ?>
                                                        <p class="mb-1">
                                                            <i class="fas fa-user me-2"></i>
                                                            <?php echo htmlspecialchars($viewing['user_name']); ?>
                                                            <?php if (!empty($viewing['user_email'])): ?>
                                                                <a href="mailto:<?php echo htmlspecialchars($viewing['user_email']); ?>" class="ms-2">
                                                                    <i class="fas fa-envelope"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </p>
                                                    <?php else: ?>
                                                        <p class="mb-1">
                                                            <i class="fas fa-user me-2"></i>
                                                            <?php echo htmlspecialchars($viewing['viewer_name']); ?> (Guest)
                                                        </p>
                                                        <p class="mb-1">
                                                            <i class="fas fa-phone me-2"></i>
                                                            <?php echo htmlspecialchars($viewing['contact_number']); ?>
                                                        </p>
                                                    <?php endif; ?>

                                                    <?php if (!empty($viewing['notes'])): ?>
                                                        <div class="mt-2 p-2 bg-light rounded">
                                                            <small class="text-muted">
                                                                <i class="far fa-sticky-note me-1"></i>
                                                                <?php echo nl2br(htmlspecialchars($viewing['notes'])); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="col-md-4 text-end action-buttons">
                                                    <?php if ($viewing['status'] === 'pending' && !$is_past): ?>
                                                        <form action="update_viewing_status.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="viewing_id" value="<?php echo $viewing['id']; ?>">
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button type="submit" class="btn btn-success btn-sm">
                                                                <i class="fas fa-check me-1"></i> Confirm
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($viewing['status'] !== 'cancelled' && !$is_past): ?>
                                                        <button type="button" class="btn btn-warning btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#rescheduleModal"
                                                                data-viewing-id="<?php echo $viewing['id']; ?>">
                                                            <i class="fas fa-calendar-alt me-1"></i> Reschedule
                                                        </button>
                                                        
                                                        <form action="update_viewing_status.php" method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Are you sure you want to cancel this viewing?');">
                                                            <input type="hidden" name="viewing_id" value="<?php echo $viewing['id']; ?>">
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="fas fa-times me-1"></i> Cancel
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($viewing['status'] === 'confirmed' && !$is_past): ?>
                                                        <form action="update_viewing_status.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="viewing_id" value="<?php echo $viewing['id']; ?>">
                                                            <input type="hidden" name="status" value="completed">
                                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                                <i class="fas fa-check-double me-1"></i> Mark as Completed
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="far fa-calendar-times fa-4x text-muted mb-3"></i>
                                    <h4>No scheduled viewings found</h4>
                                    <p class="text-muted">When you have scheduled viewings, they will appear here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1" aria-labelledby="rescheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="reschedule_viewing.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rescheduleModalLabel">Reschedule Viewing</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="viewing_id" id="rescheduleViewingId">
                        
                        <div class="mb-3">
                            <label for="newViewingDate" class="form-label">New Date</label>
                            <input type="date" class="form-control" id="newViewingDate" name="viewing_date" required
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="newViewingTime" class="form-label">New Time</label>
                            <select class="form-select" id="newViewingTime" name="viewing_time" required>
                                <option value="">Select Time</option>
                                <option value="09:00:00">9:00 AM</option>
                                <option value="10:00:00">10:00 AM</option>
                                <option value="11:00:00">11:00 AM</option>
                                <option value="12:00:00">12:00 PM</option>
                                <option value="13:00:00">1:00 PM</option>
                                <option value="14:00:00">2:00 PM</option>
                                <option value="15:00:00">3:00 PM</option>
                                <option value="16:00:00">4:00 PM</option>
                                <option value="17:00:00">5:00 PM</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="rescheduleNotes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="rescheduleNotes" name="notes" 
                                     rows="3" placeholder="Add any notes about the reschedule"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-check me-1"></i> Update Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- <?php include('../includes/footer.php'); ?> -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle reschedule modal
        const rescheduleModal = document.getElementById('rescheduleModal');
        if (rescheduleModal) {
            rescheduleModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const viewingId = button.getAttribute('data-viewing-id');
                const modalInput = rescheduleModal.querySelector('#rescheduleViewingId');
                modalInput.value = viewingId;
            });
        }

        // Auto-close alerts after 5 seconds
        window.setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
    </script>
</body>
</html>
