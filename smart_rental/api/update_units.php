<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/auth.php';

// Check if user is logged in and is a landlord
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$propertyId = $input['property_id'] ?? null;
$action = $input['action'] ?? null; // 'increment' or 'decrement'
$amount = $input['amount'] ?? 1; // Number of units to change

if (!$propertyId || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

try {
    // Verify the property belongs to the current user
    $stmt = $conn->prepare("
        SELECT id, house_no, available_units, total_units 
        FROM houses 
        WHERE id = ? AND landlord_id = ?
    ");
    $stmt->bind_param('ii', $propertyId, $_SESSION['user_id']);
    $stmt->execute();
    $property = $stmt->get_result()->fetch_assoc();
    
    if (!$property) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Property not found or access denied']);
        exit();
    }
    
    // Calculate new available units
    $currentUnits = $property['available_units'];
    $totalUnits = $property['total_units'];
    
    if ($action === 'increment') {
        $newUnits = min($currentUnits + $amount, $totalUnits);
    } elseif ($action === 'decrement') {
        $newUnits = max($currentUnits - $amount, 0);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action. Use "increment" or "decrement"']);
        exit();
    }
    
    // Update the property
    $stmt = $conn->prepare("UPDATE houses SET available_units = ? WHERE id = ? AND landlord_id = ?");
    $stmt->bind_param('iii', $newUnits, $propertyId, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $unitChange = $newUnits - $currentUnits;
        
        // Log the change
        error_log("Manual unit update: Property ID $propertyId, Action: $action, " .
                 "Units changed from $currentUnits to $newUnits (change: $unitChange) by user ID " . $_SESSION['user_id']);
        
        echo json_encode([
            'success' => true,
            'message' => "Units updated successfully",
            'property_id' => $propertyId,
            'property_name' => $property['house_no'],
            'old_units' => $currentUnits,
            'new_units' => $newUnits,
            'unit_change' => $unitChange,
            'total_units' => $totalUnits
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?> 