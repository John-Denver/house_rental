<?php
session_start();
require_once 'config/db.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $error = '';

    // Input validation
    if (empty($username)) {
        $error = "Username is required";
    } elseif (empty($password)) {
        $error = "Password is required";
    } else {
        try {
            // Get user from database
            $sql = "SELECT id, name, password, type FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                // Create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_type'] = $user['type'];
                
                // Redirect based on user type
                if ($user['type'] === 'admin') {
                    header('Location: rental/index.php');
                } elseif ($user['type'] === 'landlord') {
                    header('Location: smart_landlords/index.php');
                } else {
                    header('Location: smart_rental/index.php');
                }
                exit;
            } else {
                $error = "Invalid username or password";
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Database error occurred. Please try again later.";
            error_log("Login error: " . $e->getMessage());
        } catch (Exception $e) {
            $error = "An unexpected error occurred. Please try again later.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Rental - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1976D2;
            --dark-blue: #0D47A1;
            --light-blue: #E3F2FD;
            --accent-blue: #2196F3;
            --transition: all 0.3s ease;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--light-blue), white);
            min-height: 100vh;
            display: flex;
            align-items: center;
            overflow-x: hidden;
            animation: gradientShift 15s ease infinite;
            background-size: 200% 200%;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .login-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .login-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .login-left {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            animation: pulse 8s linear infinite;
        }
        
        @keyframes pulse {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .login-right {
            padding: 3rem;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
            transition: var(--transition);
        }
        
        .logo-container:hover {
            transform: scale(1.05);
        }
        
        .logo {
            height: 60px;
            margin-bottom: 1rem;
            filter: drop-shadow(0 2px 5px rgba(0,0,0,0.2));
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.2);
        }
        
        .btn-login {
            background: linear-gradient(to right, var(--primary-blue), var(--accent-blue));
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.4);
        }
        
        .btn-login::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            transform: rotate(30deg);
            transition: var(--transition);
        }
        
        .btn-login:hover::after {
            left: 100%;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        .floating-icons {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .floating-icon {
            position: absolute;
            color: rgba(255,255,255,0.1);
            animation: float 15s linear infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(360deg); opacity: 0; }
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin-top: 2rem;
        }
        
        .feature-list li {
            margin-bottom: 1rem;
            position: relative;
            padding-left: 30px;
            transition: var(--transition);
        }
        
        .feature-list li:hover {
            transform: translateX(5px);
        }
        
        .feature-list li::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            color: var(--accent-blue);
        }
        
        .alert-danger {
            border-radius: 8px;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        
        .forgot-password {
            color: var(--primary-blue);
            transition: var(--transition);
            display: inline-block;
        }
        
        .forgot-password:hover {
            color: var(--dark-blue);
            transform: translateX(3px);
        }
        
        .register-link {
            color: var(--primary-blue);
            font-weight: 600;
            transition: var(--transition);
        }
        
        .register-link:hover {
            color: var(--dark-blue);
            text-decoration: underline;
        }
        
        @media (max-width: 992px) {
            .login-left {
                padding: 2rem;
            }
            .login-right {
                padding: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .login-left {
                display: none;
            }
            .login-right {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="row g-0">
            <!-- Left Side - Welcome Section -->
            <div class="col-lg-6 d-none d-lg-block">
                <div class="login-left h-100">
                    <div class="floating-icons">
                        <i class="fas fa-home floating-icon" style="top: 10%; left: 20%; font-size: 24px; animation-delay: 0s;"></i>
                        <i class="fas fa-key floating-icon" style="top: 30%; left: 70%; font-size: 30px; animation-delay: 2s;"></i>
                        <i class="fas fa-lock floating-icon" style="top: 60%; left: 30%; font-size: 28px; animation-delay: 5s;"></i>
                        <i class="fas fa-user floating-icon" style="top: 80%; left: 60%; font-size: 26px; animation-delay: 7s;"></i>
                    </div>
                    <h2 class="mb-4">Welcome Back!</h2>
                    <p class="mb-4">Log in to access your Smart Rental account and manage your properties.</p>
                    <ul class="feature-list">
                        <li>Manage your rental properties</li>
                        <li>Connect with potential tenants</li>
                        <li>Track payments and bookings</li>
                        <li>Get real-time notifications</li>
                    </ul>
                </div>
            </div>
            
            <!-- Right Side - Login Form -->
            <div class="col-lg-6">
                <div class="login-right h-100">
                    <div class="logo-container">
                        <img src="smart_rental/assets/images/smart_rental_logo.png" 
                             alt="Smart Rental" 
                             class="logo">
                        <h3 class="mb-1">Smart Rental</h3>
                        <p class="text-muted">Don't have an account? <a href="register.php" class="register-link">Register here</a></p>
                    </div>
                    
                    <?php if (isset($error) && !empty($error)): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       required
                                       placeholder="Enter your username">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       required
                                       placeholder="Enter your password">
                            </div>
                        </div>
                        
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input type="checkbox" 
                                       class="form-check-input" 
                                       id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="forgot-password.php" class="forgot-password">
                                <i class="fas fa-question-circle me-1"></i> Forgot Password?
                            </a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-login w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i> Login
                        </button>
                        
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add floating icons dynamically
        document.addEventListener('DOMContentLoaded', function() {
            const floatingIcons = document.querySelector('.floating-icons');
            const icons = ['fa-home', 'fa-key', 'fa-lock', 'fa-user', 'fa-shield-alt', 'fa-building'];
            
            for (let i = 0; i < 10; i++) {
                const icon = document.createElement('i');
                icon.className = `fas ${icons[Math.floor(Math.random() * icons.length)]} floating-icon`;
                icon.style.top = `${Math.random() * 100}%`;
                icon.style.left = `${Math.random() * 100}%`;
                icon.style.fontSize = `${20 + Math.random() * 20}px`;
                icon.style.animationDelay = `${Math.random() * 10}s`;
                icon.style.animationDuration = `${10 + Math.random() * 20}s`;
                floatingIcons.appendChild(icon);
            }
            
            // Add focus effects to form inputs
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('.input-group-text').style.backgroundColor = 'var(--primary-blue)';
                    this.parentElement.querySelector('.input-group-text').style.color = 'white';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.querySelector('.input-group-text').style.backgroundColor = '';
                    this.parentElement.querySelector('.input-group-text').style.color = '';
                });
            });
        });
    </script>
</body>
</html>