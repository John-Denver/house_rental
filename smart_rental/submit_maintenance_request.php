<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_customer();

$success_message = '';
$error_message = '';

// Get tenant's active bookings
$stmt = $conn->prepare("
    SELECT rb.id, rb.house_id, h.house_no, h.location, h.description
    FROM rental_bookings rb
    JOIN houses h ON rb.house_id = h.id
    WHERE rb.user_id = ? AND rb.status IN ('confirmed', 'active')
    ORDER BY rb.created_at DESC
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$active_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $urgency = $_POST['urgency'];
        $booking_id = $_POST['booking_id'];
        
        // Validation
        if (empty($title)) {
            throw new Exception('Title is required');
        }
        if (empty($description)) {
            throw new Exception('Description is required');
        }
        if (!in_array($urgency, ['Low', 'Medium', 'High'])) {
            throw new Exception('Invalid urgency level');
        }
        
        // Get booking details
        $stmt = $conn->prepare("
            SELECT rb.house_id, rb.user_id 
            FROM rental_bookings rb 
            WHERE rb.id = ? AND rb.user_id = ?
        ");
        $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        
        if (!$booking) {
            throw new Exception('Invalid booking selected');
        }
        
        // Handle file upload
        $photo_url = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/maintenance/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Only JPG, PNG, and GIF files are allowed');
            }
            
            if ($_FILES['photo']['size'] > 5 * 1024 * 1024) { // 5MB limit
                throw new Exception('File size must be less than 5MB');
            }
            
            $filename = time() . '_' . uniqid() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $filepath)) {
                $photo_url = $filepath;
            } else {
                throw new Exception('Failed to upload file');
            }
        }
        
        // Insert maintenance request
        $stmt = $conn->prepare("
            INSERT INTO maintenance_requests 
            (tenant_id, property_id, booking_id, title, description, photo_url, urgency, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->bind_param('iiissss', 
            $_SESSION['user_id'], 
            $booking['house_id'], 
            $booking_id, 
            $title, 
            $description, 
            $photo_url, 
            $urgency
        );
        
        if ($stmt->execute()) {
            $success_message = 'Maintenance request submitted successfully! We will review it and get back to you soon.';
        } else {
            throw new Exception('Failed to submit request');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

$page_title = "Submit Maintenance Request";
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-tools me-2"></i>
                        Submit Maintenance Request
                    </h4>
                </div>
                <div class="card-body">
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
                    
                    <?php if (empty($active_bookings)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You don't have any active bookings. You can only submit maintenance requests for properties you are currently renting.
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="booking_id" class="form-label">
                                            <i class="fas fa-home me-1"></i> Select Property
                                        </label>
                                        <select class="form-select" id="booking_id" name="booking_id" required>
                                            <option value="">Choose a property...</option>
                                            <?php foreach ($active_bookings as $booking): ?>
                                                <option value="<?php echo $booking['id']; ?>">
                                                    <?php echo htmlspecialchars($booking['house_no']); ?> - 
                                                    <?php echo htmlspecialchars($booking['location']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="urgency" class="form-label">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Urgency Level
                                        </label>
                                        <select class="form-select" id="urgency" name="urgency" required>
                                            <option value="">Select urgency...</option>
                                            <option value="Low">Low - Minor issue, not urgent</option>
                                            <option value="Medium">Medium - Moderate issue, needs attention</option>
                                            <option value="High">High - Critical issue, immediate attention needed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">
                                    <i class="fas fa-heading me-1"></i> Issue Title
                                </label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       placeholder="e.g., Leaking sink, Broken window lock" required>
                                <div class="form-text">Brief description of the issue</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    <i class="fas fa-align-left me-1"></i> Detailed Description
                                </label>
                                <textarea class="form-control" id="description" name="description" rows="5" 
                                          placeholder="Please provide a detailed description of the issue, including when it started, any specific details, and how it affects your daily routine..." required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="photo" class="form-label">
                                    <i class="fas fa-camera me-1"></i> Photo (Optional)
                                </label>
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                <div class="form-text">Upload a photo of the issue if possible. Max size: 5MB</div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="my_maintenance_requests.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-list me-1"></i> View My Requests
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> Submit Request
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preview image before upload
    const photoInput = document.getElementById('photo');
    photoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                // You can add image preview here if needed
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?> 