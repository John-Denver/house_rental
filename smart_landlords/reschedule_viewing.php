<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: scheduled_viewings.php');
    exit;
}

// Get and validate input
$viewing_id = $_POST['viewing_id'] ?? 0;
$viewing_date = $_POST['viewing_date'] ?? '';
$viewing_time = $_POST['viewing_time'] ?? '';
$notes = $_POST['notes'] ?? '';

if (empty($viewing_id) || empty($viewing_date) || empty($viewing_time)) {
    $_SESSION['error'] = 'Please fill in all required fields';
    header('Location: scheduled_viewings.php');
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
    
    // Check for scheduling conflicts
    $stmt = $conn->prepare("
        SELECT id 
        FROM property_viewings 
        WHERE property_id = ? 
        AND viewing_date = ? 
        AND viewing_time = ? 
        AND id != ?
        AND status != 'cancelled'
    ");
    $stmt->bind_param('issi', 
        $viewing['property_id'], 
        $viewing_date, 
        $viewing_time, 
        $viewing_id
    );
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('This time slot is already booked. Please choose another time.');
    }
    
    // Update the viewing
    $stmt = $conn->prepare("
        UPDATE property_viewings 
        SET viewing_date = ?, 
            viewing_time = ?, 
            notes = CONCAT(IFNULL(CONCAT(notes, '\n\n'), ''), 'Rescheduled on ', NOW(), ': ', ?),
            status = 'pending',
            updated_at = NOW() 
        WHERE id = ?
    ");
    $reschedule_note = "Viewing rescheduled to $viewing_date at $viewing_time. " . 
                      ($notes ? "Notes: $notes" : "");
    $stmt->bind_param('sssi', $viewing_date, $viewing_time, $reschedule_note, $viewing_id);
    $stmt->execute();
    
    // Get the updated viewing for notification
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
    
    // TODO: Send email notification to the user about the reschedule
    
    $_SESSION['success'] = 'Viewing has been rescheduled successfully';
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    $_SESSION['error'] = 'Failed to reschedule viewing: ' . $e->getMessage();
    error_log('Error rescheduling viewing: ' . $e->getMessage());
}

header('Location: scheduled_viewings.php');
exit;
