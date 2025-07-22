<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Check if required parameters are present
if (!isset($_POST['property_id'])) {
    echo json_encode(['success' => false, 'error' => 'Property ID is required']);
    exit;
}

$property_id = $_POST['property_id'];
$action = $_POST['action'] ?? 'decrement'; // Can be 'decrement' or 'increment'

// Verify if the property exists and belongs to the landlord
$stmt = $conn->prepare("SELECT * FROM houses WHERE id = ? AND landlord_id = ?");
$stmt->bind_param('ii', $property_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Property not found or you do not have permission to update units']);
    exit;
}

$property = $result->fetch_assoc();

// No need to check for available units anymore since we allow zero units
// The property will simply show as unavailable when units reach zero

// Update units
if ($action === 'decrement') {
    $new_units = $property['available_units'] - 1;
} else {
    $new_units = $property['available_units'] + 1;
}

// Update the property
$stmt = $conn->prepare("UPDATE houses SET available_units = ? WHERE id = ? AND landlord_id = ?");
$stmt->bind_param('iii', $new_units, $property_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'available_units' => $new_units]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error updating units: ' . $conn->error]);
}
?>
