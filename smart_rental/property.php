<?php
require_once '../config/db.php';

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
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/property.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section with Main Image -->
    <div class="property-hero" style="background-image: url('<?php echo $property['main_image'] ? '../uploads/' . htmlspecialchars($property['main_image']) : 'assets/images/default-property.jpg'; ?>')">
        <div class="status-badge <?php echo $property['status'] == 1 ? 'status-available' : 'status-rented'; ?>">
            <?php echo $property['status'] == 1 ? 'Available' : 'Rented'; ?>
        </div>
        <div class="hero-content">
            <h1 class="property-title"><?php echo htmlspecialchars($property['house_no']); ?></h1>
            <div class="property-location">
                <i class="fas fa-map-marker-alt"></i>
                <span><?php echo htmlspecialchars($property['location']); ?></span>
            </div>
            <div class="d-flex flex-wrap gap-3">
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
                    <h2 class="section-title">Property Details</h2>
                    <div class="description-section">
                        <?php echo nl2br(htmlspecialchars($property['description'])); ?>
                    </div>
                    
                    <!-- Amenities -->
                    <h3 class="section-title">Amenities</h3>
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
                                <video controls class="w-100" style="border-radius: 8px;">
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
                </div>
            </div>
                        ?>
                    </div>
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
                    
                    <!-- Navigation Button -->
                    <div class="mt-3">
                        <a href="https://www.google.com/maps/dir/current+location/<?php echo $property['latitude']; ?>,<?php echo $property['longitude']; ?>" 
                           class="btn btn-outline-success w-100" 
                           target="_blank" 
                           onclick="return confirm('Open Google Maps for directions?')">
                            <i class="fas fa-car"></i> Get Directions
                        </a>
                        </div>
                        
                        <div class="mb-3">
                            <label for="leaseDuration" class="form-label">Lease Duration</label>
                            <select class="form-select form-select-lg" id="leaseDuration" required>
                                <option value="">Select Duration</option>
                                <option value="6">6 Months</option>
                                <option value="12">12 Months</option>
                                <option value="24">24 Months</option>
                            </select>
                        </div>
                        
                        <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg btn-book">
                                <i class="fas fa-calendar-check me-2"></i>Book Now
                            </button>
                        </div>
                    </form>
                    
                    <div class="property-agent mt-4 pt-3 border-top">
                        <h5 class="mb-3">Contact Agent</h5>
                        <div class="d-flex align-items-center mb-3">
                            <div class="agent-avatar me-3">
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
                                <?php elseif (!empty($property['agent_phone'])): ?>
                                    <div class="text-muted">
                                        <i class="fas fa-phone-alt me-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($property['agent_phone']); ?>">
                                            <?php echo htmlspecialchars($property['agent_phone']); ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted">
                                        <i class="fas fa-phone-alt me-2"></i>
                                        <span>No contact number available</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#contactAgentModal">
                            <i class="fas fa-envelope me-2"></i>Send Message
                        </button>
                    </div>
                    
                    <div class="property-share mt-4 pt-3 border-top">
                        <h6 class="mb-3">Share this property</h6>
                        <?php 
                        $share_url = urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
                        $share_title = urlencode($property['house_no'] . ' - ' . $property['location']);
                        ?>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" 
                               class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>" 
                               class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="https://wa.me/?text=<?php echo $share_title . ' ' . $share_url; ?>" 
                               class="btn btn-sm btn-outline-success" target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard()">
                                <i class="fas fa-link"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Features List -->
                <?php if (!empty($property['features'])): ?>
                <div class="property-details-card mt-4">
                    <h5 class="section-title">Features</h5>
                    <ul class="list-unstyled">
                        <?php 
                        $features = explode(',', $property['features']);
                        foreach($features as $feature): 
                            if(trim($feature) !== ''): ?>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <?php echo htmlspecialchars(trim($feature)); ?>
                                </li>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Get Directions -->
                <div class="property-details-card mt-4">
                    <h5 class="section-title">Get Directions</h5>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?php 
                        echo urlencode($property['latitude'] . ',' . $property['longitude']); 
                    ?>" 
                       class="btn btn-outline-primary w-100" 
                       target="_blank">
                        <i class="fas fa-directions me-2"></i>Open in Google Maps
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD06CBLLmOHLrVccQv7t3x72cG4Rj8bcOQ"></script>
    <script>
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
            const marker = new google.maps.Marker({
                position: propertyLocation,
                map: map,
                icon: {
                    url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                    scaledSize: new google.maps.Size(30, 30)
                }
            });

            // Add info window
            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div class="info-window">
                        <h5><?php echo htmlspecialchars($property['house_no']); ?></h5>
                        <p><?php echo htmlspecialchars($property['location']); ?></p>
                        <p>Price: <?php echo number_format($property['price']); ?></p>
                    </div>
                `
            });

            marker.addListener('click', function() {
                infoWindow.open(map, marker);
            });
        }

        window.addEventListener('load', initPropertyMap);
    </script>
    <script src="assets/js/property.js"></script>
</body>
</html>
