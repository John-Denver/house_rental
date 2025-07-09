<?php
require_once 'config/db.php';

// Pagination settings
$records_per_page = 9;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $records_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$propertyType = isset($_GET['propertyType']) ? $_GET['propertyType'] : '';

// Build query conditions
$conditions = "WHERE h.status = 1";
$params = [];

if(!empty($search)) {
    $conditions .= " AND (h.house_no LIKE ? OR h.description LIKE ? OR h.location LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if(!empty($propertyType)) {
    $conditions .= " AND c.name = ?";
    $params[] = $propertyType;
}

// Get total number of records
$sql = "SELECT COUNT(*) as total FROM houses h LEFT JOIN categories c ON h.category_id = c.id $conditions";
if(count($params) > 0) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($sql)->fetch_assoc()['total'];
}
$total_pages = ceil($total_records / $records_per_page);

// Reset page if search is empty
if(empty($search) && empty($propertyType)) {
    $page = 1;
    $start = 0;
}

// Get houses for current page
$sql = "SELECT h.*, c.name as category_name 
        FROM houses h 
        LEFT JOIN categories c ON h.category_id = c.id 
        $conditions
        ORDER BY h.created_at DESC";

if(count($params) > 0) {
    $sql .= " LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $params = array_merge($params, [$start, $records_per_page]);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql .= " LIMIT $start, $records_per_page";
    $result = $conn->query($sql);
}

// Get actual number of results for this page
$current_page_records = $result->num_rows;

// Get actual number of results for this page
$current_page_records = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Rental - Find Your Perfect Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
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
</style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/images/smart_rental_logo.png" alt="Smart Rental" height="40" class="me-2">
                <span class="navbar-text">Smart Rental</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">My Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center position-relative">
                    <h1>Find Your Perfect Home</h1>
                    <form id="searchForm" class="search-form">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" placeholder="Location" id="location">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="propertyType">
                                    <option value="">Property Type</option>
                                    <option value="apartment">Apartment</option>
                                    <option value="house">House</option>
                                    <option value="studio">Studio</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" class="form-control" placeholder="Price Range" id="priceRange">
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

    <?php
// Get featured properties
$sql = "SELECT h.*, c.name as category_name 
        FROM houses h 
        LEFT JOIN categories c ON h.category_id = c.id 
        WHERE h.status = 1 
        ORDER BY h.created_at DESC 
        LIMIT 6";

$result = $conn->query($sql);
?>

<!-- Featured Properties -->
<section class="featured-properties">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1 class="text-center mb-4">Available Properties</h1>
                
                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form class="d-flex" role="search" method="GET" action="index.php">
                            <input class="form-control me-2" type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search properties" aria-label="Search">
                            <button class="btn btn-outline-primary" type="submit">Search</button>
                        </form>
                    </div>
                    
                    <div class="col-md-6">
                        <form class="d-flex" role="search" method="GET" action="index.php">
                            <?php
                            // Get categories for dropdown
                            $categories = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
                            ?>
                            <select class="form-select me-2" name="propertyType">
                                <option value="">All Property Types</option>
                                <?php while($category = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $category['name']; ?>" 
                                            <?php echo isset($_GET['propertyType']) && $_GET['propertyType'] == $category['name'] ? 'selected' : ''; ?>>
                                        <?php echo $category['name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button class="btn btn-outline-primary" type="submit">Filter</button>
                        </form>
                    </div>
                </div>

                <!-- Results Count -->
                <div class="row mb-4">
                    <div class="col-12">
                        <p class="mb-0">
                            <?php 
                            if(!empty($search)) {
                                echo "Showing " . $current_page_records . " of " . $total_records . " results for '" . htmlspecialchars($search) . "'";
                            } else {
                                echo "Showing " . $total_records . " properties";
                            }
                            ?>
                        </p>
                    </div>
                </div>
        <div class="row g-4">
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <img src="<?php echo $row['image'] ? '../uploads/' . $row['image'] : 'assets/images/default-property.jpg'; ?>" 
                             class="card-img-top" alt="<?php echo $row['house_no']; ?>">
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php echo $row['house_no']; ?>
                                <?php if($row['featured']): ?>
                                    <span class="badge bg-danger">Featured</span>
                                <?php endif; ?>
                            </h5>
                            <p class="card-text">
                                <i class="fas fa-map-marker-alt text-primary"></i> <?php echo $row['location']; ?>
                                <br>
                                <i class="fas fa-bed text-primary"></i> <?php echo $row['bedrooms']; ?> Beds
                                <i class="fas fa-bath text-primary"></i> <?php echo $row['bathrooms']; ?> Baths
                                <br>
                                <i class="fas fa-ruler-combined text-primary"></i> <?php echo $row['area']; ?> sqft
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="text-primary mb-0">
                                    $<?php echo number_format($row['price']); ?>/month
                                </h4>
                                <a href="property.php?id=<?php echo $row['id']; ?>" class="btn btn-primary">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <nav aria-label="Page navigation" class="mt-5">
            <ul class="pagination justify-content-center">
                <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
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
