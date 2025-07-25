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
    $total_units = intval($_POST['total_units'] ?? 1);
    $available_units = intval($_POST['available_units'] ?? 1);
    
    // Server-side validation for available units
    if ($available_units > $total_units) {
        echo "<script>alert('Available units cannot be greater than total units'); window.history.back();</script>";
        exit;
    }
    
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
    $sql = "INSERT INTO houses (house_no, description, price, location, category_id, status, main_image, landlord_id, bedrooms, bathrooms, area, total_units, available_units, latitude, longitude, address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Database error: ' . $conn->error);
        echo "<script>alert('Database error: " . $conn->error . "'); window.history.back();</script>";
        exit;
    }

    // Debug: Log bind parameters
    error_log('Binding parameters: lat=' . $latitude . ', lng=' . $longitude);
    
    // Debug: Log all variables being bound
    error_log('Binding values: ' . print_r([
        'house_no' => $house_no,
        'description' => substr($description, 0, 50) . '...',
        'price' => $price,
        'location' => $location,
        'category_id' => $category_id,
        'status' => $status,
        'main_image' => $main_image,
        'landlord_id' => $_SESSION['user_id'],
        'bedrooms' => $bedrooms,
        'bathrooms' => $bathrooms,
        'area' => $area,
        'total_units' => $total_units,
        'available_units' => $available_units,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'address' => $address
    ], true));

    // Bind parameters in the correct order matching the table structure
    $stmt->bind_param("ssdsiisiiiiiddds", 
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
        $total_units,
        $available_units,
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
            --box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Circular', -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, sans-serif;
            background-color: #f8f9fa;
            color: var(--text-dark);
        }
        
        .form-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            margin-bottom: 2rem;
        }
        
        .form-header h4 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            opacity: 0.9;
        }
        
        .form-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .form-section {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 0.75rem;
            color: var(--primary-blue);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.25rem rgba(25, 118, 210, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
        }
        
        .btn-outline-primary {
            color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-blue);
            color: white;
        }
        
        .map-container {
            height: 300px;
            width: 100%;
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid #dee2e6;
        }
        
        .file-upload-container {
            border: 2px dashed #dee2e6;
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: var(--white);
        }
        
        .file-upload-container:hover {
            border-color: var(--primary-blue);
            background-color: var(--light-blue);
        }
        
        .file-upload-icon {
            font-size: 2.5rem;
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }
        
        .file-upload-text {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .file-upload-hint {
            color: var(--text-light);
            font-size: 0.875rem;
        }
        
        .feature-badge {
            background-color: var(--light-blue);
            color: var(--primary-blue);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .feature-badge i {
            margin-right: 0.5rem;
        }
        
        .step-progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .step-progress::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #dee2e6;
            z-index: 1;
            transform: translateY(-50%);
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #dee2e6;
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }
        
        .step.active {
            background-color: var(--primary-blue);
            color: white;
        }
        
        .step.completed {
            background-color: var(--dark-blue);
            color: white;
        }
        
        .step-label {
            position: absolute;
            top: calc(100% + 0.5rem);
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            font-size: 0.875rem;
            color: var(--text-light);
        }
        
        .step.active .step-label,
        .step.completed .step-label {
            color: var(--text-dark);
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .step-progress {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
            
            .step-progress::before {
                display: none;
            }
            
            .step {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="form-card">
                    <!-- Form Header -->
                    <div class="form-header text-center">
                        <h4><i class="fas fa-home me-2"></i>Add New Property</h4>
                        <p>Fill in the details to list your property for rent</p>
                    </div>
                    
                    <!-- Progress Steps -->
                    <div class="form-section">
                        <div class="step-progress">
                            <div class="step active">
                                <span>1</span>
                                <span class="step-label">Basic Info</span>
                            </div>
                            <div class="step">
                                <span>2</span>
                                <span class="step-label">Details</span>
                            </div>
                            <div class="step">
                                <span>3</span>
                                <span class="step-label">Location</span>
                            </div>
                            <div class="step">
                                <span>4</span>
                                <span class="step-label">Media</span>
                            </div>
                            <div class="step">
                                <span>5</span>
                                <span class="step-label">Review</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Property Form -->
                    <form method="POST" enctype="multipart/form-data" id="propertyForm">
                        <!-- Basic Information Section -->
                        <div class="form-section">
                            <h5 class="section-title"><i class="fas fa-info-circle"></i> Basic Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="property_house_no" class="form-label">Property Name</label>
                                    <input type="text" class="form-control" id="property_house_no" name="house_no" required placeholder="e.g., Luxury Apartment">
                                </div>
                                
                                <div class="col-md-6 mb-3">
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
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required placeholder="Describe your property in detail..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Property Details Section -->
                        <div class="form-section">
                            <h5 class="section-title"><i class="fas fa-list-ul"></i> Property Details</h5>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="property_price" class="form-label">Monthly Price (Ksh)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Ksh</span>
                                        <input type="number" class="form-control" id="property_price" name="price" required placeholder="e.g., 50000">
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="property_bedrooms" class="form-label">Bedrooms</label>
                                    <input type="number" class="form-control" id="property_bedrooms" name="bedrooms" required placeholder="e.g., 3">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="property_bathrooms" class="form-label">Bathrooms</label>
                                    <input type="number" class="form-control" id="property_bathrooms" name="bathrooms" required placeholder="e.g., 2">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="property_area" class="form-label">Area (sqm)</label>
                                    <input type="number" class="form-control" id="property_area" name="area" required placeholder="e.g., 120">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="property_total_units" class="form-label">Total Units</label>
                                    <input type="number" class="form-control" id="property_total_units" name="total_units" required min="1" value="1">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="property_available_units" class="form-label">Available Units</label>
                                    <input type="number" class="form-control" id="property_available_units" name="available_units" required min="0" value="1" onchange="validateUnits()">
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="property_status" name="status" checked>
                                        <label class="form-check-label" for="property_status">
                                            Available for Rent
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Location Section -->
                        <div class="form-section">
                            <h5 class="section-title"><i class="fas fa-map-marker-alt"></i> Location Details</h5>
                            
                            <div class="mb-3">
                                <label for="property_location" class="form-label">Location Description</label>
                                <textarea class="form-control" id="property_location" name="location" rows="2" required placeholder="Describe the location (e.g., Near ABC Mall, Westlands)"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Pin Location on Map</label>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" id="search_address" placeholder="Search address or drag the pin on the map" readonly>
                                    <button class="btn btn-outline-primary" type="button" id="currentLocationBtn">
                                        <i class="fas fa-location-arrow"></i> Current Location
                                    </button>
                                </div>
                                <div id="map" class="map-container"></div>
                                <input type="hidden" id="latitude" name="latitude" required>
                                <input type="hidden" id="longitude" name="longitude" required>
                                <input type="hidden" id="address" name="address">
                            </div>
                        </div>
                        
                        <!-- Media Section -->
                        <div class="form-section">
                            <h5 class="section-title"><i class="fas fa-images"></i> Property Media</h5>
                            
                            <div class="mb-4">
                                <label class="form-label">Main Image (Featured Photo)</label>
                                <div class="file-upload-container" onclick="document.getElementById('property_main_image').click()">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="file-upload-text">Click to upload main image</div>
                                    <div class="file-upload-hint">High quality photo that represents your property (required)</div>
                                    <input type="file" class="d-none" id="property_main_image" name="main_image" accept="image/*" required>
                                </div>
                                <div id="mainImagePreview" class="mt-2 text-center"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Additional Photos & Videos</label>
                                <div class="file-upload-container" onclick="document.getElementById('property_additional_media').click()">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-images"></i>
                                    </div>
                                    <div class="file-upload-text">Click to upload additional media</div>
                                    <div class="file-upload-hint">Upload multiple images or videos (optional)</div>
                                    <input type="file" class="d-none" id="property_additional_media" name="additional_media[]" accept="image/*,video/*" multiple>
                                </div>
                                <div id="additionalMediaPreview" class="mt-3 row g-2"></div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-section bg-light">
                            <div class="d-flex justify-content-between">
                                <a href="properties.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Submit Property
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function validateUnits() {
            const totalUnits = parseInt(document.getElementById('property_total_units').value) || 0;
            const availableUnits = parseInt(document.getElementById('property_available_units').value) || 0;
            
            if (availableUnits > totalUnits) {
                alert('Available units cannot be greater than total units');
                document.getElementById('property_available_units').value = totalUnits;
                return false;
            }
            return true;
        }
        
        // Also validate on form submission
        document.getElementById('propertyForm').addEventListener('submit', function(e) {
            if (!validateUnits()) {
                e.preventDefault();
                return false;
            }
            return true;
        });
        
        // Update max value of available units when total units changes
        document.getElementById('property_total_units').addEventListener('change', function() {
            const totalUnits = parseInt(this.value) || 1;
            const availableInput = document.getElementById('property_available_units');
            const availableUnits = parseInt(availableInput.value) || 0;
            
            availableInput.max = totalUnits;
            if (availableUnits > totalUnits) {
                availableInput.value = totalUnits;
            }
        });
    </script>
    
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
        // Initialize map variables in global scope
        let map = null;
        let marker = null;
        let currentLocationMarker = null;
        
        function initMap() {
            console.log('Initializing map...');
            
            // Set initial location to Nairobi
            const initialLocation = { lat: -1.2833, lng: 36.8167 };
            
            try {
                // Initialize map
                window.map = new google.maps.Map(document.getElementById('map'), {
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
                
                console.log('Map initialized:', window.map);

                // Create draggable marker if it doesn't exist
                if (!window.marker) {
                    window.marker = new google.maps.Marker({
                        position: initialLocation,
                        map: window.map,
                        draggable: true,
                        icon: {
                            url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                            scaledSize: new google.maps.Size(30, 30)
                        }
                    });
                    
                    // Add drag event to marker
                    window.marker.addListener('dragend', function(event) {
                        updateLocation(event.latLng);
                    });
                }

                // Create current location marker if it doesn't exist
                if (!window.currentLocationMarker) {
                    window.currentLocationMarker = new google.maps.Marker({
                        position: initialLocation,
                        map: window.map,
                        icon: {
                            url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                            scaledSize: new google.maps.Size(20, 20)
                        }
                    });
                }

                // Add click event to map
                window.map.addListener('click', function(event) {
                    if (window.marker) {
                        window.marker.setPosition(event.latLng);
                        updateLocation(event.latLng);
                    }
                });

                // Initialize place search
                const input = document.getElementById('search_address');
                if (input) {
                    const autocomplete = new google.maps.places.Autocomplete(input);
                    autocomplete.addListener('place_changed', function() {
                        const place = autocomplete.getPlace();
                        if (place.geometry && window.marker) {
                            window.map.setCenter(place.geometry.location);
                            window.marker.setPosition(place.geometry.location);
                            updateLocation(place.geometry.location);
                        }
                    });
                }
                
                console.log('Map initialization complete');
                
            } catch (error) {
                console.error('Error initializing map:', error);
            }
            
            return window.map;
        }

        function updateLocation(latLng) {
            console.log('updateLocation called with:', latLng);
            
            if (!latLng || (typeof latLng.lat !== 'function') || (typeof latLng.lng !== 'function')) {
                console.error('Invalid location data:', latLng);
                return;
            }
            
            try {
                const lat = latLng.lat();
                const lng = latLng.lng();
                
                console.log('Updating location to:', lat, lng);
                
                // Update hidden fields
                document.getElementById('latitude').value = lat.toFixed(6);
                document.getElementById('longitude').value = lng.toFixed(6);
                
                // Update the visible address input
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ 'location': latLng }, function(results, status) {
                    console.log('Geocoding status:', status, 'Results:', results);
                    
                    if (status === 'OK' && results[0]) {
                        const address = results[0].formatted_address;
                        console.log('Setting address to:', address);
                        
                        // Update the hidden address field
                        document.getElementById('address').value = address;
                        
                        // Update the location description textarea
                        const locationTextarea = document.getElementById('property_location');
                        if (locationTextarea) {
                            locationTextarea.value = address;
                            console.log('Updated property_location with:', address);
                        }
                        
                        // Update the search input
                        const searchInput = document.getElementById('search_address');
                        if (searchInput) {
                            searchInput.value = address;
                            console.log('Updated search_address with:', address);
                        }
                    } else {
                        console.error('Geocoding failed:', status);
                        // At least update the coordinates even if geocoding fails
                        document.getElementById('property_location').value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                        document.getElementById('search_address').value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                    }
                });
            } catch (error) {
                console.error('Error in updateLocation:', error);
            }
        }

        function getCurrentLocation() {
            console.log('getCurrentLocation called');
            
            const currentLocationBtn = document.getElementById('currentLocationBtn');
            if (!currentLocationBtn) {
                console.error('Current location button not found');
                return;
            }
            
            // Save original button state
            const originalHtml = currentLocationBtn.innerHTML;
            const originalOnClick = currentLocationBtn.onclick;
            
            // Show loading state
            currentLocationBtn.disabled = true;
            currentLocationBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Locating...';
            currentLocationBtn.onclick = null; // Prevent multiple clicks
            
            // Function to reset button state
            const resetButton = function() {
                currentLocationBtn.disabled = false;
                currentLocationBtn.innerHTML = originalHtml;
                currentLocationBtn.onclick = originalOnClick;
            };
            
            if (!navigator.geolocation) {
                console.error('Geolocation is not supported by your browser');
                handleLocationError(false);
                resetButton();
                return;
            }
            
            console.log('Requesting geolocation...');
            
            // Set a timeout to ensure button gets reset even if geolocation hangs
            const timeoutId = setTimeout(function() {
                console.error('Geolocation request timed out');
                handleLocationError(true);
                resetButton();
            }, 15000); // 15 second timeout
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    clearTimeout(timeoutId); // Clear the timeout
                    console.log('Got geolocation:', position);
                    
                    const pos = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    
                    console.log('Position object:', pos);
                    
                    // Update the map and fields
                    updateMapAndFields(pos);
                    
                    // Reset button state
                    resetButton();
                },
                function(error) {
                    clearTimeout(timeoutId); // Clear the timeout
                    console.error('Geolocation error:', error);
                    handleLocationError(true);
                    resetButton();
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }
        
        function updateMapAndFields(pos) {
            console.log('Updating map and fields with position:', pos);
            
            try {
                // Ensure we have a LatLng object
                const latLng = new google.maps.LatLng(pos.lat, pos.lng);
                
                // Initialize map if not already done
                if (!window.map) {
                    console.log('Initializing map...');
                    initMap();
                    
                    // Give the map a moment to initialize
                    setTimeout(() => {
                        updateMapPosition(latLng);
                        updateLocation(latLng);
                    }, 500); // Increased timeout to ensure map is fully initialized
                } else {
                    updateMapPosition(latLng);
                    updateLocation(latLng);
                }
            } catch (error) {
                console.error('Error in updateMapAndFields:', error);
            }
        }
        
        function ensureMarkerExists(markerVar, position, options) {
            if (!markerVar) {
                console.log('Creating new marker with options:', options);
                return new google.maps.Marker({
                    position: position,
                    map: window.map,
                    ...options
                });
            }
            
            // If marker exists but is not on the map, re-create it
            if (!markerVar.getMap()) {
                console.log('Marker exists but has no map, re-creating...');
                markerVar.setMap(null); // Clean up old marker
                return new google.maps.Marker({
                    position: position,
                    map: window.map,
                    ...options
                });
            }
            
            // Just update position if marker is valid
            markerVar.setPosition(position);
            markerVar.setVisible(true);
            return markerVar;
        }
        
        function updateMapPosition(latLng) {
            console.log('updateMapPosition called with:', latLng);
            
            if (!latLng) {
                console.error('No latLng provided to updateMapPosition');
                return;
            }
            
            // Ensure we have a valid LatLng object
            const position = new google.maps.LatLng(
                typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat,
                typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng
            );
            
            // Initialize map if it doesn't exist
            if (!window.map) {
                console.log('Map not initialized, initializing now...');
                initMap();
                
                // Queue the position update after map is initialized
                setTimeout(() => updateMapPosition(latLng), 500);
                return;
            }
            
            try {
                // Update map view
                window.map.setCenter(position);
                window.map.setZoom(15);
                
                // Update or create main marker
                window.marker = ensureMarkerExists(
                    window.marker,
                    position,
                    {
                        draggable: true,
                        animation: google.maps.Animation.DROP,
                        icon: {
                            url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                            scaledSize: new google.maps.Size(30, 30)
                        },
                        zIndex: 2
                    }
                );
                
                // Ensure drag event is attached
                const dragListener = google.maps.event.addListenerOnce(
                    window.marker,
                    'dragend',
                    (event) => updateLocation(event.latLng)
                );
                
                // Update or create current location marker
                window.currentLocationMarker = ensureMarkerExists(
                    window.currentLocationMarker,
                    position,
                    {
                        animation: google.maps.Animation.DROP,
                        icon: {
                            url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                            scaledSize: new google.maps.Size(20, 20)
                        },
                        zIndex: 1
                    }
                );
                
                console.log('Markers updated successfully');
                
            } catch (error) {
                console.error('Error in updateMapPosition:', error);
                
                // If we get here, something is seriously wrong with the map
                if (!window.map) {
                    console.log('Map is not available, attempting to reinitialize...');
                    initMap();
                    setTimeout(() => updateMapPosition(latLng), 1000);
                }
            }
        }

        function handleLocationError(browserHasGeolocation) {
            alert(browserHasGeolocation ? 
                'Error: The Geolocation service failed.' :
                'Error: Your browser doesn\'t support geolocation.');
        }

        // Make getCurrentLocation globally available
        window.getCurrentLocation = getCurrentLocation;
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the map
            initMap();
            
            // Add click handler for the current location button
            document.getElementById('currentLocationBtn').addEventListener('click', function(e) {
                e.preventDefault();
                getCurrentLocation();
            });
            
            // For testing: Log when the page is fully loaded
            console.log('Page fully loaded, event listeners attached');
        });

        // File upload previews
        document.getElementById('property_main_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('mainImagePreview').innerHTML = `
                        <div class="d-inline-block position-relative">
                            <img src="${event.target.result}" class="img-thumbnail" style="max-height: 150px;">
                            <span class="badge bg-primary position-absolute top-0 start-100 translate-middle">
                                Main
                            </span>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });

        document.getElementById('property_additional_media').addEventListener('change', function(e) {
            const files = e.target.files;
            const previewContainer = document.getElementById('additionalMediaPreview');
            previewContainer.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    const isImage = file.type.startsWith('image/');
                    const col = document.createElement('div');
                    col.className = 'col-6 col-md-4 col-lg-3';
                    
                    if (isImage) {
                        col.innerHTML = `
                            <div class="ratio ratio-1x1">
                                <img src="${event.target.result}" class="img-fluid rounded" style="object-fit: cover;">
                            </div>
                        `;
                    } else {
                        col.innerHTML = `
                            <div class="ratio ratio-1x1 bg-light rounded d-flex align-items-center justify-content-center">
                                <i class="fas fa-video text-muted fa-2x"></i>
                            </div>
                        `;
                    }
                    
                    previewContainer.appendChild(col);
                };
                
                reader.readAsDataURL(file);
            }
        });

        // Form validation
        document.getElementById('propertyForm').addEventListener('submit', function(e) {
            // Ensure location is set
            const markerPosition = marker.getPosition();
            if (!markerPosition) {
                e.preventDefault();
                alert('Please select a location on the map first');
                return;
            }
            
            // Update hidden fields with current marker position
            document.getElementById('latitude').value = markerPosition.lat().toFixed(6);
            document.getElementById('longitude').value = markerPosition.lng().toFixed(6);
            
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
</body>
</html>