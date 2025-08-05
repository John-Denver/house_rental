<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkout_request_id = $_POST['checkout_request_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $receipt_number = $_POST['receipt_number'] ?? '';
    
    if ($checkout_request_id && $status) {
        // Update payment request status
        $stmt = $conn->prepare("
            UPDATE mpesa_payment_requests 
            SET 
                status = ?,
                mpesa_receipt_number = ?,
                updated_at = NOW()
            WHERE checkout_request_id = ?
        ");
        $stmt->bind_param('sss', $status, $receipt_number, $checkout_request_id);
        $stmt->execute();
        
        if ($status === 'completed') {
            // Get booking ID
            $stmt = $conn->prepare("SELECT booking_id FROM mpesa_payment_requests WHERE checkout_request_id = ?");
            $stmt->bind_param('s', $checkout_request_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $bookingId = $result['booking_id'];
                
                // Update booking status
                $stmt = $conn->prepare("
                    UPDATE rental_bookings 
                    SET status = 'confirmed', payment_status = 'paid', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $bookingId);
                $stmt->execute();
                
                // Record payment
                $stmt = $conn->prepare("
                    INSERT INTO booking_payments (
                        booking_id, amount, payment_method, transaction_id, 
                        status, payment_date, notes
                    ) VALUES (?, ?, 'M-Pesa', ?, 'completed', NOW(), 'Manual status update')
                ");
                
                // Get payment amount
                $stmt2 = $conn->prepare("SELECT amount FROM mpesa_payment_requests WHERE checkout_request_id = ?");
                $stmt2->bind_param('s', $checkout_request_id);
                $stmt2->execute();
                $paymentResult = $stmt2->get_result()->fetch_assoc();
                $amount = $paymentResult['amount'] ?? 0;
                
                $stmt->bind_param('ids', $bookingId, $amount, $receipt_number);
                $stmt->execute();
            }
        }
        
        $_SESSION['success'] = "Payment status updated to: $status";
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Get pending payment requests
$stmt = $conn->prepare("
    SELECT pr.*, rb.house_id, h.house_no
    FROM mpesa_payment_requests pr
    JOIN rental_bookings rb ON pr.booking_id = rb.id
    JOIN houses h ON rb.house_id = h.id
    WHERE pr.status = 'pending'
    ORDER BY pr.created_at DESC
");
$stmt->execute();
$pendingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Payment Status Update</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container py-5">
        <h1>Manual Payment Status Update</h1>
        <p class="text-muted">Use this page to manually update payment status when callbacks don't work.</p>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Pending Payment Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingPayments)): ?>
                            <p class="text-muted">No pending payment requests found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Checkout ID</th>
                                            <th>Property</th>
                                            <th>Amount</th>
                                            <th>Phone</th>
                                            <th>Created</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingPayments as $payment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payment['checkout_request_id']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['house_no']); ?></td>
                                                <td>KSh <?php echo number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($payment['phone_number']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-success btn-sm" onclick="updateStatus('<?php echo $payment['checkout_request_id']; ?>', 'completed')">
                                                        Mark Completed
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="updateStatus('<?php echo $payment['checkout_request_id']; ?>', 'failed')">
                                                        Mark Failed
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Manual Status Update</h5>
                    </div>
                    <div class="card-body">
                        <form id="statusForm" method="POST">
                            <div class="mb-3">
                                <label for="checkout_request_id" class="form-label">Checkout Request ID</label>
                                <input type="text" class="form-control" id="checkout_request_id" name="checkout_request_id" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="completed">Completed</option>
                                    <option value="failed">Failed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="receipt_number" class="form-label">Receipt Number (Optional)</label>
                                <input type="text" class="form-control" id="receipt_number" name="receipt_number">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateStatus(checkoutId, status) {
            document.getElementById('checkout_request_id').value = checkoutId;
            document.getElementById('status').value = status;
            document.getElementById('statusForm').submit();
        }
    </script>
</body>
</html> 