<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Get landlord's properties
$stmt = $conn->prepare("SELECT h.*, c.name as category_name 
                       FROM houses h 
                       LEFT JOIN categories c ON h.category_id = c.id 
                       WHERE h.landlord_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total properties and rental statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_properties 
                       FROM houses 
                       WHERE landlord_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$total_properties = $stmt->get_result()->fetch_assoc()['total_properties'];

// Get rental statistics
$stmt = $conn->prepare("SELECT 
                           COUNT(*) as total_rentals,
                           COUNT(CASE WHEN rb.status = 'confirmed' THEN 1 END) as active_rentals,
                           COUNT(CASE WHEN rb.status = 'pending' THEN 1 END) as pending_rentals,
                           COUNT(CASE WHEN rb.status = 'cancelled' THEN 1 END) as cancelled_rentals,
                           COUNT(CASE WHEN rb.status = 'expired' THEN 1 END) as expired_rentals
                       FROM rental_bookings rb
                       JOIN houses h ON rb.house_id = h.id
                       WHERE h.landlord_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$rental_stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Dashboard - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include('./includes/header.php'); ?>

    <div class="page-wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="position-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
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
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid">
                <div class="page-content">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Dashboard</h1>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="row">
                            <div class="col-md-3 mb-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Properties</h5>
                                        <p class="card-text display-6"><?php echo $total_properties; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Active Rentals</h5>
                                        <p class="card-text display-6"><?php echo $rental_stats['active_rentals']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="card bg-warning text-dark">
                                    <div class="card-body">
                                        <h5 class="card-title">Pending Rentals</h5>
                                        <p class="card-text display-6"><?php echo $rental_stats['pending_rentals']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="card bg-danger text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Expired Rentals</h5>
                                        <p class="card-text display-6"><?php echo $rental_stats['expired_rentals']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Properties -->
                    <h2>Recent Properties</h2>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Property</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($properties as $property): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($property['house_no']); ?></td>
                                    <td><?php echo htmlspecialchars($property['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($property['location']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $property['status'] ? 'success' : 'danger'; ?>">
                                            <?php echo $property['status'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit-property.php?id=<?php echo $property['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
