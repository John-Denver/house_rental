<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Get all payments for this landlord
$query = "SELECT 
    mpr.*,
    rb.start_date, rb.end_date,
    h.house_no, h.description, h.location, h.price,
    u.name as tenant_name, u.username as tenant_email, u.phone_number as tenant_phone
FROM mpesa_payment_requests mpr
JOIN rental_bookings rb ON mpr.booking_id = rb.id
JOIN houses h ON rb.house_id = h.id
JOIN users u ON rb.user_id = u.id
WHERE h.landlord_id = ?
ORDER BY mpr.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Landlord Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="properties.php">
                                <i class="fas fa-home me-2"></i> Properties
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="fas fa-book me-2"></i> Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="payments.php">
                                <i class="fas fa-credit-card me-2"></i> Payments
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-credit-card me-2 text-primary"></i>
                        M-Pesa Payments
                    </h1>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Payment Records (<?php echo count($payments); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No payments found</h5>
                                <p class="text-muted">No M-Pesa payments have been made yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tenant</th>
                                            <th>Property</th>
                                            <th>Amount</th>
                                            <th>M-Pesa Receipt</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($payment['tenant_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['phone_number']); ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($payment['house_no']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['location']); ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-success">KSh <?php echo number_format($payment['amount'], 2); ?></div>
                                            </td>
                                            <td>
                                                <?php if ($payment['mpesa_receipt_number']): ?>
                                                    <span class="badge bg-primary">
                                                        <i class="fas fa-receipt me-1"></i>
                                                        <?php echo htmlspecialchars($payment['mpesa_receipt_number']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $statusColor = $payment['status'] === 'completed' ? 'success' : 
                                                             ($payment['status'] === 'pending' ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?php echo $statusColor; ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($payment['transaction_date']): ?>
                                                    <?php echo date('M d, Y H:i', strtotime($payment['transaction_date'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 