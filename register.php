<?php
session_start();
require_once 'config/db.php';

// Handle registration (same as before)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Registration form submitted");
    
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $user_type = isset($_POST['user_type']) ? $_POST['user_type'] : '';
    
    error_log("Form data: " . print_r($_POST, true));
    error_log("Variables: name=$name, username=$username, password=XXX, confirm_password=XXX, user_type=$user_type");
    
    $error = '';
    $success = '';

    if (empty($name)) {
        $error = "Name is required";
    } elseif (empty($username)) {
        $error = "Username is required";
    } elseif (empty($phone)) {
        $error = "Phone number is required";
    } elseif (!preg_match('/^[0-9+\-\s()]{10,20}$/', $phone)) {
        $error = "Please enter a valid phone number (10-20 digits, may include + - ( ) and spaces)";
    } elseif (empty($password)) {
        $error = "Password is required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . $conn->error);
            }
            $stmt->bind_param('s', $username);
            if (!$stmt->execute()) {
                throw new Exception("Error executing statement: " . $stmt->error);
            }
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error = "Username already exists";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, username, phone_number, password, type) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Error preparing insert statement: " . $conn->error);
                }
                $stmt->bind_param('sssss', $name, $username, $phone, $hashed_password, $user_type);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error executing insert: " . $stmt->error);
                }
                
                $success = "Registration successful! You can now login.";
                if ($user_type === 'customer') {
                    header('Location: login.php?redirect=smart_rental');
                } else {
                    header('Location: login.php?redirect=smart_landlords');
                }
                exit;
            }
        } catch (Exception $e) {
            $error = "An error occurred during registration: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-blue: #1a73e8;
            --dark-blue: #0d47a1;
            --light-blue: #e8f0fe;
            --accent-blue: #4285f4;
            --gradient-start: #1a73e8;
            --gradient-end: #0d47a1;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--light-blue), white);
            min-height: 100vh;
        }
        
        .register-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeInUp 0.5s ease-out;
        }
        
        .register-header {
            background: linear-gradient(to right, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .register-header h1 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
            opacity: 0.9;
        }
        
        .register-body {
            padding: 2rem;
        }
        
        .user-type-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            border-bottom: none;
        }
        
        .user-type-tabs .nav-link {
            padding: 1rem 2rem;
            border-radius: 50px;
            margin: 0 0.5rem;
            font-weight: 500;
            color: #555;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            background: #f5f5f5;
        }
        
        .user-type-tabs .nav-link.active {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(26, 115, 232, 0.3);
            border-color: var(--primary-blue);
        }
        
        .user-type-tabs .nav-link i {
            margin-right: 8px;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.2);
        }
        
        .btn-primary {
            background-color: var(--primary-blue);
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 71, 161, 0.3);
        }
        
        .form-floating label {
            color: #666;
        }
        
        .form-floating>.form-control:focus~label {
            color: var(--primary-blue);
        }
        
        .login-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link:hover {
            text-decoration: underline;
        }
        
        .tab-pane {
            animation: fadeIn 0.5s ease-out;
        }
        
        .illustration {
            max-width: 100%;
            height: auto;
            margin-bottom: 1.5rem;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        
        .feature-icon {
            color: var(--primary-blue);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .features-container {
            background-color: var(--light-blue);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .feature-item {
            text-align: center;
            padding: 1rem;
            transition: all 0.3s;
        }
        
        .feature-item:hover {
            transform: translateY(-5px);
        }
        
        .feature-item h5 {
            color: var(--dark-blue);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="register-container animate__animated animate__fadeIn">
            <div class="register-header">
                <h1>Join Smart Rental</h1>
                <p>Create your account and get started today</p>
            </div>
            
            <div class="register-body">
                <!-- User Type Tabs -->
                <ul class="nav nav-tabs user-type-tabs" id="userTypeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="customer-tab" data-bs-toggle="tab" data-bs-target="#customer" type="button" role="tab" aria-controls="customer" aria-selected="true">
                            <i class="fas fa-user me-2"></i>I'm a Customer
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="landlord-tab" data-bs-toggle="tab" data-bs-target="#landlord" type="button" role="tab" aria-controls="landlord" aria-selected="false">
                            <i class="fas fa-home me-2"></i>I'm a Landlord
                        </button>
                    </li>
                </ul>

                <!-- Customer Registration Form -->
                <form method="POST" action="register.php" id="customerForm">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="customer" role="tabpanel" aria-labelledby="customer-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <img src="https://illustrations.popsy.co/amber/digital-nomad.svg" alt="Customer Illustration" class="illustration floating d-none d-md-block">
                                </div>
                                <div class="col-md-6">
                                    <h4 class="mb-4 text-center text-md-start"><i class="fas fa-user-circle me-2"></i>Customer Registration</h4>
                                    
                                    <div class="mb-3">
                                        <label for="customer-name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="customer-name" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer-phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="customer-phone" name="phone" required
                                               placeholder="e.g., +1234567890" 
                                               pattern="[0-9+\-\s()]{10,20}"
                                               title="Please enter a valid phone number (10-20 digits, may include + - ( ) and spaces)">
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer-username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="customer-username" name="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer-password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="customer-password" name="password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer-confirm-password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="customer-confirm-password" name="confirm_password" required>
                                    </div>
                                    
                                    <input type="hidden" name="user_type" value="customer">
                                    
                                    <?php if (isset($error)): ?>
                                        <div class="alert alert-danger animate__animated animate__shakeX"><?php echo htmlspecialchars($error); ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($success)): ?>
                                        <div class="alert alert-success animate__animated animate__fadeIn"><?php echo htmlspecialchars($success); ?></div>
                                    <?php endif; ?>

                                    <button type="submit" class="w-100 btn btn-lg btn-primary mt-3" onclick="return validateCustomerForm()">
                                        Register as Customer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Landlord Registration Form -->
                <form method="POST" action="register.php" id="landlordForm">
                    <div class="tab-content">
                        <div class="tab-pane fade" id="landlord" role="tabpanel" aria-labelledby="landlord-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <img src="https://cdn.prod.website-files.com/5e51c674258ffe10d286d30a/5e5354c88e249320e005285e_peep-32.svg" alt="Landlord Illustration" class="illustration floating d-none d-md-block">
                                </div>
                                <div class="col-md-6">
                                    <h4 class="mb-4 text-center text-md-start"><i class="fas fa-building me-2"></i>Landlord Registration</h4>
                                    
                                    <div class="mb-3">
                                        <label for="landlord-name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="landlord-name" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="landlord-phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="landlord-phone" name="phone" required
                                               placeholder="e.g., +1234567890" 
                                               pattern="[0-9+\-\s()]{10,20}"
                                               title="Please enter a valid phone number (10-20 digits, may include + - ( ) and spaces)">
                                    </div>
                                    <div class="mb-3">
                                        <label for="landlord-username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="landlord-username" name="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="landlord-password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="landlord-password" name="password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="landlord-confirm-password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="landlord-confirm-password" name="confirm_password" required>
                                    </div>
                                    
                                    <input type="hidden" name="user_type" value="landlord">
                                    
                                    <?php if (isset($error)): ?>
                                        <div class="alert alert-danger animate__animated animate__shakeX"><?php echo htmlspecialchars($error); ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($success)): ?>
                                        <div class="alert alert-success animate__animated animate__fadeIn"><?php echo htmlspecialchars($success); ?></div>
                                    <?php endif; ?>

                                    <button type="submit" class="w-100 btn btn-lg btn-primary mt-3" onclick="return validateLandlordForm()">
                                        Register as Landlord
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <p class="mb-0">Already have an account? <a href="login.php" class="login-link">Login here</a></p>
                </div>
                
                <div class="features-container mt-5">
                    <h4 class="text-center mb-4">Why Join Smart Rental?</h4>
                    <div class="row">
                        <div class="col-md-4 feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h5>Secure Platform</h5>
                            <p>Your data is protected with industry-standard security measures</p>
                        </div>
                        <div class="col-md-4 feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <h5>Quick Setup</h5>
                            <p>Get started in minutes with our easy registration process</p>
                        </div>
                        <div class="col-md-4 feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <h5>24/7 Support</h5>
                            <p>Our team is always ready to help you with any questions</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateCustomerForm() {
            const form = document.getElementById('customerForm');
            const phone = form.querySelector('input[name="phone"]').value;
            const password = form.querySelector('input[name="password"]').value;
            const confirmPassword = form.querySelector('input[name="confirm_password"]').value;
            
            const phoneRegex = /^[0-9+\-\s()]{10,20}$/;
            if (!phoneRegex.test(phone)) {
                alert('Please enter a valid phone number (10-20 digits, may include + - ( ) and spaces)');
                return false;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            return true;
        }

        function validateLandlordForm() {
            const form = document.getElementById('landlordForm');
            const phone = form.querySelector('input[name="phone"]').value;
            const password = form.querySelector('input[name="password"]').value;
            const confirmPassword = form.querySelector('input[name="confirm_password"]').value;
            
            const phoneRegex = /^[0-9+\-\s()]{10,20}$/;
            if (!phoneRegex.test(phone)) {
                alert('Please enter a valid phone number (10-20 digits, may include + - ( ) and spaces)');
                return false;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            return true;
        }

        // Add animation to tabs when clicked
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                this.classList.add('animate__animated', 'animate__pulse');
                setTimeout(() => {
                    this.classList.remove('animate__animated', 'animate__pulse');
                }, 1000);
            });
        });
        
        // Add floating animation to form elements on focus
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('animate__animated', 'animate__pulse');
                setTimeout(() => {
                    this.parentElement.classList.remove('animate__animated', 'animate__pulse');
                }, 1000);
            });
        });
    </script>
</body>
</html>