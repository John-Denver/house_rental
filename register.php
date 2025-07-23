<?php
session_start();
require_once 'config/db.php';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Registration form submitted");
    
    // Get POST data
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $user_type = isset($_POST['user_type']) ? $_POST['user_type'] : '';
    
    // Debug log
    error_log("Form data: " . print_r($_POST, true));
    error_log("Variables: name=$name, username=$username, password=XXX, confirm_password=XXX, user_type=$user_type");
    
    $error = '';
    $success = '';

    // Input validation
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
            // Check if username already exists
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
                // Insert new user
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
                // Redirect to appropriate dashboard after login
                if ($user_type === 'customer') {
                    header('Location: login.php?redirect=smart_rental');
                } else {
                    header('Location: login.php?redirect=smart_landlords');
                    $success = "Registration successful! You can now login.";
                    // Redirect to appropriate dashboard after login
                    if ($user_type === 'customer') {
                        header('Location: login.php?redirect=smart_rental');
                    } else {
                        header('Location: login.php?redirect=smart_landlords');
                    }
                    exit;
                }
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
    <style>
        .register-container {
            max-width: 500px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .user-type-tabs {
            margin-bottom: 2rem;
        }
        .user-type-tabs .nav-link {
            padding: 0.5rem 1.5rem;
        }
        .user-type-tabs .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        .user-type-tabs .nav-link {
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="register-container">
                    <div class="text-center mb-4">
                        <h1 class="h3 mb-3 fw-normal">Register</h1>
                        <p class="text-muted">Create your account as a Customer or Landlord</p>
                    </div>

                    <!-- User Type Tabs -->
                    <ul class="nav nav-tabs user-type-tabs" id="userTypeTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="customer-tab" data-bs-toggle="tab" data-bs-target="#customer" type="button" role="tab" aria-controls="customer" aria-selected="true">
                                <i class="fas fa-user me-2"></i>Customer
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="landlord-tab" data-bs-toggle="tab" data-bs-target="#landlord" type="button" role="tab" aria-controls="landlord" aria-selected="false">
                                <i class="fas fa-home me-2"></i>Landlord
                            </button>
                        </li>
                    </ul>

                    <!-- Customer Registration Form -->
                    <form method="POST" action="register.php" class="mt-4" id="customerForm">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="customer" role="tabpanel" aria-labelledby="customer-tab">
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
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>
                                <?php if (isset($success)): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                                <?php endif; ?>

                                <button type="submit" class="w-100 btn btn-lg btn-primary" onclick="return validateCustomerForm()">
                                    Register as Customer
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Landlord Registration Form -->
                    <form method="POST" action="register.php" class="mt-4" id="landlordForm">
                        <div class="tab-content">
                            <div class="tab-pane fade" id="landlord" role="tabpanel" aria-labelledby="landlord-tab">
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
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>
                                <?php if (isset($success)): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                                <?php endif; ?>

                                <button type="submit" class="w-100 btn btn-lg btn-primary" onclick="return validateLandlordForm()">
                                    Register as Landlord
                                </button>
                            </div>
                        </div>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <!-- <button class="w-100 btn btn-lg btn-primary" type="submit" onclick="return validateForm()">Register</button> -->
                    </form>
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
            
            // Validate phone number format
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
            
            // Validate phone number format
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

    // Add event listener for tab change
    document.addEventListener('DOMContentLoaded', function() {
        const tabLinks = document.querySelectorAll('.nav-link');
        tabLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Reset validation on tab change
                document.querySelectorAll('.is-invalid').forEach(field => {
                    field.classList.remove('is-invalid');
                });
            });
        });
    });
    </script>

                    <div class="text-center mt-3">
                        <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
