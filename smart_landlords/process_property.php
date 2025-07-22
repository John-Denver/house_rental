<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

header('Content-Type: application/json');

// Enable error reporting but capture errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Validate required fields
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $price = $_POST['price'] ?? '';
    $address = $_POST['address'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;
    
    if (empty($name) || empty($description) || empty($category_id) || empty($price) || empty($address)) {
        throw new Exception('All fields are required');
    }

    // Handle main image
    if (!isset($_FILES['main_image']) || !is_uploaded_file($_FILES['main_image']['tmp_name'])) {
        throw new Exception('Main image is required');
    }

    // Validate file type and size
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($_FILES['main_image']['type'], $allowed_types)) {
        throw new Exception('Invalid image type. Only JPEG, PNG, and WEBP are allowed');
    }

    if ($_FILES['main_image']['size'] > $max_size) {
        throw new Exception('Image size too large. Maximum size is 5MB');
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $ext = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
    $main_image = time() . '_main_' . uniqid() . '.' . $ext;
    $upload_path = $upload_dir . $main_image;

    // Move uploaded file
    if (!move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_path)) {
        throw new Exception('Failed to upload main image: ' . error_get_last()['message']);
    }

    // Insert house record
    $sql = "INSERT INTO houses (house_no, description, category_id, price, status, location, main_image, landlord_id, bedrooms, bathrooms, area, latitude, longitude, address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param('ssdssiiiiiddd', $house_no, $description, $price, $location, $category_id, $status, $main_image, $_SESSION['user_id'], $bedrooms, $bathrooms, $area, $latitude, $longitude, $address);
    
    if (!$stmt->execute()) {
        throw new Exception('Error saving property: ' . $stmt->error);
    }
    
    $house_id = $stmt->insert_id;
    
    // Handle additional media
    if (isset($_FILES['additional_media']) && is_array($_FILES['additional_media']['tmp_name'])) {
        $media = $_FILES['additional_media'];
        
        for ($i = 0; $i < count($media['tmp_name']); $i++) {
            if ($media['tmp_name'][$i] && $media['error'][$i] === 0) {
                // Validate file type and size
                $type = $media['type'][$i];
                if (!in_array($type, $allowed_types) && !strpos($type, 'video/')) {
                    continue; // Skip invalid files
                }

                if ($media['size'][$i] > $max_size) {
                    continue; // Skip large files
                }

                // Generate unique filename
                $ext = pathinfo($media['name'][$i], PATHINFO_EXTENSION);
                $file_name = time() . '_' . uniqid() . '.' . $ext;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($media['tmp_name'][$i], $file_path)) {
                    $media_type = strpos($type, 'image/') !== false ? 'image' : 'video';
                    
                    $sql = "INSERT INTO house_media (house_id, media_type, file_path) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $stmt->bind_param("iss", $house_id, $media_type, $file_name);
                    if (!$stmt->execute()) {
                        throw new Exception('Error saving media: ' . $stmt->error);
                    }
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Property added successfully']);
    
} catch (Exception $e) {
    http_response_code(400);
    error_log('Property upload error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
