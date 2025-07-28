<?php
require_once '../config/db.php';
require_once '../config/auth.php';

// Get property ID from URL
$property_id = $_GET['id'] ?? 0;

// Get property details with owner information
$sql = "SELECT h.*, c.name as category_name, u.name as owner_name, u.phone_number as owner_phone
        FROM houses h 
        LEFT JOIN categories c ON h.category_id = c.id 
        LEFT JOIN users u ON h.landlord_id = u.id 
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1976D2;
            --dark-blue: #0D47A1;
            --light-blue: #E3F2FD;
            --accent-blue: #2196F3;
            --text-dark: #212121;
            --text-light: #757575;
            --white: #FFFFFF;
            --border-radius: 12px;
        }
        
        body {
            font-family: 'Circular', -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, sans-serif;
            color: var(--text-dark);
            background-color: var(--white);
        }
        
        /* Header */
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
        
        /* Hero Section */
        .property-hero {
            position: relative;
            height: 60vh;
            min-height: 500px;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: flex-end;
            padding-bottom: 2rem;
            margin-bottom: 2rem;
        }
        
        .property-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent 50%);
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            color: var(--white);
            width: 100%;
        }
        
        .property-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .property-location {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            z-index: 2;
        }
        
        .status-available {
            background-color: var(--primary-blue);
            color: var(--white);
        }
        
        .status-rented {
            background-color: var(--text-light);
            color: var(--white);
        }
        
        /* Main Content */
        .container {
            max-width: 1200px;
        }
        
        /* Image Gallery */
        .gallery-container {
            margin-bottom: 2rem;
        }
        
        .main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .thumbnail-container {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .thumbnail {
            width: 100px;
            height: 75px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.2s ease;
        }
        
        .thumbnail:hover, .thumbnail.active {
            opacity: 1;
            border: 2px solid var(--primary-blue);
        }
        
        /* Property Details */
        .property-details-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }
        
        .description-section {
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        /* Amenities */
        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .amenity-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
        }
        
        /* Booking Card */
        .booking-card {
            position: sticky;
            top: 20px;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .price-display {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--primary-blue);
        }
        
        .price-period {
            font-size: 1rem;
            color: var(--text-light);
        }
        
        /* Reviews */
        .review-card {
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            background-color: var(--white);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .reviewer-name {
            font-weight: 600;
        }
        
        .review-date {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        /* Map */
        .map-container {
            height: 400px;
            width: 100%;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .property-hero {
                height: 50vh;
                min-height: 400px;
            }
            
            .main-image {
                height: 400px;
            }
        }
        
        @media (max-width: 768px) {
            .property-hero {
                height: 40vh;
                min-height: 300px;
            }
            
            .property-title {
                font-size: 2rem;
            }
            
            .main-image {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <div class="property-hero" style="background-image: url('<?php echo $property['main_image'] ? '../uploads/' . htmlspecialchars($property['main_image']) : 'assets/images/hero-bg.png'; ?>')">
        <div class="status-badge <?php echo $property['status'] == 1 ? 'status-available' : 'status-rented'; ?>">
            <?php echo $property['status'] == 1 ? 'Available' : 'Rented'; ?>
        </div>
        <div class="container hero-content">
            <h1 class="property-title"><?php echo htmlspecialchars($property['house_no']); ?></h1>
            <div class="property-location">
                <i class="fas fa-map-marker-alt"></i>
                <span><?php echo htmlspecialchars($property['location']); ?></span>
            </div>
            <div class="d-flex flex-wrap gap-3 text-white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-bed me-2"></i>
                    <span><?php echo $property['bedrooms']; ?> Beds</span>
                </div>
                <div class="d-flex align-items-center">
                    <i class="fas fa-bath me-2"></i>
                    <span><?php echo $property['bathrooms']; ?> Baths</span>
                </div>
                <?php if (!empty($property['size'])): ?>
                <div class="d-flex align-items-center">
                    <i class="fas fa-ruler-combined me-2"></i>
                    <span><?php echo htmlspecialchars($property['size']); ?> sq.ft</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Image Gallery -->
                <div class="gallery-container">
                    <img id="mainImage" src="<?php echo $property['main_image'] ? '../uploads/' . htmlspecialchars($property['main_image']) : 'assets/images/default-property.jpg'; ?>" 
                         class="main-image" alt="Property Image">
                    
                    <?php
                    // Get additional media
                    $sql = "SELECT * FROM house_media WHERE house_id = ? AND media_type = 'image' ORDER BY created_at DESC";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('i', $property_id);
                    $stmt->execute();
                    $media = $stmt->get_result();
                    
                    if ($media->num_rows > 0): ?>
                    <div class="thumbnail-container mt-3">
                        <img src="<?php echo $property['main_image'] ? '../uploads/' . htmlspecialchars($property['main_image']) : 'assets/images/default-property.jpg'; ?>" 
                             class="thumbnail active" 
                             onclick="changeImage(this, '<?php echo $property['main_image'] ? '../uploads/' . htmlspecialchars($property['main_image']) : 'assets/images/default-property.jpg'; ?>')">
                        
                        <?php while($media_row = $media->fetch_assoc()): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($media_row['file_path']); ?>" 
                                 class="thumbnail" 
                                 onclick="changeImage(this, '../uploads/<?php echo htmlspecialchars($media_row['file_path']); ?>')"
                                 alt="Property Image">
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Property Description -->
                <div class="property-details-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="section-title mb-0">About this property</h2>
                        <div class="price-display">
                            Ksh <?php echo number_format($property['price']); ?> 
                            <span class="price-period">/month</span>
                            <div class="favorite-icon d-inline-block ms-3" id="propertyFavorite" data-property-id="<?php echo $property['id']; ?>">
                                <?php 
                                $is_favorite = false;
                                if (is_logged_in()) {
                                    $fav_check = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND house_id = ?");
                                    $fav_check->bind_param('ii', $_SESSION['user_id'], $property['id']);
                                    $fav_check->execute();
                                    $is_favorite = $fav_check->get_result()->num_rows > 0;
                                }
                                ?>
                                <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart text-danger"></i>
                                <span class="ms-1"><?php echo $is_favorite ? 'Saved' : 'Save'; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="description-section">
                        <?php echo nl2br(htmlspecialchars($property['description'])); ?>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Amenities -->
                    <h3 class="section-title">What this place offers</h3>
                    <div class="amenities-grid">
                        <?php if($property['bedrooms'] > 0): ?>
                        <div class="amenity-item">
                            <i class="fas fa-bed text-primary"></i>
                            <span><?php echo $property['bedrooms']; ?> Bedrooms</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($property['bathrooms'] > 0): ?>
                        <div class="amenity-item">
                            <i class="fas fa-bath text-primary"></i>
                            <span><?php echo $property['bathrooms']; ?> Bathrooms</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($property['size'])): ?>
                        <div class="amenity-item">
                            <i class="fas fa-ruler-combined text-primary"></i>
                            <span><?php echo $property['size']; ?> sq.ft</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($property['parking'])): ?>
                        <div class="amenity-item">
                            <i class="fas fa-car text-primary"></i>
                            <span>Parking: <?php echo $property['parking']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($property['features'])): 
                            $features = explode(',', $property['features']);
                            foreach($features as $feature): 
                                if(trim($feature) !== ''): ?>
                                    <div class="amenity-item">
                                        <i class="fas fa-check-circle text-primary"></i>
                                        <span><?php echo htmlspecialchars(trim($feature)); ?></span>
                                    </div>
                        <?php   endif;
                            endforeach;
                        endif; ?>
                    </div>
                </div>

                <!-- Videos Section -->
                <?php
                $sql = "SELECT * FROM house_media WHERE house_id = ? AND media_type = 'video' ORDER BY created_at DESC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $property_id);
                $stmt->execute();
                $videos = $stmt->get_result();
                
                if ($videos->num_rows > 0): ?>
                <div class="property-details-card">
                    <h3 class="section-title">Video Tour</h3>
                    <div class="row">
                        <?php while($video = $videos->fetch_assoc()): ?>
                        <div class="col-md-6 mb-4">
                            <div class="video-container">
                                <video controls class="w-100" style="border-radius: var(--border-radius);">
                                    <source src="../uploads/<?php echo htmlspecialchars($video['file_path']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Location Map -->
                <div class="property-details-card">
                    <h3 class="section-title">Location</h3>
                    <div id="propertyMap" class="map-container"></div>
                    <div class="mt-3">
                        <a href="https://www.google.com/maps/dir/current+location/<?php echo $property['latitude']; ?>,<?php echo $property['longitude']; ?>" 
                           class="btn btn-outline-primary" 
                           target="_blank">
                            <i class="fas fa-directions me-2"></i> Get Directions
                        </a>
                    </div>
                </div>
            </div>

            <!-- Booking Card -->
            <div class="col-lg-4">
                <div class="booking-card">
                    <div class="price-display mb-3">
                        Ksh <?php echo number_format($property['price']); ?> 
                        <span class="price-period">/month</span>
                    </div>
                    
                    <form id="bookingForm" action="process_booking.php" method="POST">
                        <input type="hidden" name="house_id" value="<?php echo $property_id; ?>">
                        
                        <div class="mb-3">
                            <label for="startDate" class="form-label fw-bold">Move-in Date</label>
                            <input type="date" class="form-control form-control-lg" 
                                   id="startDate" name="start_date" required
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="leaseDuration" class="form-label fw-bold">Lease Duration</label>
                            <select class="form-select form-select-lg" id="leaseDuration" name="rental_period" required>
                                <option value="">Select Duration</option>
                                <option value="6">6 Months</option>
                                <option value="12" selected>12 Months</option>
                                <option value="24">24 Months</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="specialRequests" class="form-label fw-bold">Special Requests (Optional)</label>
                            <textarea class="form-control" id="specialRequests" name="special_requests" 
                                     rows="3" placeholder="Any special requirements or questions"></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 mb-3">
                            <button type="button" class="btn btn-outline-primary btn-lg py-3 mb-2" data-bs-toggle="modal" data-bs-target="#scheduleViewingModal">
                                <i class="fas fa-calendar-alt me-2"></i>Schedule Viewing
                            </button>
                            
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <button type="submit" class="btn btn-primary btn-lg py-3">
                                    <i class="fas fa-calendar-check me-2"></i>Book Now
                                </button>
                            <?php else: ?>
                                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                   class="btn btn-primary btn-lg py-3">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to Book
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center text-muted small">
                            You won't be charged yet
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="contact-owner">
                        <h5 class="mb-3">Contact Owner</h5>
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <i class="fas fa-user-circle fa-3x text-primary"></i>
                            </div>
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($property['owner_name'] ?? 'Property Owner'); ?></h6>
                                <?php if (!empty($property['owner_phone'])): ?>
                                    <div class="text-muted">
                                        <i class="fas fa-phone-alt me-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($property['owner_phone']); ?>">
                                            <?php echo htmlspecialchars($property['owner_phone']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#contactAgentModal">
                            <i class="fas fa-envelope me-2"></i>Send Message
                        </button>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="share-property">
                        <h5 class="mb-3">Share this property</h5>
                        <?php 
                        $share_url = urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
                        $share_title = urlencode($property['house_no'] . ' - ' . $property['location']);
                        ?>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" 
                               class="btn btn-sm btn-outline-primary rounded-circle" 
                               target="_blank" 
                               rel="noopener noreferrer">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>" 
                               class="btn btn-sm btn-outline-primary rounded-circle" 
                               target="_blank" 
                               rel="noopener noreferrer">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="https://wa.me/?text=<?php echo $share_title . ' ' . $share_url; ?>" 
                               class="btn btn-sm btn-outline-success rounded-circle" 
                               target="_blank" 
                               rel="noopener noreferrer">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-secondary rounded-circle" onclick="copyToClipboard()">
                                <i class="fas fa-link"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <!-- Toast Notifications -->
    <div class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1100; margin-top: 20px;">
        <!-- Success Toast -->
        <div id="viewingSuccessToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
            <div class="toast-header bg-success text-white">
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body bg-white" id="toastMessage"></div>
        </div>
        
        <!-- Error Toast -->
        <div id="viewingErrorToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
            <div class="toast-header bg-danger text-white">
                <strong class="me-auto">Error</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body bg-white" id="errorToastMessage"></div>
        </div>
    </div>
    
    <!-- Schedule Viewing Modal -->
    <div class="modal fade" id="scheduleViewingModal" tabindex="-1" aria-labelledby="scheduleViewingModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleViewingModalLabel">Schedule a Viewing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="viewingForm" action="process_viewing.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="house_id" value="<?php echo $property_id; ?>">
                        
                        <div class="mb-3">
                            <label for="viewingDate" class="form-label fw-bold">Preferred Date</label>
                            <input type="date" class="form-control" id="viewingDate" name="viewing_date" required
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="viewingTime" class="form-label fw-bold">Preferred Time</label>
                            <select class="form-select" id="viewingTime" name="viewing_time" required>
                                <option value="">Select Time</option>
                                <option value="09:00">9:00 AM</option>
                                <option value="10:00">10:00 AM</option>
                                <option value="11:00">11:00 AM</option>
                                <option value="12:00">12:00 PM</option>
                                <option value="13:00">1:00 PM</option>
                                <option value="14:00">2:00 PM</option>
                                <option value="15:00">3:00 PM</option>
                                <option value="16:00">4:00 PM</option>
                                <option value="17:00">5:00 PM</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contactNumber" class="form-label fw-bold">Contact Number</label>
                            <input type="tel" class="form-control" id="contactNumber" name="contact_number" 
                                   placeholder="Your phone number" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="viewingNotes" class="form-label fw-bold">Additional Notes (Optional)</label>
                            <textarea class="form-control" id="viewingNotes" name="viewing_notes" 
                                     rows="3" placeholder="Any special requirements or questions"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-plus me-2"></i>Schedule Viewing
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Handle viewing form submission
    document.getElementById('viewingForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Scheduling...';
            
            const response = await fetch('process_viewing.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show success message
                const toast = new bootstrap.Toast(document.getElementById('viewingSuccessToast'));
                document.getElementById('toastMessage').textContent = data.message;
                toast.show();
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('scheduleViewingModal'));
                modal.hide();
                
                // Reset form
                this.reset();
            } else {
                throw new Error(data.message || 'Failed to schedule viewing');
            }
        } catch (error) {
            console.error('Error:', error);
            const toast = new bootstrap.Toast(document.getElementById('viewingErrorToast'));
            document.getElementById('errorToastMessage').textContent = error.message;
            toast.show();
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
    
    // Handle favorite toggle on property page
    document.getElementById('propertyFavorite').addEventListener('click', async function() {
        <?php if (!is_logged_in()): ?>
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname + '?id=<?php echo $property_id; ?>');
            return;
        <?php endif; ?>
        
        const icon = this.querySelector('i');
        const text = this.querySelector('span');
        const propertyId = this.dataset.propertyId;
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
                if (data.is_favorite) {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    text.textContent = 'Saved';
                    showToast('Property added to favorites!');
                } else {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    text.textContent = 'Save';
                    showToast('Property removed from favorites');
                }
            } else {
                showToast(data.message || 'Error updating favorites', true);
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', true);
        }
    });
    
    function showToast(message, isError = false) {
        // Create toast element if it doesn't exist
        let toastEl = document.getElementById('favoriteToast');
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.id = 'favoriteToast';
            toastEl.className = 'position-fixed top-0 start-50 translate-middle-x mt-5';
            toastEl.style.zIndex = '1100';
            toastEl.innerHTML = `
                <div class="toast align-items-center" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            document.body.appendChild(toastEl);
        } else {
            toastEl.querySelector('.toast-body').textContent = message;
        }
        
        // Toggle error class
        const toast = toastEl.querySelector('.toast');
        toast.classList.remove('success', 'error');
        toast.classList.add(isError ? 'error' : 'success');
        
        // Show toast
        const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 3000 });
        bsToast.show();
    }
    </script>
    
    <!-- Contact Owner Modal -->
    <div class="modal fade" id="contactAgentModal" tabindex="-1" aria-labelledby="contactAgentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactAgentModalLabel">Contact Owner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="contactForm">
                        <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">
                        <input type="hidden" name="owner_id" value="<?php echo $property['landlord_id']; ?>">
                        
                        <div class="mb-3">
                            <label for="contactName" class="form-label">Your Name</label>
                            <input type="text" class="form-control" id="contactName" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contactEmail" class="form-label">Your Email</label>
                            <input type="email" class="form-control" id="contactEmail" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contactPhone" class="form-label">Your Phone Number</label>
                            <input type="tel" class="form-control" id="contactPhone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="contactMessage" class="form-label">Message</label>
                            <textarea class="form-control" id="contactMessage" name="message" rows="4" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD06CBLLmOHLrVccQv7t3x72cG4Rj8bcOQ&callback=initPropertyMap" async defer></script>
    <script>
        // Change main image when thumbnail is clicked
        function changeImage(element, newSrc) {
            document.getElementById('mainImage').src = newSrc;
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            element.classList.add('active');
        }
        
        // Initialize Google Map
        function initPropertyMap() {
            const propertyLocation = { 
                lat: <?php echo $property['latitude']; ?>, 
                lng: <?php echo $property['longitude']; ?> 
            };

            const map = new google.maps.Map(document.getElementById('propertyMap'), {
                center: propertyLocation,
                zoom: 15,
                mapTypeId: 'roadmap',
                styles: [
                    {
                        featureType: 'poi',
                        elementType: 'labels',
                        stylers: [{ visibility: 'off' }]
                    }
                ]
            });

            // Add marker for the property
            new google.maps.Marker({
                position: propertyLocation,
                map: map,
                icon: {
                    url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                    scaledSize: new google.maps.Size(40, 40)
                }
            });
        }
        
        // Copy to clipboard function
        function copyToClipboard() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                alert('Link copied to clipboard!');
            });
        }
        
        // Handle contact form submission
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // AJAX form submission would go here
            alert('Message sent successfully!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('contactAgentModal'));
            modal.hide();
        });
    </script>
</body>
</html>