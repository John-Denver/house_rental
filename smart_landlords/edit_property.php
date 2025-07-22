<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Check if we have all required fields
$required_fields = ['edit_property', 'property_id', 'house_no', 'description', 'price', 'location', 'category_id', 'status', 'bedrooms', 'bathrooms', 'area', 'latitude', 'longitude'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

// Get form data
$property_id = $_POST['property_id'];
$house_no = $_POST['house_no'];
$description = $_POST['description'];
$price = $_POST['price'];
$location = $_POST['location'];
$category_id = $_POST['category_id'];
$status = isset($_POST['status']) ? 1 : 0;
$bedrooms = $_POST['bedrooms'];
$bathrooms = $_POST['bathrooms'];
$area = $_POST['area'];
$latitude = $_POST['latitude'];
$longitude = $_POST['longitude'];

// Verify if the property exists and belongs to the landlord
$stmt = $conn->prepare("SELECT * FROM houses WHERE id = ? AND landlord_id = ?");
$stmt->bind_param('ii', $property_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Property not found or you do not have permission to edit it']);
    exit;
}

// Handle image uploads
if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
    $main_image = time() . '_main_' . $_FILES['main_image']['name'];
    $upload_path = '../uploads/' . $main_image;
    
    if (!move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_path)) {
        echo json_encode(['success' => false, 'error' => 'Failed to upload main image']);
        exit;
    }
} else {
    // Keep existing image
    $stmt = $conn->prepare("SELECT main_image FROM houses WHERE id = ?");
    $stmt->bind_param('i', $property_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $property = $result->fetch_assoc();
    $main_image = $property['main_image'];
}

// Handle additional media uploads
if (isset($_FILES['additional_media']) && is_array($_FILES['additional_media']['name'])) {
    $additional_images = [];
    foreach ($_FILES['additional_media']['name'] as $index => $filename) {
        if ($_FILES['additional_media']['error'][$index] === 0) {
            $image_name = time() . '_' . $index . '_' . $filename;
            $upload_path = '../uploads/' . $image_name;
            
            if (move_uploaded_file($_FILES['additional_media']['tmp_name'][$index], $upload_path)) {
                $additional_images[] = $image_name;
            }
        }
    }
    
    // Insert additional images into house_media table
    if (!empty($additional_images)) {
        $stmt = $conn->prepare("INSERT INTO house_media (house_id, media_type, media_path) VALUES (?, 'image', ?)");
        $stmt->bind_param('is', $property_id, $image_path);
        
        foreach ($additional_images as $image) {
            $image_path = $image;
            $stmt->execute();
        }
    }
}

// Update the property
$stmt = $conn->prepare("UPDATE houses SET 
                      house_no = ?, 
                      description = ?, 
                      price = ?, 
                      location = ?,
                      latitude = ?,
                      longitude = ?,
                      category_id = ?, 
                      status = ?,
                      bedrooms = ?,
                      bathrooms = ?,
                      area = ?,
                      main_image = ?
                      WHERE id = ? AND landlord_id = ?");

$stmt->bind_param('ssdssddiiiiisi', 
    $house_no, 
    $description, 
    $price, 
    $location,
    $latitude,
    $longitude,
    $category_id, 
    $status,
    $bedrooms,
    $bathrooms,
    $area,
    $main_image,
    $property_id,
    $_SESSION['user_id']
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error updating property: ' . $conn->error]);
}
?>
