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
$propertyId = $input['property_id'] ?? null;
$rating = $input['rating'] ?? null;
$title = $input['title'] ?? '';
$review = $input['review'] ?? '';
$isAnonymous = $input['is_anonymous'] ?? false;

// Validate input
if (!$bookingId || !$propertyId || !$rating || !$title || !$review) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Validate rating (1-5)
if ($rating < 1 || $rating > 5) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit();
}

$bookingController = new BookingController($conn);

try {
    // Verify the user has completed this booking and can leave a review
    $booking = $bookingController->getBookingDetails($bookingId);
    
    if ($booking['user_id'] != $_SESSION['user_id']) {
        throw new Exception('You are not authorized to review this booking');
    }
    
    if ($booking['status'] !== 'completed') {
        throw new Exception('You can only review completed bookings');
    }
    
    // Check if a review already exists for this booking
    $stmt = $conn->prepare("
        SELECT id FROM booking_reviews 
        WHERE booking_id = ? AND user_id = ?
    ");
    $stmt->bind_param('ii', $bookingId, $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('You have already reviewed this booking');
    }
    
    // Insert the review
    $stmt = $conn->prepare("
        INSERT INTO booking_reviews 
        (booking_id, user_id, property_id, rating, title, review, is_anonymous, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->bind_param(
        'iiisssi', 
        $bookingId,
        $_SESSION['user_id'],
        $propertyId,
        $rating,
        $title,
        $review,
        $isAnonymous ? 1 : 0
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit review: ' . $stmt->error);
    }
    
    // Update property rating
    $this->updatePropertyRating($propertyId);
    
    // Send notification to admin for review approval
    $this->notifyAdminOfNewReview($conn->insert_id);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your review! It will be visible after approval.'
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Update the average rating for a property
 */
private function updatePropertyRating($propertyId) {
    $stmt = $this->conn->prepare("
        UPDATE houses h
        SET 
            rating = (
                SELECT AVG(rating) 
                FROM booking_reviews 
                WHERE property_id = ? 
                AND status = 'approved'
            ),
            review_count = (
                SELECT COUNT(*) 
                FROM booking_reviews 
                WHERE property_id = ? 
                AND status = 'approved'
            )
        WHERE id = ?
    ");
    
    $stmt->bind_param('iii', $propertyId, $propertyId, $propertyId);
    $stmt->execute();
}

/**
 * Notify admin of a new review that needs approval
 */
private function notifyAdminOfNewReview($reviewId) {
    // In a real application, this would send an email to the admin
    // For now, we'll just log it
    error_log("New review submitted. Review ID: " . $reviewId);
    
    // Example email sending (commented out as it requires email configuration)
    /*
    $to = 'admin@example.com';
    $subject = 'New Review Requires Approval';
    $message = "A new review has been submitted and is awaiting approval.\n";
    $message .= "Review ID: " . $reviewId . "\n";
    $message .= "Please log in to the admin panel to review it.";
    
    mail($to, $subject, $message);
    */
}
