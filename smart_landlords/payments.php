<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Get comprehensive payment analytics
$landlord_id = $_SESSION['user_id'];

// 1. Overall Statistics
$stats_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN mpr.status = 'completed' THEN mpr.amount ELSE 0 END) as total_received,
    SUM(CASE WHEN mpr.status = 'pending' THEN mpr.amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN mpr.status = 'failed' THEN mpr.amount ELSE 0 END) as failed_amount,
    COUNT(CASE WHEN mpr.status = 'completed' THEN 1 END) as completed_payments,
    COUNT(CASE WHEN mpr.status = 'pending' THEN 1 END) as pending_payments,
    COUNT(CASE WHEN mpr.status = 'failed' THEN 1 END) as failed_payments,
    AVG(CASE WHEN mpr.status = 'completed' THEN mpr.amount END) as avg_payment_amount
FROM mpesa_payment_requests mpr
JOIN rental_bookings rb ON mpr.booking_id = rb.id
JOIN houses h ON rb.house_id = h.id
WHERE h.landlord_id = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param('i', $landlord_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// 2. Monthly Payment Trends (Last 12 months)
$monthly_query = "SELECT 
    DATE_FORMAT(mpr.created_at, '%Y-%m') as month,
    COUNT(*) as payment_count,
    SUM(CASE WHEN mpr.status = 'completed' THEN mpr.amount ELSE 0 END) as total_amount,
    COUNT(CASE WHEN mpr.status = 'completed' THEN 1 END) as completed_count
FROM mpesa_payment_requests mpr
JOIN rental_bookings rb ON mpr.booking_id = rb.id
JOIN houses h ON rb.house_id = h.id
WHERE h.landlord_id = ? 
AND mpr.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(mpr.created_at, '%Y-%m')
ORDER BY month DESC";

$stmt = $conn->prepare($monthly_query);
$stmt->bind_param('i', $landlord_id);
$stmt->execute();
$monthly_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Property-wise Performance
$property_query = "SELECT 
    h.house_no,
    h.location,
    COUNT(mpr.id) as total_payments,
    SUM(CASE WHEN mpr.status = 'completed' THEN mpr.amount ELSE 0 END) as total_received,
    AVG(CASE WHEN mpr.status = 'completed' THEN mpr.amount END) as avg_payment
FROM houses h
LEFT JOIN rental_bookings rb ON h.id = rb.house_id
LEFT JOIN mpesa_payment_requests mpr ON rb.id = mpr.booking_id
WHERE h.landlord_id = ?
GROUP BY h.id, h.house_no, h.location
ORDER BY total_received DESC";

$stmt = $conn->prepare($property_query);
$stmt->bind_param('i', $landlord_id);
$stmt->execute();
$property_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. Recent Payments with Details
$recent_payments_query = "SELECT 
    mpr.*,
    rb.start_date, rb.end_date,
    h.house_no, h.description, h.location, h.price,
    u.name as tenant_name, u.username as tenant_email, u.phone_number as tenant_phone
FROM mpesa_payment_requests mpr
JOIN rental_bookings rb ON mpr.booking_id = rb.id
JOIN houses h ON rb.house_id = h.id
JOIN users u ON rb.user_id = u.id
WHERE h.landlord_id = ?
ORDER BY mpr.created_at DESC
LIMIT 20";

$stmt = $conn->prepare($recent_payments_query);
$stmt->bind_param('i', $landlord_id);
$stmt->execute();
$recent_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 5. Payment Success Rate
$success_rate = $stats['total_payments'] > 0 ? 
    round(($stats['completed_payments'] / $stats['total_payments']) * 100, 2) : 0;

// 6. Top Performing Tenants
$tenant_query = "SELECT 
    u.name as tenant_name,
    u.username as tenant_email,
    COUNT(mpr.id) as payment_count,
    SUM(CASE WHEN mpr.status = 'completed' THEN mpr.amount ELSE 0 END) as total_paid,
    AVG(CASE WHEN mpr.status = 'completed' THEN mpr.amount END) as avg_payment
FROM users u
JOIN rental_bookings rb ON u.id = rb.user_id
JOIN houses h ON rb.house_id = h.id
JOIN mpesa_payment_requests mpr ON rb.id = mpr.booking_id
WHERE h.landlord_id = ?
GROUP BY u.id, u.name, u.username
HAVING total_paid > 0
ORDER BY total_paid DESC
LIMIT 5";

$stmt = $conn->prepare($tenant_query);
$stmt->bind_param('i', $landlord_id);
$stmt->execute();
$top_tenants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 7. Payment Type Analysis
$payment_type_query = "SELECT 
    mpr.payment_type,
    COUNT(*) as count,
    SUM(CASE WHEN mpr.status = 'completed' THEN mpr.amount ELSE 0 END) as total_amount,
    AVG(CASE WHEN mpr.status = 'completed' THEN mpr.amount END) as avg_amount
FROM mpesa_payment_requests mpr
JOIN rental_bookings rb ON mpr.booking_id = rb.id
JOIN houses h ON rb.house_id = h.id
WHERE h.landlord_id = ?
GROUP BY mpr.payment_type";

$stmt = $conn->prepare($payment_type_query);
$stmt->bind_param('i', $landlord_id);
$stmt->execute();
$payment_type_analysis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 8. Daily Payment Trends (Last 30 days)
$daily_query = "SELECT 
    DATE(mpr.created_at) as date,
    COUNT(*) as payment_count,
    SUM(CASE WHEN mpr.status = 'completed' THEN mpr.amount ELSE 0 END) as total_amount
FROM mpesa_payment_requests mpr
JOIN rental_bookings rb ON mpr.booking_id = rb.id
JOIN houses h ON rb.house_id = h.id
WHERE h.landlord_id = ? 
AND mpr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(mpr.created_at)
ORDER BY date DESC";

$stmt = $conn->prepare($daily_query);
$stmt->bind_param('i', $landlord_id);
$stmt->execute();
$daily_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 9. Payment Success Rate by Property
$property_success_query = "SELECT 
    h.house_no,
    h.location,
    COUNT(mpr.id) as total_payments,
    COUNT(CASE WHEN mpr.status = 'completed' THEN 1 END) as completed_payments,
    ROUND((COUNT(CASE WHEN mpr.status = 'completed' THEN 1 END) / COUNT(mpr.id)) * 100, 2) as success_rate
FROM houses h
LEFT JOIN rental_bookings rb ON h.id = rb.house_id
LEFT JOIN mpesa_payment_requests mpr ON rb.id = mpr.booking_id
WHERE h.landlord_id = ? AND mpr.id IS NOT NULL
GROUP BY h.id, h.house_no, h.location
ORDER BY success_rate DESC";

$stmt = $conn->prepare($property_success_query);
$stmt->bind_param('i', $landlord_id);
$stmt->execute();
$property_success_rates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Analytics - Landlord Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .progress-ring {
            width: 120px;
            height: 120px;
        }
        .trend-indicator {
            font-size: 0.8rem;
        }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-neutral { color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
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
                        <li class="nav-item">
                            <a class="nav-link" href="tenants.php">
                                <i class="fas fa-users me-2"></i> Tenants
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="maintenance_requests.php">
                                <i class="fas fa-tools me-2"></i> Maintenance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="scheduled_viewings.php">
                                <i class="fas fa-calendar-alt me-2"></i> Viewings
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-line me-2 text-primary"></i>
                        Payment Analytics Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData()">
                                <i class="fas fa-download me-1"></i> Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshData()">
                                <i class="fas fa-sync-alt me-1"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Date Range</label>
                                        <select class="form-select" id="dateRange">
                                            <option value="30">Last 30 Days</option>
                                            <option value="90">Last 3 Months</option>
                                            <option value="180">Last 6 Months</option>
                                            <option value="365">Last Year</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Payment Status</label>
                                        <select class="form-select" id="statusFilter">
                                            <option value="">All Statuses</option>
                                            <option value="completed">Completed</option>
                                            <option value="pending">Pending</option>
                                            <option value="failed">Failed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Payment Type</label>
                                        <select class="form-select" id="typeFilter">
                                            <option value="">All Types</option>
                                            <option value="initial">Initial</option>
                                            <option value="monthly_payment">Monthly Payment</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" id="searchInput" placeholder="Search tenants, properties...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Key Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Revenue
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            KSh <?php echo number_format($stats['total_received'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Success Rate
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $success_rate; ?>%
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Payments
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_payments']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-credit-card fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Avg Payment
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            KSh <?php echo number_format($stats['avg_payment_amount'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Monthly Trends Chart -->
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Monthly Payment Trends</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Status Distribution -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Payment Status Distribution</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Property Performance & Top Tenants -->
                <div class="row mb-4">
                    <!-- Property Performance -->
                    <div class="col-xl-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Property Performance</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Property</th>
                                                <th>Payments</th>
                                                <th>Revenue</th>
                                                <th>Avg Payment</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($property_performance as $property): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($property['house_no']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($property['location']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $property['total_payments']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-success fw-bold">KSh <?php echo number_format($property['total_received'], 2); ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-muted">KSh <?php echo number_format($property['avg_payment'], 2); ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Tenants -->
                    <div class="col-xl-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Top Performing Tenants</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_tenants)): ?>
                                    <p class="text-muted text-center">No tenant data available</p>
                                <?php else: ?>
                                    <?php foreach ($top_tenants as $tenant): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($tenant['tenant_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($tenant['tenant_email']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-success fw-bold">KSh <?php echo number_format($tenant['total_paid'], 2); ?></div>
                                            <small class="text-muted"><?php echo $tenant['payment_count']; ?> payments</small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Analytics Row -->
                <div class="row mb-4">
                    <!-- Payment Type Analysis -->
                    <div class="col-xl-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Payment Type Analysis</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($payment_type_analysis)): ?>
                                    <p class="text-muted text-center">No payment type data available</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Payment Type</th>
                                                    <th>Count</th>
                                                    <th>Total Amount</th>
                                                    <th>Avg Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($payment_type_analysis as $type): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-<?php echo $type['payment_type'] === 'initial' ? 'primary' : 'info'; ?>">
                                                            <?php echo ucfirst($type['payment_type'] ?? 'monthly_payment'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo $type['count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="text-success fw-bold">KSh <?php echo number_format($type['total_amount'], 2); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted">KSh <?php echo number_format($type['avg_amount'], 2); ?></span>
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

                    <!-- Property Success Rates -->
                    <div class="col-xl-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Property Success Rates</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($property_success_rates)): ?>
                                    <p class="text-muted text-center">No property success data available</p>
                                <?php else: ?>
                                    <?php foreach ($property_success_rates as $property): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($property['house_no']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($property['location']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold"><?php echo $property['success_rate']; ?>%</div>
                                            <small class="text-muted"><?php echo $property['completed_payments']; ?>/<?php echo $property['total_payments']; ?> payments</small>
                                        </div>
                                    </div>
                                    <div class="progress mb-2" style="height: 8px;">
                                        <div class="progress-bar bg-<?php echo $property['success_rate'] >= 80 ? 'success' : ($property['success_rate'] >= 60 ? 'warning' : 'danger'); ?>" 
                                             style="width: <?php echo $property['success_rate']; ?>%"></div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Trends Chart -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Daily Payment Trends (Last 30 Days)</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="dailyTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Payments Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Payments</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_payments)): ?>
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
                                            <th>Payment Type</th>
                                            <th>M-Pesa Receipt</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_payments as $payment): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($payment['tenant_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['tenant_phone'] ?? 'N/A'); ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($payment['house_no']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['location']); ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-success">KSh <?php echo number_format($payment['amount'], 2); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $payment['payment_type'] === 'initial' ? 'primary' : 'info'; ?>">
                                                    <?php echo ucfirst($payment['payment_type'] ?? 'monthly_payment'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($payment['mpesa_receipt_number']): ?>
                                                    <span class="badge bg-secondary">
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
    <script>
        // Monthly Trends Chart
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        const monthlyLabels = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }).reverse();
        const monthlyAmounts = monthlyData.map(item => parseFloat(item.total_amount)).reverse();
        const monthlyCounts = monthlyData.map(item => parseInt(item.payment_count)).reverse();

        const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        new Chart(monthlyTrendsCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Revenue (KSh)',
                    data: monthlyAmounts,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Payment Count',
                    data: monthlyCounts,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (KSh)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Payment Count'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });

        // Status Distribution Chart
        const statusData = {
            completed: <?php echo $stats['completed_payments']; ?>,
            pending: <?php echo $stats['pending_payments']; ?>,
            failed: <?php echo $stats['failed_payments']; ?>
        };

        const statusDistributionCtx = document.getElementById('statusDistributionChart').getContext('2d');
        new Chart(statusDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending', 'Failed'],
                datasets: [{
                    data: [statusData.completed, statusData.pending, statusData.failed],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Daily Trends Chart
        const dailyData = <?php echo json_encode($daily_data); ?>;
        const dailyLabels = dailyData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }).reverse();
        const dailyAmounts = dailyData.map(item => parseFloat(item.total_amount)).reverse();
        const dailyCounts = dailyData.map(item => parseInt(item.payment_count)).reverse();

        const dailyTrendsCtx = document.getElementById('dailyTrendsChart').getContext('2d');
        new Chart(dailyTrendsCtx, {
            type: 'bar',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Revenue (KSh)',
                    data: dailyAmounts,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgb(75, 192, 192)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Payment Count',
                    data: dailyCounts,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    borderColor: 'rgb(255, 99, 132)',
                    borderWidth: 1,
                    yAxisID: 'y1',
                    type: 'line'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (KSh)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Payment Count'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });

        // Export function
        function exportData() {
            // Create CSV content
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Payment Analytics Report\n";
            csvContent += "Generated: " + new Date().toLocaleString() + "\n\n";
            csvContent += "Metric,Value\n";
            csvContent += "Total Revenue,KSh " + <?php echo $stats['total_received']; ?> + "\n";
            csvContent += "Success Rate," + <?php echo $success_rate; ?> + "%\n";
            csvContent += "Total Payments," + <?php echo $stats['total_payments']; ?> + "\n";
            csvContent += "Average Payment,KSh " + <?php echo $stats['avg_payment_amount']; ?> + "\n";
            csvContent += "Pending Amount,KSh " + <?php echo $stats['pending_amount']; ?> + "\n";
            csvContent += "Failed Amount,KSh " + <?php echo $stats['failed_amount']; ?> + "\n\n";
            
            // Add payment type analysis
            csvContent += "Payment Type Analysis\n";
            csvContent += "Type,Count,Total Amount,Average Amount\n";
            <?php foreach ($payment_type_analysis as $type): ?>
            csvContent += "<?php echo ucfirst($type['payment_type'] ?? 'monthly_payment'); ?>,<?php echo $type['count']; ?>,KSh <?php echo number_format($type['total_amount'], 2); ?>,KSh <?php echo number_format($type['avg_amount'], 2); ?>\n";
            <?php endforeach; ?>

            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "payment_analytics_" + new Date().toISOString().split('T')[0] + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Filter and search functionality
        function refreshData() {
            location.reload();
        }

        // Add event listeners for filters
        document.addEventListener('DOMContentLoaded', function() {
            const dateRange = document.getElementById('dateRange');
            const statusFilter = document.getElementById('statusFilter');
            const typeFilter = document.getElementById('typeFilter');
            const searchInput = document.getElementById('searchInput');

            // Add event listeners
            [dateRange, statusFilter, typeFilter].forEach(filter => {
                filter.addEventListener('change', function() {
                    // In a real implementation, this would make an AJAX call to filter data
                    console.log('Filter changed:', this.id, this.value);
                });
            });

            // Search functionality
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const tableRows = document.querySelectorAll('tbody tr');
                
                tableRows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        });

        // Add tooltips and enhance UX
        document.addEventListener('DOMContentLoaded', function() {
            // Add tooltips to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-2px)';
                });
            });
        });
    </script>
</body>
</html> 