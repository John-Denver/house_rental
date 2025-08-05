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

// Handle test actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $bookingId = $_POST['booking_id'] ?? null;
        $status = $_POST['status'] ?? null;
        
        if (!$bookingId || !$status) {
            throw new Exception('Missing required parameters');
        }
        
        // Get current property units before update
        $stmt = $conn->prepare("
            SELECT h.available_units, h.total_units, h.house_no, b.status as current_status
            FROM rental_bookings b
            JOIN houses h ON b.house_id = h.id
            WHERE b.id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $propertyInfo = $stmt->get_result()->fetch_assoc();
        
        if (!$propertyInfo) {
            throw new Exception('Booking not found');
        }
        
        $oldUnits = $propertyInfo['available_units'];
        $oldStatus = $propertyInfo['current_status'];
        
        // Update booking status
        $bookingController->updateBookingStatus($bookingId, $status, 'Test automation', $_SESSION['user_id']);
        
        // Get updated property units
        $stmt = $conn->prepare("
            SELECT h.available_units, h.total_units
            FROM rental_bookings b
            JOIN houses h ON b.house_id = h.id
            WHERE b.id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $updatedProperty = $stmt->get_result()->fetch_assoc();
        
        $newUnits = $updatedProperty['available_units'];
        $unitChange = $newUnits - $oldUnits;
        
        $_SESSION['success'] = "Booking #$bookingId status updated from '$oldStatus' to '$status'. " .
                              "Property units changed from $oldUnits to $newUnits (change: $unitChange)";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get bookings for testing
$bookings = [];
$stmt = $conn->prepare("
    SELECT 
        b.*, 
        h.house_no, 
        h.available_units,
        h.total_units,
        u.name as user_name
    FROM rental_bookings b
    JOIN houses h ON b.house_id = h.id
    JOIN users u ON b.user_id = u.id
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Unit Automation - Admin Panel</title>
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
                    <h1 class="h2">Test Unit Automation</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="manage_bookings.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Bookings
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
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Bookings for Testing</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bookings)): ?>
                            <p class="text-muted">No bookings found for testing.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Property</th>
                                            <th>Tenant</th>
                                            <th>Current Status</th>
                                            <th>Available Units</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): ?>
                                            <tr>
                                                <td>#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($booking['house_no']); ?></strong><br>
                                                    <small class="text-muted"><?php echo $booking['available_units']; ?>/<?php echo $booking['total_units']; ?> units</small>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
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
                                                    <span class="badge bg-<?php echo $booking['available_units'] > 0 ? 'success' : 'danger'; ?>">
                                                        <?php echo $booking['available_units']; ?> available
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($booking['status'] === 'pending'): ?>
                                                            <form method="post" class="d-inline" 
                                                                  onsubmit="return confirm('Confirm this booking? This will DECREMENT available units.');">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                <input type="hidden" name="status" value="confirmed">
                                                                <button type="submit" class="btn btn-success btn-sm">
                                                                    <i class="fas fa-check"></i> Confirm
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($booking['status'] === 'confirmed'): ?>
                                                            <form method="post" class="d-inline" 
                                                                  onsubmit="return confirm('Cancel this booking? This will INCREMENT available units.');">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                <input type="hidden" name="status" value="cancelled">
                                                                <button type="submit" class="btn btn-danger btn-sm">
                                                                    <i class="fas fa-times"></i> Cancel
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($booking['status'] === 'cancelled'): ?>
                                                            <form method="post" class="d-inline" 
                                                                  onsubmit="return confirm('Re-confirm this booking? This will DECREMENT available units.');">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                <input type="hidden" name="status" value="confirmed">
                                                                <button type="submit" class="btn btn-warning btn-sm">
                                                                    <i class="fas fa-redo"></i> Re-confirm
                                                                </button>
                                                            </form>
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
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">How Unit Automation Works</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-check-circle text-success"></i> When Booking is Confirmed:</h6>
                                <ul>
                                    <li>Available units are <strong>decremented</strong> by 1</li>
                                    <li>Property becomes less available for new bookings</li>
                                    <li>System prevents units from going below 0</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-times-circle text-danger"></i> When Booking is Cancelled:</h6>
                                <ul>
                                    <li>Available units are <strong>incremented</strong> by 1</li>
                                    <li>Property becomes more available for new bookings</li>
                                    <li>System prevents units from exceeding total units</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <strong>Note:</strong> This automation only works when booking status changes through the admin interface or API. 
                            Direct database updates will not trigger unit changes.
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 