<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="fas fa-home me-2"></i>Smart Rental
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active fw-bold' : ''; ?>" 
                       href="index.php">
                        <i class="fas fa-search me-1"></i> Browse Properties
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'login.php' ? 'active fw-bold' : ''; ?>" 
                       href="login.php">
                        <i class="fas fa-sign-in-alt me-1"></i> Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-primary text-white ms-2 px-3 <?php echo $current_page === 'register.php' ? 'active' : ''; ?>" 
                       href="register.php">
                        <i class="fas fa-user-plus me-1"></i> Register
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
.navbar {
    padding: 0.8rem 0;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}
.navbar-brand {
    font-size: 1.5rem;
    color: var(--primary-blue);
}
.nav-link {
    padding: 0.5rem 1rem;
    color: #4a5568;
    transition: all 0.3s ease;
    border-radius: 4px;
    margin: 0 0.2rem;
}
.nav-link:hover {
    color: var(--primary-blue);
    background-color: rgba(26, 115, 232, 0.05);
}
.nav-link.active {
    color: var(--primary-blue);
    font-weight: 500;
}
.navbar-toggler {
    border: none;
    padding: 0.5rem;
}
.navbar-toggler:focus {
    box-shadow: none;
    outline: none;
}
</style>
