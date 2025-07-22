<?php
require_once '../config/db.php';
require_once '../config/auth.php';

// Pagination settings
$records_per_page = 9;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $records_per_page;

// Search and filter parameters
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$propertyType = isset($_GET['propertyType']) ? trim($_GET['propertyType']) : '';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : '';

// Build query conditions
$conditions = "WHERE h.status = 1";
$params = [];
$types = '';

if (!empty($location)) {
    $conditions .= " AND (h.location LIKE ? OR h.house_no LIKE ? OR h.description LIKE ?)";
    $loc_term = "%$location%";
    $params[] = $loc_term;
    $params[] = $loc_term;
    $params[] = $loc_term;
    $types .= 'sss';
}
if (!empty($propertyType)) {
    $conditions .= " AND c.id = ?";
    $params[] = $propertyType;
    $types .= 'i';
}
if ($min_price !== '') {
    $conditions .= " AND h.price >= ?";
    $params[] = $min_price;
    $types .= 'd';
}
if ($max_price !== '') {
    $conditions .= " AND h.price <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

// Prepare and execute count query
$count_sql = "SELECT COUNT(*) as total FROM houses h LEFT JOIN categories c ON h.category_id = c.id $conditions";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Prepare and execute main query with limit
$main_sql = "SELECT h.*, c.name as category_name FROM houses h LEFT JOIN categories c ON h.category_id = c.id $conditions ORDER BY h.created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($main_sql);
$main_params = $params;
$main_types = $types . 'ii';
$main_params[] = $start;
$main_params[] = $records_per_page;
$stmt->bind_param($main_types, ...$main_params);
$stmt->execute();
$result = $stmt->get_result();

// Get categories for dropdown (for hero section)
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Rental - Find Your Perfect Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
    .units-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1;
    }
    .units-badge .badge {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        border-radius: 20px;
    }
    .property-image-container {
        position: relative;
        overflow: hidden;
    }
    .property-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }
    .hero-section {
        position: relative;
        min-height: 400px;
        overflow: hidden;
    }
    .hero-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: url('assets/images/hero-bg.png') center/cover no-repeat;
        z-index: -1;
    }
    .hero-section::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.05);
        z-index: -1;
    }
    .hero-section .container {
        position: relative;
        z-index: 1;
    }
    .hero-section h1 {
        color: white;
        font-weight: bold;
        margin-bottom: 2rem;
    }
    .hero-section .search-form {
        padding: 1.5rem;
        border-radius: 10px;
        background: transparent;
    }
    .hero-section .search-form .form-control,
    .hero-section .search-form .form-select {
        background-color: transparent;
        border: 1px solid #0d6efd;
        color: white;
        padding: 0.75rem;
        border-radius: 8px;
    }
    .hero-section .search-form .form-control:focus,
    .hero-section .search-form .form-select:focus {
        background-color: transparent;
        border-color: #0b5ed7;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    .hero-section .search-form .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: white;
        border-radius: 8px;
    }
    .hero-section .search-form .btn-primary:hover {
        background-color: #0b5ed7;
        border-color: #0a58ca;
    }
    .hero-section .search-form .form-control::placeholder,
    .hero-section .search-form .form-select::placeholder {
        color: rgba(255, 255, 255, 0.7);
    }
    .hero-section .search-form .form-select,
    .hero-section .search-form .form-select option {
        background-color: #212529 !important;
        color: #fff !important;
        border: 1px solid #0d6efd !important;
    }
