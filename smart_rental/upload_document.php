<?php
session_start();
require_once 'config/db.php';
require_once 'controllers/BookingController.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$bookingId = $input['booking_id'] ?? null;
$documentType = $input['document_type'] ?? null;

if (!$bookingId || !$documentType) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$bookingController = new BookingController($conn);

// Verify user has permission to upload documents for this booking
try {
    $booking = $bookingController->getBookingDetails($bookingId);
    
    if ($booking['user_id'] != $_SESSION['user_id'] && $booking['landlord_id'] != $_SESSION['user_id'] && !isset($_SESSION['is_admin'])) {
        throw new Exception('Unauthorized access to this booking');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['document'])) {
        throw new Exception('No file was uploaded');
    }
    
    $file = $_FILES['document'];
    
    // Handle the file upload
    $result = $bookingController->uploadDocument($bookingId, $documentType, $file);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'document' => [
            'id' => $result['id'],
            'document_type' => $result['document_type'],
            'file_path' => $result['file_path'],
            'uploaded_at' => $result['uploaded_at']
        ]
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
