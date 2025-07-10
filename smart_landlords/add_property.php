<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $house_no = $_POST['house_no'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $price = $_POST['price'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;
    $location = $_POST['location'] ?? '';
    $bedrooms = $_POST['bedrooms'] ?? '';
    $bathrooms = $_POST['bathrooms'] ?? '';
    $area = $_POST['area'] ?? '';

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
    $sql = "INSERT INTO houses (house_no, description, category_id, price, status, location, main_image, landlord_id, bedrooms, bathrooms, area) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "<script>alert('Database error: " . $conn->error . "'); window.history.back();</script>";
        exit;
    }

    $stmt->bind_param("sssssssssss", $house_no, $description, $category_id, $price, $status, $location, $main_image, $_SESSION['user_id'], $bedrooms, $bathrooms, $area);
    
    if (!$stmt->execute()) {
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
                                <label for="property_house_no" class="form-label">House Number</label>
                                <input type="text" class="form-control" id="property_house_no" name="house_no" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="property_description" class="form-label">Description</label>
                                <textarea class="form-control" id="property_description" name="description" rows="3" required></textarea>
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
                                <textarea class="form-control" id="property_location" name="location" rows="2" required></textarea>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add file selection feedback
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