</style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include('./includes/header.php'); ?>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center position-relative">
                    <h1>Find Your Perfect Home</h1>
                    <form id="searchForm" class="search-form" method="GET" action="index.php">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" placeholder="Location" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select bg-dark text-white" id="propertyType" name="propertyType">
                                    <option value="">Property Type</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($propertyType == $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="input-group">
                                    <input type="number" class="form-control" placeholder="Min Price" id="min_price" name="min_price" value="<?php echo htmlspecialchars($min_price); ?>">
                                    <span class="input-group-text">-</span>
                                    <input type="number" class="form-control" placeholder="Max Price" id="max_price" name="max_price" value="<?php echo htmlspecialchars($max_price); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<!-- Featured Properties -->
<section class="featured-properties">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1 class="text-center mb-4">Available Properties</h1>

                <!-- Results Count -->
                <div class="row mb-4">
                    <div class="col-12">
                        <p class="mb-0">
                            <?php 
                            if ($total_records > 0) {
                                echo "Showing $total_records properties";
                            } else {
                                echo "No properties found for your search.";
                            }
                            ?>
                        </p>
                    </div>
                </div>
        <div class="row g-4">
            <?php if ($total_records > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="col-md-4">
                        <div class="property-card">
                            <div class="card">
                                <div class="property-image-container">
                                    <img src="<?php echo $row['main_image'] ? '../uploads/' . $row['main_image'] : 'assets/images/default-property.jpg'; ?>" 
                                         class="property-image" alt="<?php echo htmlspecialchars($row['house_no']); ?>">
                                    <div class="units-badge">
                                        <span class="badge bg-primary">
                                            <?php echo $row['available_units'] . '/' . $row['total_units']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($row['house_no']); ?>
                                        <?php if(isset($row['featured']) && $row['featured']): ?>
                                            <span class="badge bg-danger">Featured</span>
                                        <?php endif; ?>
                                    </h5>
                                    <p class="card-text">
                                        <i class="fas fa-map-marker-alt text-primary"></i> <?php echo htmlspecialchars($row['location']); ?>
                                        <br>
                                        <i class="fas fa-bed text-primary"></i> <?php echo htmlspecialchars($row['bedrooms']); ?> Beds
                                        <i class="fas fa-bath text-primary"></i> <?php echo htmlspecialchars($row['bathrooms']); ?> Baths
                                        <br>
                                        <i class="fas fa-ruler-combined text-primary"></i> <?php echo htmlspecialchars($row['area']); ?> sqft
                                    </p>
                                </div>
                                <div class="card-footer">
                                    <div class="price">
                                        Ksh. <?php echo number_format($row['price']); ?>/month
                                    </div>
                                    <?php if($row['available_units'] > 0): ?>
                                        <a href="property.php?id=<?php echo $row['id']; ?>" class="btn btn-primary">
                                            View Details
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>
                                            Unavailable
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-warning text-center">No properties found matching your search criteria.</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-5">
            <ul class="pagination justify-content-center">
                <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($location) ? '&location=' . urlencode($location) : ''; ?><?php echo !empty($propertyType) ? '&propertyType=' . urlencode($propertyType) : ''; ?><?php echo !empty($min_price) ? '&min_price=' . urlencode($min_price) : ''; ?><?php echo !empty($max_price) ? '&max_price=' . urlencode($max_price) : ''; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($location) ? '&location=' . urlencode($location) : ''; ?><?php echo !empty($propertyType) ? '&propertyType=' . urlencode($propertyType) : ''; ?><?php echo !empty($min_price) ? '&min_price=' . urlencode($min_price) : ''; ?><?php echo !empty($max_price) ? '&max_price=' . urlencode($max_price) : ''; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($location) ? '&location=' . urlencode($location) : ''; ?><?php echo !empty($propertyType) ? '&propertyType=' . urlencode($propertyType) : ''; ?><?php echo !empty($min_price) ? '&min_price=' . urlencode($min_price) : ''; ?><?php echo !empty($max_price) ? '&max_price=' . urlencode($max_price) : ''; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</section>

    <!-- Footer -->
    <footer class="footer bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>About Smart Rental</h5>
                    <p>Find your perfect home with ease. Smart Rental connects renters with amazing properties.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Contact</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <p>Email: info@smartrental.com</p>
                    <p>Phone: +254 700 000 000</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
