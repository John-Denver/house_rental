<?php
// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav id="sidebar" class="sidebar">
    <div class="position-sticky">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'index' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'properties' ? 'active' : ''; ?>" href="properties.php">
                    <i class="fas fa-home"></i> My Properties
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'bookings' ? 'active' : ''; ?>" href="bookings.php">
                    <i class="fas fa-book"></i> Bookings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'tenants' ? 'active' : ''; ?>" href="tenants.php">
                    <i class="fas fa-users"></i> Tenants
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'payments' ? 'active' : ''; ?>" href="payments.php">
                    <i class="fas fa-money-bill"></i> Payments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['maintenance_requests', 'maintenance_history']) ? 'active' : ''; ?>" href="maintenance_requests.php">
                    <i class="fas fa-tools"></i> Maintenance Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'scheduled_viewings' ? 'active' : ''; ?>" href="scheduled_viewings.php">
                    <i class="fas fa-calendar-alt"></i> Scheduled Viewings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'reports' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
        </ul>
    </div>
</nav> 