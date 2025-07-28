<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login('./login.php');

// Check if user is a landlord
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'landlord') {
    $_SESSION['error'] = 'Unauthorized access';
    header('Location: index.php');
    exit;
}

// Validate input
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
    // Begin transaction
    $conn->begin_transaction();
    
    // First, verify that the viewing belongs to a property owned by this landlord
    $check_sql = "SELECT pv.id, pv.viewer_name, pv.viewing_date, pv.viewing_time, 
                         h.house_no, h.location, u.email as user_email, u.name as user_name
                  FROM property_viewings pv
                  JOIN houses h ON pv.property_id = h.id
                  LEFT JOIN users u ON pv.user_id = u.id
                  WHERE pv.id = ? AND h.landlord_id = ?";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param('ii', $viewing_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Viewing not found or access denied');
    }
    
    $viewing = $result->fetch_assoc();
    
    // Check for scheduling conflicts
    $conflict_sql = "SELECT id FROM property_viewings 
                    WHERE property_id = (SELECT property_id FROM property_viewings WHERE id = ?)
                    AND viewing_date = ? 
                    AND viewing_time = ? 
                    AND status != 'cancelled'
                    AND id != ?";
    
    $stmt = $conn->prepare($conflict_sql);
    $property_id = $viewing['property_id'] ?? 0;
    $stmt->bind_param('issi', $property_id, $viewing_date, $viewing_time, $viewing_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('This time slot is already booked. Please choose another time.');
    }
    
    // Update the viewing
    $update_sql = "UPDATE property_viewings 
                   SET viewing_date = ?, 
                       viewing_time = ?,
                       status = 'pending',
                       notes = CONCAT(IFNULL(CONCAT(notes, '\n\n'), ''), 'Rescheduled from ',
                                   DATE_FORMAT(?, '%Y-%m-%d %H:%i'), ' to ', ?, ' ', ?,\n                                   IF(? != '', CONCAT('\n\nReschedule notes: ', ?), ''))
                   WHERE id = ?";
    
    $stmt = $conn->prepare($update_sql);
    $old_datetime = $viewing['viewing_date'] . ' ' . $viewing['viewing_time'];
    $stmt->bind_param(
        'sssssssi',
        $viewing_date,
        $viewing_time,
        $old_datetime,
        $viewing_date,
        $viewing_time,
        $notes,
        $notes,
        $viewing_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to reschedule viewing');
    }
    
    // If we got here, everything is good
    $conn->commit();
    
    // TODO: Send email notification to the user about the reschedule
    
    $_SESSION['success'] = 'Viewing has been rescheduled successfully';
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

header('Location: scheduled_viewings.php');
exit;
