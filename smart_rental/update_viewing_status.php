<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login('./login.php');

header('Content-Type: application/json');

// Check if user is a landlord
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'landlord') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate input
$viewing_id = $_POST['viewing_id'] ?? 0;
$status = $_POST['status'] ?? '';
$allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];

if (empty($viewing_id) || !in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // First, verify that the viewing belongs to a property owned by this landlord
    $check_sql = "SELECT pv.id 
                 FROM property_viewings pv
                 JOIN houses h ON pv.property_id = h.id
                 WHERE pv.id = ? AND h.landlord_id = ?";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param('ii', $viewing_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Viewing not found or access denied');
    }
    
    // Update the status
    $update_sql = "UPDATE property_viewings SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('si', $status, $viewing_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update viewing status');
    }
    
    // If we got here, everything is good
    $conn->commit();
    
    // TODO: Send email notification to the user about the status change
    
    echo json_encode([
        'success' => true,
        'message' => 'Viewing status updated successfully',
        'status' => $status
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
