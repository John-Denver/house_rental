<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to save favorites']);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);
$house_id = isset($data['house_id']) ? (int)$data['house_id'] : 0;

if (!$house_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid property ID']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false];

try {
    // Check if already favorited
    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND house_id = ?");
    $stmt->bind_param('ii', $user_id, $house_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Unfavorite
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND house_id = ?");
        $stmt->bind_param('ii', $user_id, $house_id);
        $stmt->execute();
        $response = [
            'success' => true,
            'is_favorite' => false,
            'message' => 'Property removed from favorites'
        ];
    } else {
        // Favorite
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, house_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $user_id, $house_id);
        $stmt->execute();
        $response = [
            'success' => true,
            'is_favorite' => true,
            'message' => 'Property added to favorites'
        ];
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Error updating favorites'];
}

echo json_encode($response);
?>
