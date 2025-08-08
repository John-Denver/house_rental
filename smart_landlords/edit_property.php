<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Debug logging
error_log('FILES: ' . print_r($_FILES, true));
error_log('POST: ' . print_r($_POST, true));

$response = ['success' => false];

try {
    // Validate required fields
    $required = ['edit_property', 'property_id', 'house_no', 'description', 'price', 'location', 'category_id', 'bedrooms', 'bathrooms', 'area', 'latitude', 'longitude', 'total_units', 'available_units'];
    foreach ($required as $field) {
        if (!isset($_POST[$field])) throw new Exception("Missing: $field");
    }

    $property_id = (int)$_POST['property_id'];
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
    $total_units = (int)$_POST['total_units'];
    $available_units = (int)$_POST['available_units'];

    // Get current property
    $stmt = $conn->prepare("SELECT main_image FROM houses WHERE id = ? AND landlord_id = ?");
    $stmt->bind_param('ii', $property_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception('Property not found or permission denied');
    $property = $result->fetch_assoc();
    $main_image = $property['main_image'];

    // Handle main image upload
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
        $new_image = time() . '_main_' . basename($_FILES['main_image']['name']);
        $upload_path = '../uploads/' . $new_image;
        if (!move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_path)) {
            throw new Exception('Failed to upload main image');
        }
        // Delete old image if exists
        if ($main_image && file_exists('../uploads/' . $main_image)) {
            @unlink('../uploads/' . $main_image);
        }
        $main_image = $new_image;
    } // else, keep $main_image as is (do not overwrite)

    // Handle additional media uploads
    if (isset($_FILES['additional_media']) && is_array($_FILES['additional_media']['name'])) {
        foreach ($_FILES['additional_media']['name'] as $i => $filename) {
            if ($_FILES['additional_media']['error'][$i] === 0) {
                $image_name = time() . '_' . $i . '_' . basename($filename);
                $upload_path = '../uploads/' . $image_name;
                if (move_uploaded_file($_FILES['additional_media']['tmp_name'][$i], $upload_path)) {
                    $stmt = $conn->prepare("INSERT INTO house_media (house_id, media_type, file_path, updated_at) VALUES (?, 'image', ?, NOW())");
                    $stmt->bind_param('is', $property_id, $image_name);
                    $stmt->execute();
                }
            }
        }
    }

    // Get current property price for comparison
    $stmt = $conn->prepare("SELECT price FROM houses WHERE id = ? AND landlord_id = ?");
    $stmt->bind_param('ii', $property_id, $_SESSION['user_id']);
    $stmt->execute();
    $currentProperty = $stmt->get_result()->fetch_assoc();
    $oldPrice = $currentProperty['price'];
    
    // Update property
    $stmt = $conn->prepare("UPDATE houses SET 
        house_no = ?, description = ?, price = ?, location = ?, latitude = ?, longitude = ?, category_id = ?, status = ?, bedrooms = ?, bathrooms = ?, area = ?, main_image = ?, total_units = ?, available_units = ?, updated_at = NOW()
        WHERE id = ? AND landlord_id = ?");
    $stmt->bind_param('ssddssddiiiiisii',
        $house_no, $description, $price, $location, $latitude, $longitude, $category_id, $status,
        $bedrooms, $bathrooms, $area, $main_image, $total_units, $available_units, $property_id, $_SESSION['user_id']
    );
    if (!$stmt->execute()) throw new Exception('Error updating property: ' . $stmt->error);
    
    // Cascade price update to related tables if price changed
    if ($oldPrice != $price) {
        require_once 'update_property_price_cascade.php';
        $cascade = new PropertyPriceCascade($conn);
        $cascadeResult = $cascade->updatePropertyPrice($property_id, $price, $oldPrice);
        
        if (!$cascadeResult['success']) {
            throw new Exception('Error cascading price update: ' . $cascadeResult['message']);
        }
        
        // Add cascade info to response
        $response['cascade_info'] = $cascadeResult;
    }

    $response['success'] = true;
    $response['main_image'] = $main_image;
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}
echo json_encode($response);
?>
