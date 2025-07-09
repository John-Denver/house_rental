<?php
require_once 'config/db.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle favorite actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $property_id = $_POST['property_id'] ?? 0;

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, property_id, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param('ii', $_SESSION['user_id'], $property_id);
        $stmt->execute();
    } elseif ($action === 'remove') {
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND property_id = ?");
        $stmt->bind_param('ii', $_SESSION['user_id'], $property_id);
        $stmt->execute();
    }
}

// Get user's favorites
$sql = "SELECT f.*, h.*, c.name as category_name 
        FROM favorites f 
        LEFT JOIN houses h ON f.property_id = h.id 
        LEFT JOIN categories c ON h.category_id = c.id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$favorites = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favorites - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Your Favorites</h5>
                        <p class="card-text">Manage your saved properties here.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Saved Properties</h4>
                        
                        <?php if ($favorites->num_rows > 0): ?>
                            <div class="row">
                                <?php while ($favorite = $favorites->fetch_assoc()): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="property-card">
                                            <div class="property-header">
                                                <img src="<?php echo $favorite['image'] ?? '../assets/images/default-property.jpg'; ?>" 
                                                     alt="Property" class="property-image">
                                                <div class="favorite-btn">
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="property_id" value="<?php echo $favorite['property_id']; ?>">
                                                        <input type="hidden" name="action" value="remove">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-heart"></i> Remove
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <div class="property-content">
                                                <h5><?php echo htmlspecialchars($favorite['house_no']); ?></h5>
                                                <p class="location">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($favorite['location']); ?>
                                                </p>
                                                <p class="price">
                                                    <i class="fas fa-dollar-sign"></i>
                                                    <?php echo number_format($favorite['price']); ?>
                                                </p>
                                                <div class="property-features">
                                                    <span><i class="fas fa-bed"></i> <?php echo $favorite['bedrooms']; ?> Beds</span>
                                                    <span><i class="fas fa-bath"></i> <?php echo $favorite['bathrooms']; ?> Baths</span>
                                                    <span><i class="fas fa-ruler-combined"></i> <?php echo $favorite['area']; ?> sqft</span>
                                                </div>
                                                <div class="property-actions">
                                                    <a href="property.php?id=<?php echo $favorite['property_id']; ?>" 
                                                       class="btn btn-outline-primary">View Details</a>
                                                    <a href="booking.php?id=<?php echo $favorite['property_id']; ?>" 
                                                       class="btn btn-outline-success">Book Now</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-heart fa-4x text-muted mb-3"></i>
                                <h5>No Saved Properties</h5>
                                <p class="text-muted">You haven't saved any properties yet.</p>
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
