<?php
require_once '../config/db.php';
require_once '../config/auth.php';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="./assets/images/smart_rental_logo.png" alt="Smart Rental" height="40" class="me-2">
            <span class="navbar-text">Smart Rental</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="properties.php">
                        <i class="fas fa-building"></i> Properties
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-book"></i> Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tenants.php">
                        <i class="fas fa-users"></i> Tenants
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav">
                <?php if (is_landlord()): ?>
                    <li class="nav-item">
                        <span class="nav-link me-2">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-primary" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>
                            Logout
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
