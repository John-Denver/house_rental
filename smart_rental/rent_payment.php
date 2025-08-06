<?php
// Include config file
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/monthly_payment_tracker.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle CSV download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payment_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Booking ID', 'Property', 'Month', 'Amount', 'Status', 'Payment Method', 'Transaction ID']);
    
    // Get all user's payments
    $stmt = $conn->prepare("
        SELECT 
            bp.payment_date,
            bp.booking_id,
            h.house_no as property_name,
            mrp.month,
            bp.amount,
            bp.status,
            bp.payment_method,
            bp.transaction_id
        FROM booking_payments bp
        JOIN rental_bookings rb ON bp.booking_id = rb.id
        JOIN houses h ON rb.house_id = h.id
        LEFT JOIN monthly_rent_payments mrp ON bp.booking_id = mrp.booking_id 
            AND bp.payment_date = mrp.payment_date
        WHERE rb.user_id = ?
        ORDER BY bp.payment_date DESC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['payment_date'],
            $row['booking_id'],
            $row['property_name'],
            $row['month'] ? date('F Y', strtotime($row['month'])) : 'N/A',
            $row['amount'],
            $row['status'],
            $row['payment_method'],
            $row['transaction_id']
        ]);
    }
    
    fclose($output);
    exit();
}

// Get user's bookings (all statuses, not just 'approved')
$stmt = $conn->prepare("
    SELECT 
        b.*, 
        h.house_no as property_name, 
        h.price,
        h.location,
        h.description
    FROM rental_bookings b
    JOIN houses h ON b.house_id = h.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Initialize MonthlyPaymentTracker
$tracker = new MonthlyPaymentTracker($conn);

// Get payment statistics
$paymentStats = [];
$totalPaid = 0;
$totalUnpaid = 0;
$totalBookings = count($bookings);

foreach ($bookings as $booking) {
    $payments = $tracker->getMonthlyPayments($booking['id']);
    $summary = $tracker->getPaymentSummary($booking['id']);
    $nextPayment = $tracker->getNextPaymentDue($booking['id']);
    
    $paymentStats[$booking['id']] = [
        'payments' => $payments,
        'summary' => $summary,
        'nextPayment' => $nextPayment,
        'totalPaid' => $summary['total_paid'] ?? 0,
        'totalUnpaid' => $summary['total_unpaid'] ?? 0
    ];
    
    $totalPaid += $summary['total_paid'] ?? 0;
    $totalUnpaid += $summary['total_unpaid'] ?? 0;
}

// Get recent M-Pesa payments
$stmt = $conn->prepare("
    SELECT 
        mpr.*,
        rb.house_id,
        h.house_no as property_name
    FROM mpesa_payment_requests mpr
    JOIN rental_bookings rb ON mpr.booking_id = rb.id
    JOIN houses h ON rb.house_id = h.id
    WHERE rb.user_id = ?
    ORDER BY mpr.created_at DESC
    LIMIT 10
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentMpesaPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent booking payments
$stmt = $conn->prepare("
    SELECT 
        bp.*,
        rb.house_id,
        h.house_no as property_name
    FROM booking_payments bp
    JOIN rental_bookings rb ON bp.booking_id = rb.id
    JOIN houses h ON rb.house_id = h.id
    WHERE rb.user_id = ?
    ORDER BY bp.payment_date DESC
    LIMIT 10
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentBookingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-credit-card me-2"></i>Payments Dashboard</h2>
                <div>
                    <a href="?download=csv" class="btn btn-outline-primary">
                        <i class="fas fa-download me-1"></i>Download Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Bookings</h5>
                    <h3 class="mb-0"><?php echo $totalBookings; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Paid</h5>
                    <h3 class="mb-0">KSh <?php echo number_format($totalPaid, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Unpaid</h5>
                    <h3 class="mb-0">KSh <?php echo number_format($totalUnpaid, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Payment Rate</h5>
                    <h3 class="mb-0">
                        <?php 
                        $totalAmount = $totalPaid + $totalUnpaid;
                        echo $totalAmount > 0 ? round(($totalPaid / $totalAmount) * 100, 1) : 0;
                        ?>%
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Bookings and Payments -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-home me-2"></i>My Bookings & Payments</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($bookings)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No bookings found. You can browse properties and make bookings to see payment information here.
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-building me-2"></i>
                                        <?php echo htmlspecialchars($booking['property_name']); ?>
                                </h5>
                                    <span class="badge bg-<?php echo $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Booking Details</h6>
                                            <ul class="list-unstyled">
                                                <li><strong>Booking ID:</strong> #<?php echo $booking['id']; ?></li>
                                                <li><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($booking['start_date'])); ?></li>
                                                <li><strong>End Date:</strong> <?php echo date('M d, Y', strtotime($booking['end_date'])); ?></li>
                                                <li><strong>Monthly Rent:</strong> KSh <?php echo number_format($booking['monthly_rent'], 2); ?></li>
                                                <li><strong>Security Deposit:</strong> KSh <?php echo number_format($booking['security_deposit'], 2); ?></li>
                                                <li><strong>Location:</strong> <?php echo htmlspecialchars($booking['location']); ?></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Payment Summary</h6>
                                            <?php 
                                            $stats = $paymentStats[$booking['id']] ?? [];
                                            $summary = $stats['summary'] ?? [];
                                            $nextPayment = $stats['nextPayment'] ?? null;
                                            ?>
                                            <ul class="list-unstyled">
                                                <li><strong>Total Paid:</strong> KSh <?php echo number_format($summary['total_paid'] ?? 0, 2); ?></li>
                                                <li><strong>Total Unpaid:</strong> KSh <?php echo number_format($summary['total_unpaid'] ?? 0, 2); ?></li>
                                                <li><strong>Paid Months:</strong> <?php echo $summary['paid_months'] ?? 0; ?> / <?php echo $summary['total_months'] ?? 0; ?></li>
                                                <?php if ($nextPayment && ($booking['status'] === 'confirmed' || $booking['status'] === 'active')): ?>
                                                    <li><strong>Next Payment:</strong> <?php echo date('F Y', strtotime($nextPayment['month'])); ?> - KSh <?php echo number_format($nextPayment['amount'], 2); ?></li>
                                                    <li>
                                                        <a href="booking_payment.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-credit-card me-1"></i>Make Payment
                                                        </a>
                                                    </li>
                                                <?php elseif ($nextPayment && $booking['status'] === 'pending'): ?>
                                                    <li><strong>Next Payment:</strong> <?php echo date('F Y', strtotime($nextPayment['month'])); ?> - KSh <?php echo number_format($nextPayment['amount'], 2); ?></li>
                                                    <li>
                                                        <span class="badge bg-warning">Payment available after approval</span>
                                                    </li>
                                                <?php else: ?>
                                                    <li><strong>Status:</strong> <span class="badge bg-success">All Paid!</span></li>
                                <?php endif; ?>
                                            </ul>
                            </div>
                                    </div>
                                    
                                    <!-- Monthly Payments Table -->
                                    <?php if (!empty($stats['payments'])): ?>
                                        <div class="mt-3">
                                            <h6>Monthly Payment Details</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Month</th>
                                                            <th>Amount</th>
                                                            <th>Status</th>
                                                            <th>Payment Date</th>
                                                            <th>Method</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($stats['payments'] as $payment): ?>
                                                            <tr>
                                                                <td><?php echo date('F Y', strtotime($payment['month'])); ?></td>
                                                                <td>KSh <?php echo number_format($payment['amount'], 2); ?></td>
                                                                <td>
                                                                    <span class="badge bg-<?php echo $payment['status'] === 'paid' ? 'success' : 'danger'; ?>">
                                                                        <?php echo ucfirst($payment['status']); ?>
                                                                    </span>
                                                                </td>
                                                                <td><?php echo $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-'; ?></td>
                                                                <td><?php echo $payment['payment_method'] ?: '-'; ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                                </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                            <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent M-Pesa Payments</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentMpesaPayments)): ?>
                        <p class="text-muted">No M-Pesa payments found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                            <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                        <th>Property</th>
                                        <th>Amount</th>
                                                    <th>Status</th>
                                        <th>Type</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                    <?php foreach ($recentMpesaPayments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                                            <td>KSh <?php echo number_format($payment['amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                                <?php echo ucfirst($payment['status']); ?>
                                                            </span>
                                                        </td>
                                            <td><?php echo ucfirst($payment['payment_type'] ?? 'initial'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Recent Booking Payments</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentBookingPayments)): ?>
                        <p class="text-muted">No booking payments found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Property</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBookingPayments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                                            <td>KSh <?php echo number_format($payment['amount'], 2); ?></td>
                                            <td><?php echo $payment['payment_method']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
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
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
