<?php
require_once '../config/db.php';
require_once '../config/auth.php';

$property_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$property_id) {
    header('Location: browse.php');
    exit;
}

$stmt = $conn->prepare("SELECT h.*, c.name as category_name 
                       FROM houses h 
                       LEFT JOIN categories c ON h.category_id = c.id 
                       WHERE h.id = ?");
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
    <title><?php echo htmlspecialchars($property['house_no']); ?> - Property Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <!-- Property Images -->
            <div class="col-md-6">
                <div id="propertyCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <img src="../uploads/<?php echo htmlspecialchars($property['main_image']); ?>" 
                                 class="d-block w-100" alt="Main Property Image">
                        </div>
                        <?php
                        $stmt = $conn->prepare("SELECT * FROM house_media WHERE house_id = ?");
                        $stmt->bind_param('i', $property_id);
                        $stmt->execute();
                        $media = $stmt->get_result();
                        
                        while ($media_item = $media->fetch_assoc()) {
                            echo '<div class="carousel-item">
                                  <img src="../uploads/' . htmlspecialchars($media_item['file_path']) . '" 
                                       class="d-block w-100" alt="Additional Image">
                                  </div>';
                        }
                        ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#propertyCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#propertyCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>

            <!-- Property Details -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($property['house_no']); ?></h3>
                        <h6 class="card-subtitle mb-3 text-muted"><?php echo htmlspecialchars($property['category_name']); ?></h6>
                        
                        <div class="property-info">
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($property['address']); ?></span>
                            </div>
                            <div class="info-item">
                                                                                <i class="fas fa-money-bill"></i>
                                <span>KSh <?php echo number_format($property['price']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-bed"></i>
                                <span><?php echo $property['bedrooms']; ?> Bedrooms</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-bath"></i>
                                <span><?php echo $property['bathrooms']; ?> Bathrooms</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-ruler-combined"></i>
                                <span><?php echo $property['area']; ?> sqm</span>
                            </div>
                        </div>

                        <p class="card-text mt-4"><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>

                        <div class="mt-4">
                            <a href="property.php?id=<?php echo $property_id; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> View Full Details
                            </a>
                            <a href="#" class="btn btn-outline-primary" onclick="addToFavorites(<?php echo $property_id; ?>)">
                                <i class="fas fa-heart"></i> Add to Favorites
                            </a>
                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $property['latitude']; ?>,<?php echo $property['longitude']; ?>" 
                               class="btn btn-outline-success" 
                               target="_blank" 
                               onclick="return confirm('Open Google Maps for directions?')">
                                <i class="fas fa-car"></i> Get Directions
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Map -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Location</h4>
                        <div id="propertyMap" style="height: 400px; border: 1px solid #ddd; border-radius: 4px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY"></script>
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
                        <p><?php echo htmlspecialchars($property['address']); ?></p>
                        <p>Price: KSh <?php echo number_format($property['price']); ?></p>
                    </div>
                `
            });

            marker.addListener('click', function() {
                infoWindow.open(map, marker);
            });
        }

        window.addEventListener('load', initPropertyMap);
    </script>
</body>
</html>
