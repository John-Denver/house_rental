<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Get landlord's rental bookings
$stmt = $conn->prepare("SELECT rb.*, h.house_no, h.description, u.name as tenant_name,
                       TIMESTAMPDIFF(MONTH, rb.start_date, rb.end_date) as rental_months
                       FROM rental_bookings rb 
                       LEFT JOIN houses h ON rb.house_id = h.id 
                       LEFT JOIN users u ON rb.user_id = u.id 
                       WHERE h.landlord_id = ?
                       ORDER BY rb.created_at DESC");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include('./includes/header.php'); ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="properties.php">
                                <i class="fas fa-home"></i> Properties
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="bookings.php">
                                <i class="fas fa-book"></i> Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tenants.php">
                                <i class="fas fa-users"></i> Tenants
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Bookings</h1>
                </div>

                <!-- Bookings Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Property</th>
                                <th>Tenant</th>
                                <th>Property</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Rental Period (Months)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['tenant_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($booking['house_no']); ?><br>
                                    <small><?php echo htmlspecialchars($booking['description']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($booking['tenant_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($booking['house_no']); ?><br>
                                    <small><?php echo htmlspecialchars($booking['description']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></td>
                                <td><?php echo $booking['rental_months']; ?> months</td>
                                <td>
                                    <span class="badge bg-<?php echo $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view-booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="approve-booking.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-success" 
                                       onclick="return confirm('Are you sure you want to approve this booking?')">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="reject-booking.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to reject this booking?')">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
