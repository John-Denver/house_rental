<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_customer();

$success_message = '';
$error_message = '';

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_request'])) {
    try {
        $request_id = $_POST['request_id'];
        $rating = $_POST['rating'];
        $feedback = trim($_POST['feedback']);
        
        // Validate rating
        if (!in_array($rating, [1, 2, 3, 4, 5])) {
            throw new Exception('Invalid rating');
        }
        
        // Check if request belongs to user and is completed
        $stmt = $conn->prepare("
            SELECT id FROM maintenance_requests 
            WHERE id = ? AND tenant_id = ? AND status = 'Completed'
        ");
        $stmt->bind_param('ii', $request_id, $_SESSION['user_id']);
        $stmt->execute();
        
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception('Invalid request or request not completed');
        }
        
        // Update rating
        $stmt = $conn->prepare("
            UPDATE maintenance_requests 
            SET rating = ?, feedback = ? 
            WHERE id = ?
        ");
        $stmt->bind_param('isi', $rating, $feedback, $request_id);
        
        if ($stmt->execute()) {
            $success_message = 'Rating submitted successfully!';
        } else {
            throw new Exception('Failed to submit rating');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'submission_date';
$sort_order = $_GET['order'] ?? 'DESC';

// Build query
$query = "
    SELECT mr.*, h.house_no, h.location, h.description as property_description
    FROM maintenance_requests mr
    JOIN houses h ON mr.property_id = h.id
    WHERE mr.tenant_id = ?
";

$params = [$_SESSION['user_id']];
$types = 'i';

if ($status_filter) {
    $query .= " AND mr.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query .= " ORDER BY mr.$sort_by $sort_order";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "My Maintenance Requests";
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-tools me-2 text-primary"></i>
                    My Maintenance Requests
                </h2>
                <a href="submit_maintenance_request.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> New Request
                </a>
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
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Filter by Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sort by</label>
                            <select name="sort" class="form-select" onchange="this.form.submit()">
                                <option value="submission_date" <?php echo $sort_by === 'submission_date' ? 'selected' : ''; ?>>Submission Date</option>
                                <option value="urgency" <?php echo $sort_by === 'urgency' ? 'selected' : ''; ?>>Urgency</option>
                                <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Order</label>
                            <select name="order" class="form-select" onchange="this.form.submit()">
                                <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (empty($requests)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No maintenance requests found</h5>
                    <p class="text-muted">You haven't submitted any maintenance requests yet.</p>
                    <a href="submit_maintenance_request.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Submit Your First Request
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($requests as $request): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($request['title']); ?></h6>
                                    <span class="badge bg-<?php 
                                        echo $request['status'] === 'Completed' ? 'success' : 
                                            ($request['status'] === 'In Progress' ? 'warning' : 
                                            ($request['status'] === 'Rejected' ? 'danger' : 'secondary')); 
                                    ?>">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-home me-1"></i>
                                            <?php echo htmlspecialchars($request['house_no']); ?> - 
                                            <?php echo htmlspecialchars($request['location']); ?>
                                        </small>
                                    </div>
                                    
                                    <p class="card-text"><?php echo htmlspecialchars(substr($request['description'], 0, 100)); ?>...</p>
                                    
                                    <div class="mb-2">
                                        <span class="badge bg-<?php 
                                            echo $request['urgency'] === 'High' ? 'danger' : 
                                                ($request['urgency'] === 'Medium' ? 'warning' : 'info'); 
                                        ?>">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <?php echo $request['urgency']; ?> Priority
                                        </span>
                                    </div>
                                    
                                    <?php if ($request['photo_url']): ?>
                                        <div class="mb-2">
                                            <img src="<?php echo htmlspecialchars($request['photo_url']); ?>" 
                                                 class="img-fluid rounded" style="max-height: 100px;" 
                                                 alt="Issue photo">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Submitted: <?php echo date('M d, Y', strtotime($request['submission_date'])); ?>
                                        </small>
                                    </div>
                                    
                                    <?php if ($request['assigned_repair_date']): ?>
                                        <div class="mb-2">
                                            <small class="text-info">
                                                <i class="fas fa-calendar-check me-1"></i>
                                                Repair Date: <?php echo date('M d, Y', strtotime($request['assigned_repair_date'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($request['status'] === 'Completed' && !$request['rating']): ?>
                                        <!-- Rating Form -->
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="showRatingModal(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-star me-1"></i> Rate This Request
                                            </button>
                                        </div>
                                    <?php elseif ($request['rating']): ?>
                                        <!-- Show Rating -->
                                        <div class="mt-2">
                                            <div class="text-warning">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?php echo $i <= $request['rating'] ? '' : '-o'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <?php if ($request['feedback']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($request['feedback']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <button class="btn btn-sm btn-outline-secondary" 
                                            onclick="viewRequestDetails(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Rating Modal -->
<div class="modal fade" id="ratingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate This Maintenance Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="rate_request" value="1">
                    <input type="hidden" name="request_id" id="ratingRequestId">
                    
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required>
                                <label for="star<?php echo $i; ?>">
                                    <i class="fas fa-star"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="feedback" class="form-label">Feedback (Optional)</label>
                        <textarea class="form-control" name="feedback" rows="3" 
                                  placeholder="Share your experience with this maintenance request..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Rating</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.rating-stars {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}

.rating-stars input {
    display: none;
}

.rating-stars label {
    cursor: pointer;
    font-size: 1.5rem;
    color: #ddd;
    margin: 0 2px;
}

.rating-stars input:checked ~ label,
.rating-stars label:hover,
.rating-stars label:hover ~ label {
    color: #ffc107;
}
</style>

<script>
function showRatingModal(requestId) {
    document.getElementById('ratingRequestId').value = requestId;
    new bootstrap.Modal(document.getElementById('ratingModal')).show();
}

function viewRequestDetails(requestId) {
    // You can implement a detailed view modal or redirect to a details page
    alert('Detailed view functionality can be implemented here');
}
</script>

<?php include 'includes/footer.php'; ?> 