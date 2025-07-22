<?php
// Disable PHP error display completely
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure JSON response
header('Content-Type: application/json');

// Database configuration - use PDO for better error handling
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error. Please try again later.'
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please login to book a property'
    ]);
    exit;
}

// Get POST data
try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit;
}

// Validate required fields
$required_fields = ['move_in_date', 'lease_duration', 'full_name', 'email', 'phone', 'property_id'];
$missing_fields = array_diff($required_fields, array_keys($data));

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit;
}

// Validate move-in date
if (strtotime($data['move_in_date']) < strtotime('+1 month')) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Move-in date must be at least 1 month from now'
    ]);
    exit;
}

// Validate lease duration
$valid_durations = [6, 12, 24];
if (!in_array($data['lease_duration'], $valid_durations)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid lease duration. Valid options are: ' . implode(', ', $valid_durations)
    ]);
    exit;
}

// Validate email format
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

// Validate phone number format
if (!preg_match('/^[0-9]{10}$/', $data['phone'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Phone number must be 10 digits'
    ]);
    exit;
}

// Check if property exists and is available
try {
    $stmt = $conn->prepare("SELECT id, status FROM houses WHERE id = :id AND status = 1");
    $stmt->execute(['id' => $data['property_id']]);
    $property = $stmt->fetch();

    if (!$property) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Property not found or not available'
        ]);
        exit;
    }

    // Insert booking
    $stmt = $conn->prepare("INSERT INTO bookings (user_id, property_id, move_in_date, lease_duration, full_name, email, phone, status, created_at) 
                            VALUES (:user_id, :property_id, :move_in_date, :lease_duration, :full_name, :email, :phone, 'pending', NOW())");
    $params = [
        'user_id' => $_SESSION['user_id'],
        'property_id' => $data['property_id'],
        'move_in_date' => $data['move_in_date'],
        'lease_duration' => $data['lease_duration'],
        'full_name' => $data['full_name'],
        'email' => $data['email'],
        'phone' => $data['phone']
    ];

    if ($stmt->execute($params)) {
        $booking_id = $conn->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Booking request submitted successfully. Please wait for landlord approval.',
            'booking_id' => $booking_id
        ]);
    } else {
        throw new PDOException('Failed to create booking');
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create booking. Please try again.'
    ]);
}
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

header('Content-Type: application/json');

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
