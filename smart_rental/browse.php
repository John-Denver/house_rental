<?php
require_once '../config/db.php';

// Get search parameters
$location = $_GET['location'] ?? '';
$propertyType = $_GET['propertyType'] ?? '';
$priceRange = $_GET['priceRange'] ?? '';

// Build SQL query based on search parameters
$sql = "SELECT h.*, c.name as category_name 
        FROM houses h 
        LEFT JOIN categories c ON h.category_id = c.id 
        WHERE h.status = 1";

$params = [];
if ($location) {
    $sql .= " AND h.location LIKE ?";
    $params[] = "%$location%";
}
if ($propertyType) {
    $sql .= " AND c.name = ?";
    $params[] = $propertyType;
}
if ($priceRange) {
    $sql .= " AND h.price <= ?";
    $params[] = $priceRange;
}

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$results = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Properties - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <!-- Filters -->
            <div class="col-md-3">
                <div class="filters-card">
                    <h4>Filters</h4>
                    <div class="mb-3">
                        <label>Location</label>
                        <input type="text" class="form-control" id="filterLocation">
                    </div>
                    <div class="mb-3">
                        <label>Price Range</label>
                        <div class="d-flex">
                            <input type="number" class="form-control me-2" id="minPrice" placeholder="Min">
                            <input type="number" class="form-control" id="maxPrice" placeholder="Max">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Property Type</label>
                        <select class="form-select" id="filterPropertyType">
                            <option value="">All Types</option>
                            <?php
                            $categories = $conn->query("SELECT * FROM categories WHERE status = 1");
                            while ($cat = $categories->fetch_assoc()) {
                                echo "<option value='" . $cat['name'] . "'>" . $cat['name'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100" onclick="applyFilters()">Apply Filters</button>
                </div>
            </div>

            <!-- Properties Grid -->
            <div class="col-md-9">
                <div class="row" id="propertiesGrid">
                    <?php while ($property = $results->fetch_assoc()) { ?>
                        <div class="col-md-4 mb-4">
                            <div class="property-card">
                                <img src="<?php echo $property['image'] ?? 'assets/images/default-property.jpg'; ?>" 
                                     class="property-image" alt="Property">
                                <div class="property-info">
                                    <h5><?php echo htmlspecialchars($property['house_no']); ?></h5>
                                    <p class="location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($property['location']); ?>
                                    </p>
                                    <p class="price">
                                        <i class="fas fa-dollar-sign"></i>
                                        <?php echo number_format($property['price']); ?>
                                    </p>
                                    <div class="property-features">
                                        <span><i class="fas fa-bed"></i> <?php echo $property['bedrooms']; ?> Beds</span>
                                        <span><i class="fas fa-bath"></i> <?php echo $property['bathrooms']; ?> Baths</span>
                                        <span><i class="fas fa-ruler-combined"></i> <?php echo $property['area']; ?> sqft</span>
                                    </div>
                                    <a href="property.php?id=<?php echo $property['id']; ?>" 
                                       class="btn btn-primary w-100">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/browse.js"></script>
</body>
</html>
