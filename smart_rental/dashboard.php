<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login('./login.php');

// Get user's bookings with property information
$sql = "SELECT rb.*, h.house_no, h.location, h.price, h.main_image as image
        FROM rental_bookings rb
        LEFT JOIN houses h ON rb.house_id = h.id
        WHERE rb.user_id = ?
        ORDER BY rb.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h5>
                        <p class="card-text">Manage your bookings and profile here.</p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <ul class="list-unstyled">
                            <li><a href="bookings.php" class="text-decoration-none">View All Bookings</a></li>
                            <li><a href="profile.php" class="text-decoration-none">Edit Profile</a></li>
                            <li><a href="favorites.php" class="text-decoration-none">View Favorites</a></li>
                            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'landlord'): ?>
                            <li><a href="scheduled_viewings.php" class="text-decoration-none">Scheduled Viewings</a></li>
                            <?php endif; ?>
                            <li><a href="settings.php" class="text-decoration-none">Account Settings</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Your Statistics</h5>
                        <div class="stats">
                            <div class="stat-item">
                                <i class="fas fa-home"></i>
                                <span>Properties Booked</span>
                                <strong><?php echo $bookings->num_rows; ?></strong>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-star"></i>
                                <span>Favorites</span>
                                <strong>
                                    <?php
                                    $sql = "SELECT COUNT(*) FROM favorites WHERE user_id = ?";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bind_param('i', $_SESSION['user_id']);
                                    $stmt->execute();
                                    echo $stmt->get_result()->fetch_assoc()['COUNT(*)'];
                                    ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Active Bookings</h4>
                        
                        <?php if ($bookings->num_rows > 0): ?>
                            <div class="row">
                                <?php while ($booking = $bookings->fetch_assoc()): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="booking-card">
                                            <div class="booking-header">
                                                <img src="<?php echo $booking['image'] ?? '../assets/images/default-property.jpg'; ?>" 
                                                     alt="Property" class="booking-image">
                                            </div>
                                            <div class="booking-content">
                                                <h5><?php echo htmlspecialchars($booking['house_no']); ?></h5>
                                                <p class="location">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($booking['location']); ?>
                                                </p>
                                                <p class="price">
                                                    <i class="fas fa-dollar-sign"></i>
                                                    <?php echo number_format($booking['price']); ?>
                                                </p>
                                                <div class="booking-details">
                                                    <p><strong>Move-in Date:</strong> 
                                                        <?php echo date('M d, Y', strtotime($booking['start_date'])); ?></p>
                                                    <p><strong>Monthly Rent:</strong> 
                                                        KSh <?php echo number_format($booking['monthly_rent'] ?? $booking['price']); ?></p>
                                                    <p><strong>Status:</strong> 
                                                        <span class="badge bg-<?php echo $booking['status'] == 'pending' ? 'warning' : 'success'; ?>">
                                                            <?php echo ucfirst($booking['status']); ?>
                                                        </span>
                                                    </p>
                                                </div>
                                                <div class="booking-actions">
                                                    <a href="property.php?id=<?php echo $booking['house_id']; ?>" 
                                                       class="btn btn-outline-primary">View Property</a>
                                                    <a href="booking-details.php?id=<?php echo $booking['id']; ?>" 
                                                       class="btn btn-outline-secondary">View Details</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                                <h5>No Active Bookings</h5>
                                <p class="text-muted">You haven't booked any properties yet.</p>
                                <a href="browse.php" class="btn btn-primary">Browse Properties</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
