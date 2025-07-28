<?php
require_once '../config/db.php';
require_once '../config/auth.php';

$page_title = 'Smart Rental - Find Your Perfect Home';

// Pagination settings
$records_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $records_per_page;

// Search and filter parameters
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$propertyType = isset($_GET['propertyType']) ? trim($_GET['propertyType']) : '';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : '';

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

// Add category filter
if (!empty($category_id)) {
    $conditions .= " AND h.category_id = ?";
    $params[] = $category_id;
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
$main_sql = "SELECT h.*, c.name as category_name, h.available_units, h.total_units FROM houses h LEFT JOIN categories c ON h.category_id = c.id $conditions ORDER BY h.created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($main_sql);
$main_params = $params;
$main_types = $types . 'ii';
$main_params[] = $start;
$main_params[] = $records_per_page;
$stmt->bind_param($main_types, ...$main_params);
$stmt->execute();
$result = $stmt->get_result();

// Get categories for dropdown
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1976D2;
            --dark-blue: #0D47A1;
            --light-blue: #E3F2FD;
            --accent-blue: #2196F3;
            --text-dark: #212121;
            --text-light: #757575;
            --white: #FFFFFF;
        }
        
        body {
            font-family: 'Circular', -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, sans-serif;
            color: var(--text-dark);
            background-color: var(--white);
        }
        
        /* Header Styles */
        .navbar {
            padding: 1rem 2rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            background-color: var(--white);
        }
        
        .navbar-brand {
            color: var(--primary-blue) !important;
            font-weight: 800;
            font-size: 1.5rem;
        }
        
        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            color: var(--text-dark);
        }
        
        .btn-outline-blue {
            border: 1px solid var(--primary-blue);
            border-radius: 8px;
            font-weight: 600;
            padding: 0.5rem 1rem;
            color: var(--primary-blue);
        }
        
        .btn-primary-blue, 
        .btn-primary-blue:active, 
        .btn-primary-blue:focus {
            background-color: var(--primary-blue) !important;
            color: white !important;
            border: 1px solid var(--primary-blue) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            padding: 0.5rem 1rem !important;
            box-shadow: none !important;
        }
        
        .btn-primary-blue:hover {
            background-color: #1565c0 !important;
            border-color: #1565c0 !important;
            color: white !important;
        }
        
        /* Hero Section with Background Image */
        .hero-section {
            position: relative;
            padding: 8rem 0 6rem;
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), 
                        url('assets/images/hero-bg.png') center/cover no-repeat;
            color: var(--white);
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .hero-section h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }
        
        .hero-section p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .search-bar {
            display: flex;
            align-items: center;
            border: 1px solid var(--white);
            border-radius: 40px;
            padding: 0.5rem;
            background-color: rgba(255,255,255,0.2);
            max-width: 850px;
            margin: 0 auto;
            backdrop-filter: blur(5px);
        }
        
        .search-option {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.2s ease;
            color: var(--white);
        }
        
        .search-option:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .search-option.active {
            background-color: var(--primary-blue);
            color: var(--white);
        }
        
        .search-divider {
            width: 1px;
            height: 24px;
            background-color: rgba(255,255,255,0.5);
        }
        
        .search-button {
            background-color: var(--primary-blue);
            color: var(--white);
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Property Cards - 4 per row with equal height */
        .property-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            padding: 2rem 0;
        }
        
        .property-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background-color: var(--white);
        }
        
        .property-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .property-image-container {
            position: relative;
            width: 100%;
            padding-bottom: 75%; /* 4:3 aspect ratio */
            overflow: hidden;
        }
        
        .property-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .property-info {
            padding: 1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .property-location {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-dark);
        }
        
        .property-distance, .property-dates {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .property-price {
            font-weight: 700;
            margin-top: auto;
            padding-top: 0.5rem;
            color: var(--primary-blue);
        }
        
        .property-price span {
            font-weight: 400;
            color: var(--text-light);
        }
        
        .property-card-actions {
            position: absolute;
            top: 16px;
            left: 0;
            right: 0;
            padding: 0 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            z-index: 2;
        }
        
        .wishlist-icon {
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: rgba(0, 0, 0, 0.5);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
        }
        
        .wishlist-icon:hover {
            transform: scale(1.1);
        }
        
        .available-badge {
            background-color: var(--primary-blue);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-right: auto;
        }
        
        .available-badge i {
            font-size: 0.9rem;
        }
        
        /* Category Filters */
        .category-filters {
            display: flex;
            gap: 1.5rem;
            padding: 2rem 0;
            overflow-x: auto;
            scrollbar-width: none;
        }
        
        .category-filters::-webkit-scrollbar {
            display: none;
        }
        
        .category-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
            min-width: 80px;
        }
        
        .category-item:hover {
            border-bottom-color: var(--light-blue);
        }
        
        .category-item.active {
            border-bottom-color: var(--primary-blue);
        }
        
        .category-icon {
            font-size: 1.5rem;
            color: var(--primary-blue);
        }
        
        /* Footer */
        .footer {
            background-color: var(--light-blue);
            padding: 3rem 0;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        .footer-section {
            margin-bottom: 2rem;
        }
        
        .footer-title {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark-blue);
        }
        
        .footer-link {
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: block;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .footer-link:hover {
            color: var(--primary-blue);
            text-decoration: none;
        }
        
        /* Form Elements */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.25rem rgba(25, 118, 210, 0.25);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .property-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* Hero Section Styles */
.hero-section {
    position: relative;
    padding: 8rem 0 6rem;
    text-align: center;
    color: white;
    margin-top: -76px; /* Offset for fixed header */
    min-height: 400px;
    display: flex;
    align-items: center;
    overflow: hidden;
    z-index: 1; /* Add this to ensure content is above any potential overlapping elements */
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('assets/images/hero-bg.png') center/cover no-repeat;
    z-index: -1;
}

.hero-section::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6); /* Dark overlay */
    z-index: -1;
}

