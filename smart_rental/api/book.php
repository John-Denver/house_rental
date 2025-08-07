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
$required_fields = ['move_in_date', 'full_name', 'email', 'phone', 'property_id'];
$missing_fields = array_diff($required_fields, array_keys($data));

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
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

try {
    // Start database transaction to prevent race conditions
    $conn->beginTransaction();
    
    // Validate move-in date
    $moveInDate = $data['move_in_date'];
    $today = date('Y-m-d');
    
    if ($moveInDate < $today) {
        throw new Exception('Move-in date cannot be in the past');
    }
    
    if ($moveInDate > date('Y-m-d', strtotime('+6 months'))) {
        throw new Exception('Bookings cannot be made more than 6 months in advance');
    }
    
    // CRITICAL FIX: Check property availability with FOR UPDATE lock to prevent race conditions
    $propertyStmt = $conn->prepare("
        SELECT id, landlord_id, available_units, total_units 
        FROM houses 
        WHERE id = ? AND status = 1 
        FOR UPDATE
    ");
    $propertyStmt->execute(['id' => $data['property_id']]);
    $property = $propertyStmt->fetch();
    
    if (!$property) {
        throw new Exception('Property not found or not available');
    }
    
    // Check if property has available units
    if ($property['available_units'] <= 0) {
        throw new Exception('Property is fully booked');
    }
    
    // Check for existing bookings on the same date with lock
    $bookingStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM rental_bookings 
        WHERE house_id = ? 
        AND status NOT IN ('cancelled', 'completed')
        AND start_date = ?
        FOR UPDATE
    ");
    $bookingStmt->execute([$data['property_id'], $moveInDate]);
    $existingBookings = $bookingStmt->fetch();
    
    if ($existingBookings['count'] > 0) {
        throw new Exception('Property is not available for the selected date. It may have been booked by another user.');
    }
    
    // Insert booking into rental_bookings table
    $stmt = $conn->prepare("INSERT INTO rental_bookings (
        house_id, user_id, landlord_id, start_date, end_date, 
        special_requests, status, created_at
    ) VALUES (:house_id, :user_id, :landlord_id, :start_date, :end_date, :special_requests, 'pending', NOW())");
    
    // Get landlord_id from the property
    $landlordId = $property['landlord_id'] ?? 1; // Default to 1 if not set
    
    // Calculate end date (1 year from start date for now)
    $endDate = date('Y-m-d', strtotime($data['move_in_date'] . ' +1 year'));
    
    $params = [
        'house_id' => $data['property_id'],
        'user_id' => $_SESSION['user_id'],
        'landlord_id' => $landlordId,
        'start_date' => $data['move_in_date'],
        'end_date' => $endDate,
        'special_requests' => $data['special_requests'] ?? null
    ];

    if ($stmt->execute($params)) {
        $booking_id = $conn->lastInsertId();
        
        // Create monthly payment records immediately
        try {
            require_once __DIR__ . '/../monthly_payment_tracker.php';
            $tracker = new MonthlyPaymentTracker($conn);
            $payments = $tracker->getMonthlyPayments($booking_id);
            error_log("Created " . count($payments) . " monthly payment records for booking $booking_id via API");
        } catch (Exception $e) {
            error_log("Failed to create monthly payment records for booking $booking_id via API: " . $e->getMessage());
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Booking request submitted successfully. Please wait for landlord approval.',
            'booking_id' => $booking_id
        ]);
    } else {
        throw new PDOException('Failed to create booking');
    }
} catch(Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create booking: ' . $e->getMessage()
    ]);
}
?>
