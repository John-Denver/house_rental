<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Check if property_id is provided
if (!isset($_POST['property_id'])) {
    echo json_encode(['success' => false, 'error' => 'Property ID is required']);
    exit;
}

$property_id = $_POST['property_id'];

// Verify if the property exists and belongs to the landlord
$stmt = $conn->prepare("SELECT * FROM houses WHERE id = ? AND landlord_id = ?");
$stmt->bind_param('ii', $property_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Property not found or you do not have permission to delete it']);
    exit;
}

// Delete the property
$stmt = $conn->prepare("DELETE FROM houses WHERE id = ? AND landlord_id = ?");
$stmt->bind_param('ii', $property_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error deleting property: ' . $conn->error]);
}
?>
