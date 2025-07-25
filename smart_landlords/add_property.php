<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log all POST data
    error_log('Received POST data: ' . print_r($_POST, true));
    
    $house_no = $_POST['house_no'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $price = $_POST['price'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;
    $location = $_POST['location'] ?? '';
    $bedrooms = $_POST['bedrooms'] ?? '';
    $bathrooms = $_POST['bathrooms'] ?? '';
    $area = $_POST['area'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $address = $_POST['address'] ?? '';

    // Debug: Log location data
    error_log('Location data: lat=' . $latitude . ', lng=' . $longitude);

    // Handle main image
    if (!isset($_FILES['main_image']) || $_FILES['main_image']['error'] !== 0) {
        echo "<script>alert('Main image is required'); window.history.back();</script>";
        exit;
    }

    $main_image = time() . '_main_' . $_FILES['main_image']['name'];
    $upload_path = '../uploads/' . $main_image;

    if (!move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_path)) {
        echo "<script>alert('Failed to upload main image'); window.history.back();</script>";
        exit;
    }

    // Insert house record
    $sql = "INSERT INTO houses (house_no, description, price, location, category_id, status, main_image, landlord_id, bedrooms, bathrooms, area, latitude, longitude, address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Database error: ' . $conn->error);
        echo "<script>alert('Database error: " . $conn->error . "'); window.history.back();</script>";
        exit;
    }

    // Debug: Log bind parameters
    error_log('Binding parameters: lat=' . $latitude . ', lng=' . $longitude);
    
    // Bind parameters in the correct order matching the table structure
    $stmt->bind_param("ssdsiisiiiddds", 
        $house_no,
        $description,
        $price,
        $location,
        $category_id,
        $status,
        $main_image,
        $_SESSION['user_id'],
        $bedrooms,
        $bathrooms,
        $area,
        $latitude,
        $longitude,
        $address
    );
    
    if (!$stmt->execute()) {
        error_log('Error saving property: ' . $stmt->error);
        echo "<script>alert('Error saving property: " . $stmt->error . "'); window.history.back();</script>";
        exit;
    }
    
    $house_id = $stmt->insert_id;
    
    // Handle additional media
    if (isset($_FILES['additional_media']) && is_array($_FILES['additional_media']['tmp_name'])) {
        $media = $_FILES['additional_media'];
        
        for ($i = 0; $i < count($media['tmp_name']); $i++) {
            if ($media['tmp_name'][$i] && $media['error'][$i] === 0) {
                $file_name = time() . '_' . $media['name'][$i];
                $file_path = '../uploads/' . $file_name;
                
                if (move_uploaded_file($media['tmp_name'][$i], $file_path)) {
                    $file_type = $media['type'][$i];
                    $media_type = strpos($file_type, 'image/') !== false ? 'image' : 'video';
                    
                    $sql = "INSERT INTO house_media (house_id, media_type, file_path) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        continue;
                    }
                    
                    $stmt->bind_param("iss", $house_id, $media_type, $file_name);
                    $stmt->execute();
                }
            }
        }
    }
    
    header('Location: properties.php?msg=Property added successfully');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Property - Smart Landlords</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h4>Add New Property</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="propertyForm">
                            <div class="form-group mb-3">
                                <label for="property_house_no" class="form-label">Property Name</label>
                                <input type="text" class="form-control" id="property_house_no" name="house_no" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                <small class="form-text text-muted">Use the toolbar above to format your text with bullets, bold, underline, and more.</small>
                            </div>

                            <div class="form-group mb-3">
                                <label for="property_category" class="form-label">Category</label>
                                <select class="form-select" id="property_category" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php
                                    $stmt = $conn->query("SELECT id, name FROM categories ORDER BY name");
                                    while ($row = $stmt->fetch_assoc()) {
                                        echo "<option value='" . $row['id'] . "'>" . $row['name'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label for="property_price" class="form-label">Price</label>
                                <input type="number" class="form-control" id="property_price" name="price" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="property_location" class="form-label">Location</label>
                                <div class="location-container">
                                    <div class="mb-2">
                                        <textarea class="form-control" id="property_location" name="location" rows="2" required></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="address" placeholder="Enter address or use map" readonly>
                                            <button class="btn btn-outline-secondary" type="button" onclick="getCurrentLocation()">
                                                <i class="fas fa-location-arrow"></i> Use Current Location
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <div id="map" style="height: 300px; border: 1px solid #ddd; border-radius: 4px;"></div>
                                    </div>
                                    <input type="hidden" id="latitude" name="latitude" required>
                                    <input type="hidden" id="longitude" name="longitude" required>
                                </div>
                            </div>

                            <!-- Main Image -->
                            <div class="form-group mb-3">
                                <label for="property_main_image" class="form-label">Main Image (Required)</label>
                                <input type="file" class="form-control" id="property_main_image" name="main_image" accept="image/*" required>
                                <small class="text-muted">Upload the main image for the property</small>
                            </div>

                            <!-- Additional Media -->
                            <div class="form-group mb-3">
                                <label for="property_additional_media" class="form-label">Additional Images/Videos (Optional)</label>
                                <input type="file" class="form-control" id="property_additional_media" name="additional_media[]" accept="image/*,video/*" multiple>
                                <small class="text-muted">You can upload multiple images and videos</small>
                            </div>

                            <div class="form-group mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="property_status" name="status" checked>
                                    <label class="form-check-label" for="property_status">
                                        Active Status
                                    </label>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="property_bedrooms" class="form-label">Bedrooms</label>
                                <input type="number" class="form-control" id="property_bedrooms" name="bedrooms" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="property_bathrooms" class="form-label">Bathrooms</label>
                                <input type="number" class="form-control" id="property_bathrooms" name="bathrooms" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="property_area" class="form-label">Area (sqm)</label>
                                <input type="number" class="form-control" id="property_area" name="area" required>
                            </div>

                            <div class="d-flex justify-content-end">
                                <a href="properties.php" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Add Property</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/8zvwq78ba3v0q7hgjebfye6sr7blxj3jyeaggzyiph4c41hx/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#description',
            plugins: 'lists link image table code help wordcount',
            toolbar: 'undo redo | formatselect | bold italic underline | \
                     alignleft aligncenter alignright | \
                     bullist numlist outdent indent | link image | \
                     removeformat | help',
            menubar: false,
            height: 300,
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
            setup: function(editor) {
                editor.on('change', function() {
                    editor.save();
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD06CBLLmOHLrVccQv7t3x72cG4Rj8bcOQ&libraries=places"></script>
    <script>
        // Initialize map
        let map;
        let marker;
        let currentLocationMarker;
        
        function initMap() {
            // Set initial location to Nairobi
            const initialLocation = { lat: -1.2833, lng: 36.8167 };
            
            // Initialize map
            map = new google.maps.Map(document.getElementById('map'), {
                center: initialLocation,
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

            // Add draggable marker
            marker = new google.maps.Marker({
                position: initialLocation,
                map: map,
                draggable: true,
                icon: {
                    url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                    scaledSize: new google.maps.Size(30, 30)
                }
            });

            // Add click event to map
            map.addListener('click', function(event) {
                marker.setPosition(event.latLng);
                updateLocation(event.latLng);
            });

            // Add drag event to marker
            marker.addListener('dragend', function(event) {
                updateLocation(event.latLng);
            });

            // Add place search
            const input = document.getElementById('address');
            const autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (place.geometry) {
                    map.setCenter(place.geometry.location);
                    marker.setPosition(place.geometry.location);
                    updateLocation(place.geometry.location);
                }
            });

            // Add current location marker
            currentLocationMarker = new google.maps.Marker({
                position: initialLocation,
                map: map,
                icon: {
                    url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                    scaledSize: new google.maps.Size(20, 20)
                }
            });
        }

        function updateLocation(latLng) {
            // Create LatLng object if we have a plain object
            if (typeof latLng === 'object' && !latLng instanceof google.maps.LatLng) {
                latLng = new google.maps.LatLng(latLng.lat, latLng.lng);
            }

            // Ensure we have a valid LatLng object
            if (!latLng || !(latLng instanceof google.maps.LatLng)) {
                console.error('Invalid LatLng object:', latLng);
                return;
            }

            // Update hidden input fields
            const lat = latLng.lat();
            const lng = latLng.lng();
            
            document.getElementById('latitude').value = lat.toFixed(6);
            document.getElementById('longitude').value = lng.toFixed(6);
            
            // Update visible address fields
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ 'location': latLng }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    document.getElementById('address').value = results[0].formatted_address;
                    document.getElementById('property_location').value = results[0].formatted_address;
                }
            });
        }

        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const pos = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        
                        // Update map center and marker
                        map.setCenter(pos);
                        marker.setPosition(pos);
                        currentLocationMarker.setPosition(pos);
                        
                        // Update location fields
                        updateLocation(pos);
                    },
                    function() {
                        handleLocationError(true);
                    }
                );
            } else {
                handleLocationError(false);
            }
        }

        function handleLocationError(browserHasGeolocation) {
            alert(browserHasGeolocation ? 
                'Error: The Geolocation service failed.' :
                'Error: Your browser doesn\'t support geolocation.');
        }

        // Initialize map when page loads
        window.addEventListener('load', initMap);

        // Form submission handling
        document.getElementById('propertyForm').addEventListener('submit', function(e) {
            // Debug: Show current marker position
            const markerPosition = marker.getPosition();
            if (!markerPosition) {
                e.preventDefault();
                alert('Please select a location on the map first');
                return;
            }
            
            // Debug: Show coordinates
            console.log('Submitting with coordinates:', markerPosition.lat(), markerPosition.lng());
            
            // Update hidden fields with current marker position
            document.getElementById('latitude').value = markerPosition.lat().toFixed(6);
            document.getElementById('longitude').value = markerPosition.lng().toFixed(6);
            
            // Debug: Show form values
            console.log('Form values:', {
                latitude: document.getElementById('latitude').value,
                longitude: document.getElementById('longitude').value,
                location: document.getElementById('property_location').value
            });
            
            // Update the location field with formatted address
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({
                'location': markerPosition
            }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    document.getElementById('property_location').value = results[0].formatted_address;
                }
            });
        });
    </script>
    <script>
        // Initialize map
        let map;
        let marker;
        let currentLocationMarker;
        
        function initMap() {
            // Set initial location to Nairobi
            const initialLocation = { lat: -1.2833, lng: 36.8167 };
            
            // Initialize map
            map = new google.maps.Map(document.getElementById('map'), {
                center: initialLocation,
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

            // Add draggable marker
            marker = new google.maps.Marker({
                position: initialLocation,
                map: map,
                draggable: true,
                icon: {
                    url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                    scaledSize: new google.maps.Size(30, 30)
                }
            });

            // Add click event to map
            map.addListener('click', function(event) {
                marker.setPosition(event.latLng);
                updateLocation(event.latLng);
            });

            // Add drag event to marker
            marker.addListener('dragend', function(event) {
                updateLocation(event.latLng);
            });

            // Add place search
            const input = document.getElementById('address');
            const autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (place.geometry) {
                    map.setCenter(place.geometry.location);
                    marker.setPosition(place.geometry.location);
                    updateLocation(place.geometry.location);
                }
            });

            // Add current location marker
            currentLocationMarker = new google.maps.Marker({
                position: initialLocation,
                map: map,
                icon: {
                    url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                    scaledSize: new google.maps.Size(20, 20)
                }
            });
        }

        function updateLocation(latLng) {
            document.getElementById('latitude').value = latLng.lat().toFixed(6);
            document.getElementById('longitude').value = latLng.lng().toFixed(6);
            
            // Reverse geocode to get address
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ 'location': latLng }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    document.getElementById('address').value = results[0].formatted_address;
                    document.getElementById('property_location').value = results[0].formatted_address;
                }
            });
        }

        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const pos = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        
                        // Update map center and marker
                        map.setCenter(pos);
                        marker.setPosition(pos);
                        currentLocationMarker.setPosition(pos);
                        
                        // Update location fields
                        updateLocation(pos);
                    },
                    function() {
                        handleLocationError(true);
                    }
                );
            } else {
                handleLocationError(false);
            }
        }

        function handleLocationError(browserHasGeolocation) {
            alert(browserHasGeolocation ? 
                'Error: The Geolocation service failed.' :
                'Error: Your browser doesn\'t support geolocation.');
        }

        // Initialize map when page loads
        window.addEventListener('load', initMap);
    </script>

    <script>
        document.getElementById('property_main_image').addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                document.getElementById('property_main_image').nextElementSibling.textContent = 
                    `Selected file: ${files[0].name}`;
            }
        });

        document.getElementById('property_additional_media').addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                document.getElementById('property_additional_media').nextElementSibling.textContent = 
                    `Selected ${files.length} files`;
            }
        });
    </script>
</body>
</html>
