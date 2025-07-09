<?php
require_once '../config/db.php';

// Get property ID from URL
$property_id = $_GET['id'] ?? 0;

// Get property details
$sql = "SELECT h.*, c.name as category_name 
        FROM houses h 
        LEFT JOIN categories c ON h.category_id = c.id 
        WHERE h.id = ? AND h.status = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    header('Location: browse.php');
    exit;
}
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
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <!-- Property Images -->
            <div class="col-md-8">
                <div id="propertyCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php
                        // Assuming images are stored in a comma-separated string
                        $images = explode(',', $property['images'] ?? '');
                        foreach ($images as $index => $image) {
                            if (trim($image)) {
                                $active = $index === 0 ? 'active' : '';
                                echo "<div class='carousel-item $active'>
                                        <img src='$image' class='d-block w-100' alt='Property Image'>
                                      </div>";
                            }
                        }
                        ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#propertyCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#propertyCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                </div>
            </div>

            <!-- Property Details -->
            <div class="col-md-4">
                <div class="property-details">
                    <h2><?php echo htmlspecialchars($property['house_no']); ?></h2>
                    <p class="location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($property['location']); ?>
                    </p>
                    <div class="price">
                        <h3>
                            <i class="fas fa-dollar-sign"></i>
                            <?php echo number_format($property['price']); ?>
                        </h3>
                        <span class="period">Per Month</span>
                    </div>

                    <div class="property-features">
                        <div class="feature">
                            <i class="fas fa-bed"></i>
                            <span><?php echo $property['bedrooms']; ?> Bedrooms</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-bath"></i>
                            <span><?php echo $property['bathrooms']; ?> Bathrooms</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-ruler-combined"></i>
                            <span><?php echo $property['area']; ?> sqft</span>
                        </div>
                    </div>

                    <div class="description">
                        <h4>Description</h4>
                        <p><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                    </div>

                    <div class="amenities">
                        <h4>Amenities</h4>
                        <ul class="amenities-list">
                            <?php
                            $amenities = explode(',', $property['amenities'] ?? '');
                            foreach ($amenities as $amenity) {
                                if (trim($amenity)) {
                                    echo "<li><i class='fas fa-check'></i> " . htmlspecialchars(trim($amenity)) . "</li>";
                                }
                            }
                            ?>
                        </ul>
                    </div>

                    <div class="booking-form">
                        <h4>Book This Property</h4>
                        <form id="bookingForm">
                            <div class="form-group">
                                <label>Move-in Date</label>
                                <input type="date" class="form-control" name="move_in_date" required>
                            </div>
                            <div class="form-group">
                                <label>Lease Duration (months)</label>
                                <select class="form-select" name="lease_duration" required>
                                    <option value="">Select Duration</option>
                                    <option value="6">6 Months</option>
                                    <option value="12">12 Months</option>
                                    <option value="24">24 Months</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
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
