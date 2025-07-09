<?php
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please login to book a property']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Required fields
$required_fields = ['property_id', 'move_in_date', 'lease_duration', 'full_name', 'email', 'phone'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Validate move-in date
$move_in_date = date('Y-m-d', strtotime($data['move_in_date']));
if ($move_in_date < date('Y-m-d')) {
    http_response_code(400);
    echo json_encode(['error' => 'Move-in date cannot be in the past']);
    exit;
}

// Check if property exists and is available
$sql = "SELECT * FROM houses WHERE id = ? AND status = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $data['property_id']);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    http_response_code(404);
    echo json_encode(['error' => 'Property not found or not available']);
    exit;
}

// Calculate total rent
$total_rent = $property['price'] * $data['lease_duration'];

// Insert booking
$sql = "INSERT INTO bookings (
    property_id,
    user_id,
    move_in_date,
    lease_duration,
    total_rent,
    full_name,
    email,
    phone,
    status,
    created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iisddssss', 
    $data['property_id'],
    $_SESSION['user_id'],
    $move_in_date,
    $data['lease_duration'],
    $total_rent,
    $data['full_name'],
    $data['email'],
    $data['phone']
);

if ($stmt->execute()) {
    // Update property status to booked
    $sql = "UPDATE houses SET status = 2 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $data['property_id']);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Booking request submitted successfully',
        'booking_id' => $conn->insert_id,
        'total_rent' => $total_rent
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create booking']);
}
?>
