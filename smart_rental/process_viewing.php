<?php
require_once '../config/db.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and validate input
$property_id = $_POST['house_id'] ?? 0;
$viewing_date = $_POST['viewing_date'] ?? '';
$viewing_time = $_POST['viewing_time'] ?? '';
$contact_number = $_POST['contact_number'] ?? '';
$notes = $_POST['viewing_notes'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// Basic validation
if (empty($property_id) || empty($viewing_date) || empty($viewing_time) || empty($contact_number)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

try {
    // Check if property exists and is active
    $stmt = $conn->prepare("SELECT id, landlord_id FROM houses WHERE id = ? AND status = 1");
    $stmt->bind_param('i', $property_id);
    $stmt->execute();
    $property = $stmt->get_result()->fetch_assoc();
    
    if (!$property) {
        throw new Exception('Property not found or not available for viewing');
    }

    // Check for existing viewing at the same time
    $stmt = $conn->prepare("
        SELECT id 
        FROM property_viewings 
        WHERE property_id = ? 
        AND viewing_date = ? 
        AND viewing_time = ? 
        AND status != 'cancelled'
    ");
    $stmt->bind_param('iss', $property_id, $viewing_date, $viewing_time);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('This time slot is already booked. Please choose another time.');
    }

    // Insert new viewing
    $stmt = $conn->prepare("
        INSERT INTO property_viewings 
        (property_id, user_id, viewer_name, contact_number, viewing_date, viewing_time, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $viewer_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest';
    $stmt->bind_param(
        'iisssss',
        $property_id,
        $user_id,
        $viewer_name,
        $contact_number,
        $viewing_date,
        $viewing_time,
        $notes
    );
    
    if ($stmt->execute()) {
        $viewing_id = $conn->insert_id;
        
        // TODO: Send email notification to landlord
        // TODO: Send confirmation email to user if logged in
        
        echo json_encode([
            'success' => true,
            'message' => 'Viewing scheduled successfully! We will contact you to confirm the appointment.',
            'viewing_id' => $viewing_id
        ]);
    } else {
        throw new Exception('Failed to schedule viewing. Please try again.');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
