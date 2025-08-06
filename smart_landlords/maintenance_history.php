<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Get filter parameters
$property_filter = $_GET['property'] ?? '';
$status_filter = $_GET['status'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');

// Build query for completed requests
$query = "
    SELECT mr.id, mr.tenant_id, mr.property_id, mr.booking_id, mr.title, mr.description, 
           mr.photo_url, mr.urgency, mr.status, mr.submission_date, mr.assigned_repair_date,
           mr.assigned_technician, mr.before_photo_url, mr.after_photo_url, mr.rejection_reason,
           mr.rating, mr.feedback, mr.completion_date, mr.created_at, mr.updated_at,
           h.house_no, h.location, h.description as property_description,
           u.name as tenant_name, u.username as tenant_email, u.phone_number as tenant_phone
    FROM maintenance_requests mr
    JOIN houses h ON mr.property_id = h.id
    JOIN users u ON mr.tenant_id = u.id
    WHERE h.landlord_id = ? AND mr.status IN ('Completed', 'Rejected')
";

$params = [$_SESSION['user_id']];
$types = 'i';

if ($property_filter) {
    $query .= " AND mr.property_id = ?";
    $params[] = $property_filter;
    $types .= 'i';
}

if ($status_filter) {
    $query .= " AND mr.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query .= " AND YEAR(mr.submission_date) = ?";
$params[] = $year_filter;
$types .= 'i';

$query .= " ORDER BY mr.submission_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$completed_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get landlord's properties for filter
$stmt = $conn->prepare("
    SELECT id, house_no, location 
    FROM houses 
    WHERE landlord_id = ? 
    ORDER BY house_no
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_completed,
        COUNT(CASE WHEN mr.status = 'Completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN mr.status = 'Rejected' THEN 1 END) as rejected_count,
        AVG(CASE WHEN mr.status = 'Completed' THEN mr.rating END) as avg_rating
    FROM maintenance_requests mr
    JOIN houses h ON mr.property_id = h.id
    WHERE h.landlord_id = ? AND mr.status IN ('Completed', 'Rejected')
    AND YEAR(mr.submission_date) = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param('ii', $_SESSION['user_id'], $year_filter);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance History - Landlord Dashboard</title>
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
                <div class="page-content">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-history me-2 text-primary"></i>
                        Maintenance History
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="maintenance_requests.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-tools me-1"></i> Active Requests
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Completed Requests
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['completed_count']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Rejected Requests
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['rejected_count']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Average Rating
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['avg_rating'], 1); ?>/5.0
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-star fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Requests
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_completed']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Property</label>
                                <select name="property" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Properties</option>
                                    <?php foreach ($properties as $property): ?>
                                        <option value="<?php echo $property['id']; ?>" 
                                                <?php echo $property_filter == $property['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($property['house_no']); ?> - 
                                            <?php echo htmlspecialchars($property['location']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Statuses</option>
                                    <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year</label>
                                <select name="year" class="form-select" onchange="this.form.submit()">
                                    <?php for ($year = date('Y'); $year >= date('Y') - 5; $year--): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $year_filter == $year ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Completed Requests Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Completed/Rejected Requests (<?php echo $year_filter; ?>)</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($completed_requests)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No completed requests found</h5>
                                <p class="text-muted">No requests match your current filters for <?php echo $year_filter; ?>.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Request</th>
                                            <th>Tenant</th>
                                            <th>Property</th>
                                            <th>Status</th>
                                            <th>Rating</th>
                                            <th>Submitted</th>
                                            <th>Completed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($completed_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($request['title']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($request['description'], 0, 50)); ?>...</small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($request['tenant_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($request['tenant_email']); ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($request['house_no']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($request['location']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $request['status'] === 'Completed' ? 'success' : 'danger'; ?>">
                                                    <?php echo $request['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($request['rating']): ?>
                                                    <div class="text-warning">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?php echo $i <= $request['rating'] ? '' : '-o'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo $request['rating']; ?>/5</small>
                                                <?php else: ?>
                                                    <span class="text-muted">No rating</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($request['submission_date'])); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo $request['completion_date'] ? date('M d, Y', strtotime($request['completion_date'])) : 'N/A'; ?></small>
                                            </td>
                                        </tr>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 