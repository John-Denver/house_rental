<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

$success_message = '';
$error_message = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $request_id = $_POST['request_id'];
        $new_status = $_POST['new_status'];
        $repair_date = $_POST['repair_date'] ?: null;
        $technician = $_POST['technician'] ?: null;
        $rejection_reason = $_POST['rejection_reason'] ?: null;
        
        // Validate status
        if (!in_array($new_status, ['Pending', 'In Progress', 'Completed', 'Rejected'])) {
            throw new Exception('Invalid status');
        }
        
        // Check if request belongs to landlord's properties
        $stmt = $conn->prepare("
            SELECT mr.id FROM maintenance_requests mr
            JOIN houses h ON mr.property_id = h.id
            WHERE mr.id = ? AND h.landlord_id = ?
        ");
        $stmt->bind_param('ii', $request_id, $_SESSION['user_id']);
        $stmt->execute();
        
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception('Invalid request');
        }
        
        // Update request
        $stmt = $conn->prepare("
            UPDATE maintenance_requests 
            SET status = ?, assigned_repair_date = ?, assigned_technician = ?, 
                rejection_reason = ?, completion_date = ?
            WHERE id = ?
        ");
        
        $completion_date = $new_status === 'Completed' ? date('Y-m-d H:i:s') : null;
        
        $stmt->bind_param('sssssi', 
            $new_status, 
            $repair_date, 
            $technician, 
            $rejection_reason, 
            $completion_date, 
            $request_id
        );
        
        if ($stmt->execute()) {
            $success_message = 'Request status updated successfully!';
        } else {
            throw new Exception('Failed to update request');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$urgency_filter = $_GET['urgency'] ?? '';
$property_filter = $_GET['property'] ?? '';
$sort_by = $_GET['sort'] ?? 'submission_date';
$sort_order = $_GET['order'] ?? 'DESC';

// Build query
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
    WHERE h.landlord_id = ?
";

$params = [$_SESSION['user_id']];
$types = 'i';

if ($status_filter) {
    $query .= " AND mr.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($urgency_filter) {
    $query .= " AND mr.urgency = ?";
    $params[] = $urgency_filter;
    $types .= 's';
}

if ($property_filter) {
    $query .= " AND mr.property_id = ?";
    $params[] = $property_filter;
    $types .= 'i';
}

// Ensure sort_by is a valid column and properly qualified
$allowed_sort_columns = ['submission_date', 'urgency', 'status', 'assigned_repair_date', 'completion_date'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'submission_date';
}
$query .= " ORDER BY mr.$sort_by $sort_order";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
        COUNT(*) as total_requests,
        COUNT(CASE WHEN mr.status = 'Pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN mr.status = 'In Progress' THEN 1 END) as in_progress_count,
        COUNT(CASE WHEN mr.status = 'Completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN mr.status = 'Rejected' THEN 1 END) as rejected_count,
        COUNT(CASE WHEN mr.urgency = 'High' THEN 1 END) as high_priority_count
    FROM maintenance_requests mr
    JOIN houses h ON mr.property_id = h.id
    WHERE h.landlord_id = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Requests - Landlord Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include('./includes/header.php'); ?>

    <div class="page-wrapper">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <?php include('./includes/sidebar.php'); ?>

                <!-- Main Content -->
                <div class="main-content col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="container-fluid">
                        <div class="page-content" style="margin-top: 80px;">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tools me-2 text-primary"></i>
                        Maintenance Requests
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="maintenance_history.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-history me-1"></i> View History
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Requests
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_requests']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['pending_count']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            In Progress
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['in_progress_count']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-spinner fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Completed
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

                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            High Priority
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['high_priority_count']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Statuses</option>
                                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Urgency</label>
                                <select name="urgency" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Urgency Levels</option>
                                    <option value="Low" <?php echo $urgency_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="Medium" <?php echo $urgency_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="High" <?php echo $urgency_filter === 'High' ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                            <div class="col-md-3">
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
                            <div class="col-md-3">
                                <label class="form-label">Sort by</label>
                                <select name="sort" class="form-select" onchange="this.form.submit()">
                                    <option value="submission_date" <?php echo $sort_by === 'submission_date' ? 'selected' : ''; ?>>Submission Date</option>
                                    <option value="urgency" <?php echo $sort_by === 'urgency' ? 'selected' : ''; ?>>Urgency</option>
                                    <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Requests List -->
                <?php if (empty($requests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No maintenance requests found</h5>
                        <p class="text-muted">No requests match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Request</th>
                                    <th>Tenant</th>
                                    <th>Property</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
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
                                        <span class="badge bg-<?php 
                                            echo $request['urgency'] === 'High' ? 'danger' : 
                                                ($request['urgency'] === 'Medium' ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo $request['urgency']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $request['status'] === 'Completed' ? 'success' : 
                                                ($request['status'] === 'In Progress' ? 'warning' : 
                                                ($request['status'] === 'Rejected' ? 'danger' : 'secondary')); 
                                        ?>">
                                            <?php echo $request['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('M d, Y', strtotime($request['submission_date'])); ?></small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewRequestDetails(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-eye me-1"></i> View
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" 
                                                onclick="updateRequestStatus(<?php echo $request['id']; ?>, '<?php echo $request['status']; ?>')">
                                            <i class="fas fa-edit me-1"></i> Update
                                        </button>
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

    <!-- View Request Details Modal -->
    <div class="modal fade" id="viewRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Maintenance Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Maintenance Request Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="request_id" id="updateRequestId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_status" class="form-label">New Status</label>
                                    <select name="new_status" id="new_status" class="form-select" required>
                                        <option value="Pending">Pending</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Rejected">Rejected</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="repair_date" class="form-label">Repair Date</label>
                                    <input type="datetime-local" name="repair_date" id="repair_date" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="technician" class="form-label">Assigned Technician</label>
                                    <input type="text" name="technician" id="technician" class="form-control" 
                                           placeholder="Technician name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rejection_reason" class="form-label">Rejection Reason</label>
                                    <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="2" 
                                              placeholder="Reason for rejection (if applicable)"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewRequestDetails(requestId) {
            // Show loading state
            const modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
            const contentDiv = document.getElementById('requestDetailsContent');
            contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Loading...</p></div>';
            modal.show();
            
            // Fetch request details
            fetch(`get_maintenance_details.php?id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const request = data.data;
                        contentDiv.innerHTML = `
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="fw-bold mb-3">Request Information</h6>
                                    <div class="mb-3">
                                        <strong>Title:</strong> ${request.title}
                                    </div>
                                    <div class="mb-3">
                                        <strong>Description:</strong><br>
                                        <div class="mt-2 p-3 bg-light rounded">${request.description}</div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Status:</strong><br>
                                            <span class="${request.status_class}">${request.status}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Urgency:</strong><br>
                                            <span class="${request.urgency_class}">${request.urgency}</span>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Submitted:</strong><br>
                                            <small class="text-muted">${request.submission_date_formatted}</small>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Last Updated:</strong><br>
                                            <small class="text-muted">${request.updated_at_formatted}</small>
                                        </div>
                                    </div>
                                    ${request.assigned_repair_date ? `
                                    <div class="mb-3">
                                        <strong>Scheduled Repair Date:</strong><br>
                                        <small class="text-muted">${request.assigned_repair_date_formatted}</small>
                                    </div>
                                    ` : ''}
                                    ${request.assigned_technician ? `
                                    <div class="mb-3">
                                        <strong>Assigned Technician:</strong><br>
                                        <span class="text-primary">${request.assigned_technician}</span>
                                    </div>
                                    ` : ''}
                                    ${request.completion_date ? `
                                    <div class="mb-3">
                                        <strong>Completed:</strong><br>
                                        <small class="text-muted">${request.completion_date_formatted}</small>
                                    </div>
                                    ` : ''}
                                    ${request.rejection_reason ? `
                                    <div class="mb-3">
                                        <strong>Rejection Reason:</strong><br>
                                        <div class="mt-2 p-3 bg-danger bg-opacity-10 rounded text-danger">${request.rejection_reason}</div>
                                    </div>
                                    ` : ''}
                                    ${request.rating ? `
                                    <div class="mb-3">
                                        <strong>Rating:</strong><br>
                                        <div class="text-warning">
                                            ${'★'.repeat(request.rating)}${'☆'.repeat(5-request.rating)}
                                        </div>
                                    </div>
                                    ` : ''}
                                    ${request.feedback ? `
                                    <div class="mb-3">
                                        <strong>Feedback:</strong><br>
                                        <div class="mt-2 p-3 bg-light rounded">${request.feedback}</div>
                                    </div>
                                    ` : ''}
                                </div>
                                <div class="col-md-4">
                                    <h6 class="fw-bold mb-3">Property Information</h6>
                                    <div class="mb-3">
                                        <strong>Property:</strong><br>
                                        <span class="text-primary">${request.house_no}</span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Location:</strong><br>
                                        <small class="text-muted">${request.property_location}</small>
                                    </div>
                                    
                                    <h6 class="fw-bold mb-3 mt-4">Tenant Information</h6>
                                    <div class="mb-3">
                                        <strong>Name:</strong><br>
                                        <span class="text-primary">${request.tenant_name}</span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Email:</strong><br>
                                        <small class="text-muted">${request.tenant_email}</small>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Phone:</strong><br>
                                        <small class="text-muted">${request.tenant_phone}</small>
                                    </div>
                                </div>
                            </div>
                            ${request.photo_url ? `
                            <div class="mt-4">
                                <h6 class="fw-bold mb-3">Before Photos</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <img src="../uploads/${request.photo_url}" class="img-fluid rounded" alt="Before Photo">
                                        <small class="text-muted">Before Photo</small>
                                    </div>
                                    ${request.after_photo_url ? `
                                    <div class="col-md-6">
                                        <img src="../uploads/${request.after_photo_url}" class="img-fluid rounded" alt="After Photo">
                                        <small class="text-muted">After Photo</small>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                            ` : ''}
                        `;
                    } else {
                        contentDiv.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error: ${data.error}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading request details. Please try again.
                        </div>
                    `;
                    console.error('Error:', error);
                });
        }
        
        function updateRequestStatus(requestId, currentStatus) {
            document.getElementById('updateRequestId').value = requestId;
            document.getElementById('new_status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }
    </script>
</body>
</html> 