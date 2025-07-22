<?php
require_once '../config/db.php';

$property_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get property details
$property_stmt = $conn->prepare("SELECT h.*, c.name as category_name 
    FROM houses h 
    LEFT JOIN categories c ON h.category_id = c.id 
    WHERE h.id = ? AND h.status = 1");

$property_stmt->bind_param('i', $property_id);
$property_stmt->execute();
$property_result = $property_stmt->get_result();
$property = $property_result->fetch_assoc();
$property_stmt->close();

if (!$property) {
    header('Location: browse.php');
    exit;
}

// Get all media (images + videos) once
$media_stmt = $conn->prepare("SELECT * FROM house_media WHERE house_id = ? ORDER BY created_at DESC");
$media_stmt->bind_param('i', $property_id);
$media_stmt->execute();
$media_result = $media_stmt->get_result();
$media_files = $media_result->fetch_all(MYSQLI_ASSOC);
$media_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($property['house_no']); ?> - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/property.css">
</head>
<style>
.carousel-control-prev-icon,
.carousel-control-next-icon {
  filter: brightness(0); /* This makes the default white icon black */
}
</style>
<body>
<?php include 'includes/header.php'; ?>

<div class="container mt-5">
    <div class="row">
        <!-- Property Images and Videos -->
        <div class="col-md-8">
            <div id="propertyCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner" style="height: 700px;">
                    <div class="carousel-item active">
                        <img src="<?php echo $property['main_image'] ? '../uploads/' . htmlspecialchars($property['main_image']) : 'assets/images/default-property.jpg'; ?>" class="d-block w-100" alt="Main Property Image">
                    </div>

                    <?php foreach ($media_files as $media): ?>
                        <?php if ($media['media_type'] === 'image'): ?>
                            <div class="carousel-item">
                                <img src="<?php echo '../uploads/' . htmlspecialchars($media['file_path']); ?>" class="d-block w-100" alt="Additional Image">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <button class="carousel-control-prev" type="button" data-bs-target="#propertyCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#propertyCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
            </div>

            <!-- Videos -->
            <div class="mt-4">
                <h4>Videos</h4>
                <div class="row">
                    <?php foreach ($media_files as $media): ?>
                        <?php if ($media['media_type'] === 'video'): ?>
                            <div class="col-md-6 mb-3">
                                <video controls class="w-100">
                                    <source src="<?php echo '../uploads/' . htmlspecialchars($media['file_path']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Property Details -->
        <div class="col-md-4">
            <div class="property-details">
                <h2><?php echo htmlspecialchars($property['house_no']); ?></h2>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($property['location']); ?></p>

                <h3 class="text-primary">Ksh. <?php echo number_format($property['price']); ?> <small class="text-muted">/month</small></h3>

                <div class="property-features mt-3">
                    <p><i class="fas fa-bed"></i> <?php echo $property['bedrooms']; ?> Bedrooms</p>
                    <p><i class="fas fa-bath"></i> <?php echo $property['bathrooms']; ?> Bathrooms</p>
                    <p><i class="fas fa-ruler-combined"></i> <?php echo $property['area']; ?> sqft</p>
                </div>

                <div class="description mt-3">
                    <h4>Description</h4>
                    <p><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                </div>

                <div class="amenities mt-3">
                    <h4>Amenities</h4>
                    <ul>
                        <?php
                        $amenities = array_filter(array_map('trim', explode(',', $property['amenities'] ?? '')));
                        foreach ($amenities as $amenity) {
                            echo "<li><i class='fas fa-check'></i> " . htmlspecialchars($amenity) . "</li>";
                        }
                        ?>
                    </ul>
                </div>

                <div class="booking-form mt-4">
                    <h4>Book This Property</h4>
                    <form id="bookingForm">
                        <div class="mb-3">
                            <label class="form-label">Move-in Date</label>
                            <input type="date" class="form-control" name="move_in_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lease Duration</label>
                            <select class="form-select" name="lease_duration" required>
                                <option value="">Select</option>
                                <option value="6">6 Months</option>
                                <option value="12">12 Months</option>
                                <option value="24">24 Months</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Book Now</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/property.js"></script>
</body>
</html>
