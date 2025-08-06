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

// Get scheduled viewings count
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_viewings 
    FROM property_viewings pv
    JOIN houses h ON pv.property_id = h.id
    WHERE h.landlord_id = ? AND pv.status != 'cancelled' AND pv.viewing_date >= CURDATE()
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$scheduled_viewings_count = $stmt->get_result()->fetch_assoc()['total_viewings'];

// Get maintenance requests count
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_requests 
    FROM maintenance_requests mr
    JOIN houses h ON mr.property_id = h.id
    WHERE h.landlord_id = ? AND mr.status IN ('Pending', 'In Progress')
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$maintenance_requests_count = $stmt->get_result()->fetch_assoc()['total_requests'];

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
                        <a class="nav-link" href="maintenance_requests.php">
                            <i class="fas fa-tools"></i> Maintenance Requests
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="scheduled_viewings.php">
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
        <div class="main-content">
            <div class="container-fluid">
                <div class="page-content">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Dashboard</h1>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-6 col-xl mb-3 mb-xl-0">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                                    <h5 class="card-title mb-2">Total Properties</h5>
                                    <p class="card-text display-6 mb-0"><?php echo $total_properties; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl mb-3 mb-xl-0">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                                    <h5 class="card-title mb-2">Active Rentals</h5>
                                    <p class="card-text display-6 mb-0"><?php echo $rental_stats['active_rentals']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl mb-3 mb-xl-0">
                            <a href="bookings.php" class="text-decoration-none">
                                <div class="card bg-warning text-dark h-100 hover-shadow">
                                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                                        <h5 class="card-title mb-2">Pending Rentals</h5>
                                        <p class="card-text display-6 mb-0"><?php echo $rental_stats['pending_rentals']; ?></p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-sm-6 col-xl mb-3 mb-xl-0">
                            <div class="card bg-danger text-white h-100">
                                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                                    <h5 class="card-title mb-2">Expired Rentals</h5>
                                    <p class="card-text display-6 mb-0"><?php echo $rental_stats['expired_rentals']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl mb-3 mb-xl-0">
                            <a href="scheduled_viewings.php" class="text-decoration-none">
                                <div class="card bg-info text-white h-100 hover-shadow">
                                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                                        <h5 class="card-title mb-2">Scheduled Viewings</h5>
                                        <p class="card-text display-6 mb-0"><?php echo $scheduled_viewings_count; ?></p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-sm-6 col-xl">
                            <a href="maintenance_requests.php" class="text-decoration-none">
                                <div class="card bg-secondary text-white h-100 hover-shadow">
                                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                                        <h5 class="card-title mb-2">Maintenance Requests</h5>
                                        <p class="card-text display-6 mb-0"><?php echo $maintenance_requests_count; ?></p>
                                    </div>
                                </div>
                            </a>
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
