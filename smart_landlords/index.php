<?php
require_once '../config/db.php';
require_once '../config/auth.php';

require_landlord();
$landlord_id = $_SESSION['user_id'];

// Get landlord's properties
$properties_stmt = $conn->prepare("
    SELECT h.*, c.name as category_name 
    FROM houses h 
    LEFT JOIN categories c ON h.category_id = c.id 
    WHERE h.landlord_id = ?
");
$properties_stmt->bind_param('i', $landlord_id);
$properties_stmt->execute();
$properties = $properties_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total properties
$total_stmt = $conn->prepare("
    SELECT COUNT(*) as total_properties 
    FROM houses 
    WHERE landlord_id = ?
");
$total_stmt->bind_param('i', $landlord_id);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_properties = $total_result->fetch_assoc()['total_properties'] ?? 0;

// Get rental statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_rentals,
        COUNT(CASE WHEN rb.status = 'confirmed' THEN 1 END) as active_rentals,
        COUNT(CASE WHEN rb.status = 'pending' THEN 1 END) as pending_rentals,
        COUNT(CASE WHEN rb.status = 'cancelled' THEN 1 END) as cancelled_rentals,
        COUNT(CASE WHEN rb.status = 'expired' THEN 1 END) as expired_rentals
    FROM rental_bookings rb
    JOIN houses h ON rb.house_id = h.id
    WHERE h.landlord_id = ?
");
$stats_stmt->bind_param('i', $landlord_id);
$stats_stmt->execute();
$rental_stats = $stats_stmt->get_result()->fetch_assoc() ?? [];
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
<?php include './includes/header.php'; ?>

<div class="page-wrapper d-flex">
    <!-- Sidebar -->
    <nav id="sidebar" class="sidebar bg-light">
        <div class="position-sticky">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link active" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="properties.php"><i class="fas fa-home"></i> My Properties</a></li>
                <li class="nav-item"><a class="nav-link" href="bookings.php"><i class="fas fa-book"></i> Bookings</a></li>
                <li class="nav-item"><a class="nav-link" href="tenants.php"><i class="fas fa-users"></i> Tenants</a></li>
                <li class="nav-item"><a class="nav-link" href="payments.php"><i class="fas fa-money-bill"></i> Payments</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content flex-fill p-4">
        <div class="container-fluid">
            <div class="border-bottom pb-3 mb-4">
                <h1 class="h2">Dashboard</h1>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5>Total Properties</h5>
                            <p class="display-6"><?php echo $total_properties; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5>Active Rentals</h5>
                            <p class="display-6"><?php echo $rental_stats['active_rentals'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h5>Pending Rentals</h5>
                            <p class="display-6"><?php echo $rental_stats['pending_rentals'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h5>Expired Rentals</h5>
                            <p class="display-6"><?php echo $rental_stats['expired_rentals'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Properties -->
            <h2>Recent Properties</h2>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Property</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Rent</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($properties)): ?>
                            <?php foreach ($properties as $property): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($property['house_no']); ?></td>
                                    <td><?php echo htmlspecialchars($property['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($property['location']); ?></td>
                                    <td><?php echo htmlspecialchars($property['price']); ?></td>

                                    <td>
                                        <span class="badge bg-<?php echo $property['status'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $property['status'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $property['id']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                    <td>
                                    <!-- Delete Form -->
                                        <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this property?');">
                                            <input type="hidden" name="id" value="<?= $property['id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal for this Property -->
                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $property['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $property['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content bg-dark text-white">
                                            <form action="" method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editModalLabel<?= $property['id'] ?>">
                                                        Edit Property - <?= htmlspecialchars($property['house_no']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?= $property['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Location</label>
                                                        <input type="text" class="form-control" name="location" value="<?= htmlspecialchars($property['location']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Category</label>
                                                        <input type="text" class="form-control" name="category" value="<?= htmlspecialchars($property['category_name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Rent (Ksh)</label>
                                                        <input type="number" class="form-control" name="price" value="<?= htmlspecialchars($property['price']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="1" <?= $property['status'] ? 'selected' : '' ?>>Active</option>
                                                            <option value="0" <?= !$property['status'] ? 'selected' : '' ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" name="update" class="btn btn-success">Save</button>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted">No properties found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
