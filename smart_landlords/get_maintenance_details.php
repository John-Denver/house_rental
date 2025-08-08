<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Request ID is required']);
    exit;
}

$request_id = intval($_GET['id']);

try {
    // Get maintenance request details with property and tenant information
    $sql = "
        SELECT mr.*, 
               h.house_no, h.location as property_location, h.description as property_description,
               u.name as tenant_name, u.username as tenant_email, u.phone_number as tenant_phone
        FROM maintenance_requests mr
        JOIN houses h ON mr.property_id = h.id
        JOIN users u ON mr.tenant_id = u.id
        WHERE mr.id = ? AND h.landlord_id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $request_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Maintenance request not found']);
        exit;
    }
    
    $request = $result->fetch_assoc();
    
    // Format dates
    $request['submission_date_formatted'] = date('F d, Y \a\t g:i A', strtotime($request['submission_date']));
    $request['created_at_formatted'] = date('F d, Y \a\t g:i A', strtotime($request['created_at']));
    $request['updated_at_formatted'] = date('F d, Y \a\t g:i A', strtotime($request['updated_at']));
    
    if ($request['assigned_repair_date']) {
        $request['assigned_repair_date_formatted'] = date('F d, Y \a\t g:i A', strtotime($request['assigned_repair_date']));
    }
    
    if ($request['completion_date']) {
        $request['completion_date_formatted'] = date('F d, Y \a\t g:i A', strtotime($request['completion_date']));
    }
    
    // Get status badge class
    $status_classes = [
        'Pending' => 'badge bg-warning',
        'In Progress' => 'badge bg-info',
        'Completed' => 'badge bg-success',
        'Rejected' => 'badge bg-danger'
    ];
    
    $request['status_class'] = $status_classes[$request['status']] ?? 'badge bg-secondary';
    
    // Get urgency badge class
    $urgency_classes = [
        'Low' => 'badge bg-success',
        'Medium' => 'badge bg-warning',
        'High' => 'badge bg-danger',
        'Critical' => 'badge bg-dark'
    ];
    
    $request['urgency_class'] = $urgency_classes[$request['urgency']] ?? 'badge bg-secondary';
    
    echo json_encode(['success' => true, 'data' => $request]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