.hero-section .container {
    position: relative;
    z-index: 2; /* Ensure content is above the overlay */
    width: 100%;
}
        
        .hero-section h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        
        .hero-section p {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            text-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }

        @media (max-width: 992px) {
            .property-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .hero-section h1 {
                font-size: 2.5rem;
            }
            
            .hero-section p {
                font-size: 1.25rem;
            }
        }
        
        @media (max-width: 768px) {
            .search-bar {
                flex-direction: column;
                border-radius: 12px;
                padding: 1rem;
                background-color: rgba(255,255,255,0.3);
            }
            
            .search-option {
                width: 100%;
                border-radius: 8px;
                margin-bottom: 0.5rem;
                text-align: center;
            }
            
            .search-divider {
                display: none;
            }
            
            .property-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-section {
                padding: 5rem 0 3rem;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .hero-section p {
                font-size: 1rem;
            }
            
            .navbar {
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

    <!-- Hero Section with Background Image -->
    <section class="hero-section">
        <div class="container">
            <h1>Find Your Perfect Home</h1>
            <p>Discover the best rental properties in your desired location</p>
           
            
            <form id="searchForm" class="mt-4" method="GET" action="index.php">
                <div class="row g-3 justify-content-center">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-lg" placeholder="Location" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-lg" id="propertyType" name="propertyType">
                            <option value="">Property Type</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($propertyType == $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <input type="number" class="form-control form-control-lg" placeholder="Min Price" id="min_price" name="min_price" value="<?php echo htmlspecialchars($min_price); ?>">
                            <span class="input-group-text">-</span>
                            <input type="number" class="form-control form-control-lg" placeholder="Max Price" id="max_price" name="max_price" value="<?php echo htmlspecialchars($max_price); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary-blue btn-lg w-100">Search</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Category Filters -->
    <?php
    // Fetch categories from database
    $category_query = "SELECT id, name FROM categories ORDER BY name";
    $category_result = $conn->query($category_query);
    
    // Default icons for categories
    $category_icons = [
        'Beach' => 'umbrella-beach',
        'Mountain' => 'mountain',
        'Pool' => 'swimming-pool',
        'City' => 'city',
        'Camping' => 'campground',
        'Unique' => 'igloo',
        'Lakefront' => 'water',
        'Countryside' => 'tree',
        'Apartment' => 'building',
        'House' => 'home',
        'Villa' => 'home',
        'Cottage' => 'home'
    ];
    ?>
    <div class="container">
        <div class="category-filters">
            <div class="category-item active" data-category="all">
                <i class="fas fa-home category-icon"></i>
                <span>All</span>
            </div>
            <?php if ($category_result && $category_result->num_rows > 0): ?>
                <?php while($category = $category_result->fetch_assoc()): ?>
                    <div class="category-item" data-category="<?php echo htmlspecialchars($category['id']); ?>">
                        <i class="fas fa-<?php echo htmlspecialchars($category_icons[$category['name']] ?? 'home'); ?> category-icon"></i>
                        <span><?php echo htmlspecialchars($category['name']); ?></span>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Property Listings -->
    <div class="container">
        <h2 class="mb-4">Explore nearby properties</h2>
        
        <div class="property-grid">
            <?php if ($total_records > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="property-card">
                        <a href="property.php?id=<?php echo $row['id']; ?>" class="text-decoration-none text-dark d-block h-100">
                            <div class="position-relative h-100 d-flex flex-column">
                                <div class="property-image-container">
                                    <img src="<?php echo $row['main_image'] ? '../uploads/' . $row['main_image'] : 'assets/images/hero-bg.png'; ?>" 
                                         class="property-image" alt="<?php echo htmlspecialchars($row['house_no']); ?>">
                                </div>
                                <div class="property-card-actions">
                                    <div class="available-badge">
                                        <i class="fas fa-home me-1"></i> <?php echo ($row['available_units'] ?? 0) . '/' . ($row['total_units'] ?? 1); ?> units
                                    </div>
                                    <?php if (is_logged_in()): ?>
                                    <?php
                                    // Check if this property is in user's favorites
                                    $is_favorite = false;
                                    $fav_check = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND house_id = ?");
                                    $fav_check->bind_param('ii', $_SESSION['user_id'], $row['id']);
                                    $fav_check->execute();
                                    $is_favorite = $fav_check->get_result()->num_rows > 0;
                                    ?>
                                    <div class="favorite-icon" data-property-id="<?php echo $row['id']; ?>" onclick="event.preventDefault(); event.stopPropagation(); toggleFavorite(this, <?php echo $row['id']; ?>);">
                                        <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart text-danger"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="property-info mt-auto">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="property-location"><?php echo htmlspecialchars($row['house_no']); ?></h5>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-star text-warning"></i>
                                            <span class="ms-1">4.8</span>
                                        </div>
                                    </div>
                                    <p class="property-distance"><?php echo htmlspecialchars($row['location']); ?></p>
                                    <p class="property-dates"><?php echo htmlspecialchars($row['bedrooms']); ?> beds</p>
                                    <p class="property-price">Ksh <?php echo number_format($row['price']); ?> <span>/month</span></p>
                                </div>
                            </div>
                        </a>
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

    <!-- Toast Notification -->
    <div class="position-fixed top-0 start-50 translate-middle-x mt-5" style="z-index: 1100;">
        <div id="favoriteToast" class="toast align-items-center" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage">
                    <!-- Message will be inserted here -->
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
    // Initialize toast
    const toastEl = document.getElementById('favoriteToast');
    const toastMessage = document.getElementById('toastMessage');
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });

    async function toggleFavorite(element, propertyId) {
        if (!<?php echo is_logged_in() ? 'true' : 'false'; ?>) {
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        const icon = element.querySelector('i');
        const isFavorite = icon.classList.contains('fas');
        
        try {
            const response = await fetch('api/toggle_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ house_id: propertyId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Toggle heart icon
                if (data.is_favorite) {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    showToast('Property added to favorites!');
                } else {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    showToast('Property removed from favorites');
                }
            } else {
                showToast(data.message || 'Error updating favorites', true);
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', true);
        }
    }

    function showToast(message, isError = false) {
        const toast = document.getElementById('favoriteToast');
        const toastMessage = document.getElementById('toastMessage');
        
        // Remove previous classes
        toast.classList.remove('success', 'error');
        
        // Add appropriate class based on message type
        if (isError) {
            toast.classList.add('error');
        } else {
            toast.classList.add('success');
        }
        
        // Set message and show
        toastMessage.textContent = message;
        const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 3000 });
        bsToast.show();
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add active class to clicked category filter and update URL
        document.querySelectorAll('.category-item').forEach(item => {
            item.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                const url = new URL(window.location.href);
                
                // Update active class
                document.querySelector('.category-item.active').classList.remove('active');
                this.classList.add('active');
                
                // Update URL with category parameter
                if (category === 'all') {
                    url.searchParams.delete('category');
                } else {
                    url.searchParams.set('category', category);
                }
                
                // Reset to first page when changing categories
                url.searchParams.set('page', '1');
                
                // Navigate to the new URL
                window.location.href = url.toString();
            });
        });
        
        // Set active category based on URL parameter on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const categoryParam = urlParams.get('category');
            
            if (categoryParam) {
                const activeItem = document.querySelector(`.category-item[data-category="${categoryParam}"]`);
                if (activeItem) {
                    document.querySelector('.category-item.active').classList.remove('active');
                    activeItem.classList.add('active');
                }
            }
        });
        
        // Wishlist toggle function
        function toggleWishlist(element, propertyId) {
            const heart = element.querySelector('i');
            
            // Check if user is logged in
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = '../login.php';
                return;
            <?php endif; ?>
            
            // Toggle heart icon
            if (heart.classList.contains('far')) {
                heart.classList.remove('far');
                heart.classList.add('fas');
                heart.style.color = 'var(--primary-blue)';
                
                // Add to wishlist (AJAX call would go here)
                console.log('Added property ' + propertyId + ' to wishlist');
            } else {
                heart.classList.remove('fas');
                heart.classList.add('far');
                heart.style.color = 'var(--white)';
                
                // Remove from wishlist (AJAX call would go here)
                console.log('Removed property ' + propertyId + ' from wishlist');
            }
        }
    </script>
</body>
</html>