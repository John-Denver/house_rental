<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Get landlord's tenants with their current rental status
$stmt = $conn->prepare("SELECT DISTINCT u.*, 
                       h.house_no, 
                       h.description,
                       rb.status as rental_status,
                       rb.start_date,
                       rb.end_date
                       FROM users u 
                       LEFT JOIN rental_bookings rb ON u.id = rb.user_id 
                       LEFT JOIN houses h ON rb.house_id = h.id 
                       WHERE h.landlord_id = ?
                       ORDER BY u.name");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$tenants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Debug: Uncomment the line below to see the structure of the first tenant record
// if (!empty($tenants)) { echo '<pre>'; print_r($tenants[0]); echo '</pre>'; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tenants - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include('./includes/header.php'); ?>

    <div class="page-wrapper">
        <!-- Sidebar -->
        <?php include('./includes/sidebar.php'); ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid">
                <div class="page-content" style="margin-top: 80px;">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Tenants</h1>
                </div>

                <!-- Tenants Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Email/Username</th>
                                <th>Phone</th>
                                <th>Current Property</th>
                                <th>Rental Status</th>
<th>Start Date</th>
<th>End Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tenants as $tenant): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tenant['name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($tenant['username'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($tenant['phone_number'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($tenant['house_no']): ?>
                                        <?php echo htmlspecialchars($tenant['house_no']); ?><br>
                                        <small><?php echo htmlspecialchars($tenant['description']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">No current property</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tenant['rental_status']): ?>
                                        <span class="badge bg-<?php echo $tenant['rental_status'] === 'confirmed' ? 'success' : ($tenant['rental_status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($tenant['rental_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No Status</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tenant['start_date']): ?>
                                        <?php echo date('M d, Y', strtotime($tenant['start_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tenant['end_date']): ?>
                                        <?php echo date('M d, Y', strtotime($tenant['end_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view-tenant.php?id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="edit-tenant.php?id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-warning">
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
