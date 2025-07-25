<?php
require_once '../config/db.php';
require_once '../config/auth.php';

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
$main_sql = "SELECT h.*, c.name as category_name FROM houses h LEFT JOIN categories c ON h.category_id = c.id $conditions ORDER BY h.created_at DESC LIMIT ?, ?";
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
            --airbnb-pink: #FF385C;
            --airbnb-dark: #222222;
            --airbnb-light-gray: #F7F7F7;
            --airbnb-gray: #DDDDDD;
            --airbnb-text: #484848;
        }
        
        body {
            font-family: 'Circular', -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, sans-serif;
            color: var(--airbnb-text);
            background-color: white;
        }
        
        /* Header Styles */
        .navbar {
            padding: 1rem 2rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        }
        
        .navbar-brand {
            color: var(--airbnb-pink) !important;
            font-weight: 800;
            font-size: 1.5rem;
        }
        
        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem !important;
        }
        
        .btn-airbnb {
            background-color: var(--airbnb-pink);
            color: white;
            border-radius: 8px;
            font-weight: 600;
            padding: 0.5rem 1rem;
        }
        
        .btn-airbnb-outline {
            border: 1px solid var(--airbnb-gray);
            border-radius: 8px;
            font-weight: 600;
            padding: 0.5rem 1rem;
        }
        
        /* Hero Section */
        .hero-section {
            position: relative;
            padding: 6rem 0 4rem;
            background-color: white;
        }
        
        .search-bar {
            display: flex;
            align-items: center;
            border: 1px solid var(--airbnb-gray);
            border-radius: 40px;
            padding: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 850px;
            margin: 0 auto;
        }
        
        .search-option {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.2s ease;
        }
        
        .search-option:hover {
            background-color: var(--airbnb-light-gray);
        }
        
        .search-option.active {
            background-color: var(--airbnb-dark);
            color: white;
        }
        
        .search-divider {
            width: 1px;
            height: 24px;
            background-color: var(--airbnb-gray);
        }
        
        .search-button {
            background-color: var(--airbnb-pink);
            color: white;
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
        }
        
        .property-card:hover {
            transform: scale(1.02);
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
            border-radius: 12px;
        }
        
        .property-info {
            padding: 1rem 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .property-location {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .property-distance, .property-dates {
            color: #717171;
            font-size: 0.9rem;
        }
        
        .property-price {
            font-weight: 600;
            margin-top: auto;
            padding-top: 0.5rem;
        }
        
        .property-price span {
            font-weight: 400;
        }
        
        .wishlist-icon {
            position: absolute;
            top: 16px;
            right: 16px;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
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
            border-bottom-color: var(--airbnb-gray);
        }
        
        .category-item.active {
            border-bottom-color: var(--airbnb-dark);
        }
        
        .category-icon {
            font-size: 1.5rem;
        }
        
        /* Footer */
        .footer {
            background-color: var(--airbnb-light-gray);
            padding: 3rem 0;
            border-top: 1px solid var(--airbnb-gray);
        }
        
        .footer-section {
            margin-bottom: 2rem;
        }
        
        .footer-title {
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .footer-link {
            color: var(--airbnb-text);
            margin-bottom: 0.5rem;
            display: block;
            text-decoration: none;
        }
        
        .footer-link:hover {
            text-decoration: underline;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .property-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .property-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .search-bar {
                flex-direction: column;
                border-radius: 12px;
                padding: 1rem;
            }
            
            .search-option {
                width: 100%;
                border-radius: 8px;
                margin-bottom: 0.5rem;
            }
            
            .search-divider {
                display: none;
            }
            
            .property-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">SmartRental</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="search-bar ms-auto me-3 d-lg-none d-block">
                    <div class="search-option active">Anywhere</div>
                    <div class="search-divider"></div>
                    <div class="search-option">Any week</div>
                    <div class="search-divider"></div>
                    <div class="search-option">Add guests</div>
                    <div class="search-button">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown d-lg-block d-none">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> My Account
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="my_bookings.php">My Bookings</a></li>
                                <li><a class="dropdown-item" href="favorites.php">Favorites</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item d-lg-block d-none">
                            <a class="btn btn-airbnb-outline me-2" href="../login.php">Log in</a>
                        </li>
                        <li class="nav-item d-lg-block d-none">
                            <a class="btn btn-airbnb" href="register.php">Sign up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section with Search -->
    <section class="hero-section">
        <div class="container">
            <div class="search-bar d-none d-lg-flex">
                <div class="search-option active">Anywhere</div>
                <div class="search-divider"></div>
                <div class="search-option">Any week</div>
                <div class="search-divider"></div>
                <div class="search-option">Add guests</div>
                <div class="search-button">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            
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
                        <button type="submit" class="btn btn-airbnb btn-lg w-100">Search</button>
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
    
    // Default icons for categories (you can add more as needed)
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
        <h2 class="mb-4">Explore nearby</h2>
        
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
                                <div class="wishlist-icon" onclick="event.preventDefault(); event.stopPropagation(); toggleWishlist(this, <?php echo $row['id']; ?>);">
                                    <i class="far fa-heart"></i>
                                </div>
                                <div class="property-info mt-auto">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="property-location"><?php echo htmlspecialchars($row['house_no']); ?></h5>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-star"></i>
                                            <span class="ms-1">4.8</span>
                                        </div>
                                    </div>
                                    <p class="property-distance"><?php echo htmlspecialchars($row['location']); ?></p>
                                    <p class="property-dates"><?php echo htmlspecialchars($row['bedrooms']); ?> beds</p>
                                    <p class="property-price">Ksh <?php echo number_format($row['price']); ?> <span>Month</span></p>
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

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-3 footer-section">
                    <h5 class="footer-title">Support</h5>
                    <a href="#" class="footer-link">Help Center</a>
                    <a href="#" class="footer-link">Safety information</a>
                    <a href="#" class="footer-link">Cancellation options</a>
                    <a href="#" class="footer-link">Our COVID-19 Response</a>
                </div>
                <div class="col-md-3 footer-section">
                    <h5 class="footer-title">Community</h5>
                    <a href="#" class="footer-link">Disaster relief housing</a>
                    <a href="#" class="footer-link">Support refugees</a>
                    <a href="#" class="footer-link">Combating discrimination</a>
                </div>
                <div class="col-md-3 footer-section">
                    <h5 class="footer-title">Hosting</h5>
                    <a href="#" class="footer-link">Try hosting</a>
                    <a href="#" class="footer-link">AirCover for Hosts</a>
                    <a href="#" class="footer-link">Explore hosting resources</a>
                    <a href="#" class="footer-link">Visit our community forum</a>
                </div>
                <div class="col-md-3 footer-section">
                    <h5 class="footer-title">About</h5>
                    <a href="#" class="footer-link">Newsroom</a>
                    <a href="#" class="footer-link">Learn about new features</a>
                    <a href="#" class="footer-link">Careers</a>
                    <a href="#" class="footer-link">Investors</a>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6">
                    <p>© 2023 SmartRental, Inc. All rights reserved</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-decoration-none me-3"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-decoration-none me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-decoration-none"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>

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
        
        // Wishlist toggle
        document.querySelectorAll('.wishlist-icon').forEach(icon => {
            icon.addEventListener('click', function(e) {
                e.stopPropagation();
                const heart = this.querySelector('i');
                if (heart.classList.contains('far')) {
                    heart.classList.remove('far');
                    heart.classList.add('fas');
                    heart.style.color = 'var(--airbnb-pink)';
                } else {
                    heart.classList.remove('fas');
                    heart.classList.add('far');
                    heart.style.color = 'white';
                }
            });
        });
    </script>
</body>
</html>