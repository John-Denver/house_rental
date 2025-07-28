<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and validate input
$viewing_id = $_POST['viewing_id'] ?? 0;
$status = $_POST['status'] ?? '';

if (empty($viewing_id) || empty($status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status
$allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // First, verify that the viewing belongs to a property owned by this landlord
    $stmt = $conn->prepare("
        SELECT pv.*, h.landlord_id 
        FROM property_viewings pv
        JOIN houses h ON pv.property_id = h.id 
        WHERE pv.id = ? AND h.landlord_id = ?
    ");
    $stmt->bind_param('ii', $viewing_id, $_SESSION['user_id']);
    $stmt->execute();
    $viewing = $stmt->get_result()->fetch_assoc();
    
    if (!$viewing) {
        throw new Exception('Viewing not found or access denied');
    }
    
    // Update the viewing status
    $stmt = $conn->prepare("
        UPDATE property_viewings 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param('si', $status, $viewing_id);
    $stmt->execute();
    
    // Get the updated viewing for response
    $stmt = $conn->prepare("
        SELECT pv.*, h.house_no, h.location, u.name as user_name, 
               u.phone_number as user_phone
        FROM property_viewings pv
        JOIN houses h ON pv.property_id = h.id
        LEFT JOIN users u ON pv.user_id = u.id
        WHERE pv.id = ?
    ");
    $stmt->bind_param('i', $viewing_id);
    $stmt->execute();
    $updated_viewing = $stmt->get_result()->fetch_assoc();
    
    // Commit transaction
    $conn->commit();
    
    // TODO: Send email notification to the user about the status change
    
    echo json_encode([
        'success' => true, 
        'message' => 'Viewing status updated successfully',
        'viewing' => $updated_viewing
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to update viewing status: ' . $e->getMessage()
    ]);
    error_log('Error updating viewing status: ' . $e->getMessage());
}
