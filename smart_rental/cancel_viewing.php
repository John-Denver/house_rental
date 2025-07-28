<?php
// Start session and include required files
require_once '../config/db.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action.']);
    exit;
}

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get and validate input
$viewingId = filter_input(INPUT_POST, 'viewing_id', FILTER_VALIDATE_INT);
$reason = trim(filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING));

if (!$viewingId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid viewing ID.']);
    exit;
}

if (empty($reason)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for cancellation.']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check if the viewing exists and belongs to the current user
    $stmt = $conn->prepare("
        SELECT id, status, property_id 
        FROM property_viewings 
        WHERE id = ? AND user_id = ? AND status IN ('pending', 'confirmed')
    ");
    $stmt->bind_param('ii', $viewingId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Viewing not found or cannot be cancelled.');
    }
    
    $viewing = $result->fetch_assoc();
    
    // Update the viewing status to cancelled
    // Using notes field to store the cancellation reason since there's no dedicated cancellation_reason column
    $updateStmt = $conn->prepare("
        UPDATE property_viewings 
        SET status = 'cancelled', 
            notes = CONCAT(IFNULL(notes, ''), '\n[CANCELLED: ', ?, ']')
        WHERE id = ?
    ");
    $updateStmt->bind_param('si', $reason, $viewingId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update viewing status: ' . $conn->error);
    }
    
    // Activity logging removed - activity_logs table doesn't exist
    // You can enable this later if you create the activity_logs table
    /*
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent)
        VALUES (?, 'viewing_cancelled', ?, ?, ?)
    ");
    $activityDesc = "Cancelled viewing #$viewingId for property #" . $viewing['property_id'];
    $logStmt->bind_param('isss', $_SESSION['user_id'], $activityDesc, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    $logStmt->execute();
    */
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Viewing has been cancelled successfully.',
        'redirect' => 'my_bookings.php?status=cancelled'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while cancelling the viewing: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
