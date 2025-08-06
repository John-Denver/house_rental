<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Handle property addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_property'])) {
    $house_no = $_POST['house_no'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $security_deposit = $_POST['security_deposit'] ?? '';
    $location = $_POST['location'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;
    $bedrooms = $_POST['bedrooms'] ?? 0;
    $bathrooms = $_POST['bathrooms'] ?? 0;
    $area = $_POST['area'] ?? 0;
    
    if (!empty($house_no) && !empty($description) && !empty($price) && !empty($location) && !empty($category_id)) {
        try {
            // First check if the user is a landlord
            if (!is_landlord()) {
                $error = "You must be a landlord to add properties";
                return;
            }

            // Insert the property with landlord_id
            $stmt = $conn->prepare("INSERT INTO houses (house_no, description, price, security_deposit, location, category_id, status, landlord_id, bedrooms, bathrooms, area) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssddsiiiiii', $house_no, $description, $price, $security_deposit, $location, $category_id, $status, $_SESSION['user_id'], $bedrooms, $bathrooms, $area);
            $stmt->execute();
            $success = "Property added successfully";
        } catch (Exception $e) {
            $error = "Error adding property: " . $e->getMessage();
            error_log("Property addition error: " . $e->getMessage());
        }
    } else {
        $error = "All fields are required";
    }
}

// Handle property editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_property'])) {
    $property_id = $_POST['property_id'] ?? '';
    $house_no = $_POST['house_no'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $security_deposit = $_POST['security_deposit'] ?? '';
    $location = $_POST['location'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;
    $bedrooms = $_POST['bedrooms'] ?? 0;
    $bathrooms = $_POST['bathrooms'] ?? 0;
    $area = $_POST['area'] ?? 0;
    $total_units = $_POST['total_units'] ?? 1;
    $available_units = $_POST['available_units'] ?? 1;
    
    // Handle image upload
    $main_image = null;
    $upload_debug = [];
    
    if (isset($_FILES['main_image'])) {
        $upload_debug['file_exists'] = true;
        $upload_debug['file_error'] = $_FILES['main_image']['error'];
        $upload_debug['file_name'] = $_FILES['main_image']['name'] ?? 'not set';
        $upload_debug['file_size'] = $_FILES['main_image']['size'] ?? 'not set';
        
        if ($_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            $file_extension = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('property_') . '.' . $file_extension;
            $upload_path = $upload_dir . $file_name;
            
            $upload_debug['upload_dir'] = $upload_dir;
            $upload_debug['file_extension'] = $file_extension;
            $upload_debug['file_name'] = $file_name;
            $upload_debug['upload_path'] = $upload_path;
            $upload_debug['dir_exists'] = is_dir($upload_dir);
            $upload_debug['dir_writable'] = is_writable($upload_dir);
            
            if (move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_path)) {
                $main_image = $file_name;
                $upload_debug['upload_success'] = true;
            } else {
                $upload_debug['upload_success'] = false;
                $upload_debug['upload_error'] = error_get_last();
            }
        }
    } else {
        $upload_debug['file_exists'] = false;
    }
    
    if (!empty($property_id) && !empty($house_no) && !empty($description) && !empty($price) && !empty($location) && !empty($category_id)) {
        try {
            // First check if the user owns this property
            $stmt = $conn->prepare("SELECT * FROM houses WHERE id = ? AND landlord_id = ?");
            $stmt->bind_param('ii', $property_id, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $property = $result->fetch_assoc();

            if (!$property) {
                $error = "You can only edit your own properties";
                return;
            }

            // Update the property
            if ($main_image) {
                // New image uploaded - update with new image
                $stmt = $conn->prepare("UPDATE houses SET 
                                      house_no = ?, 
                                      description = ?, 
                                      price = ?, 
                                      security_deposit = ?,
                                      location = ?, 
                                      category_id = ?, 
                                      status = ?,
                                      bedrooms = ?,
                                      bathrooms = ?,
                                      area = ?,
                                      total_units = ?,
                                      available_units = ?,
                                      main_image = ?
                                      WHERE id = ?");
                $stmt->bind_param('ssddsiiiiiiiss', $house_no, $description, $price, $security_deposit, $location, $category_id, $status, $bedrooms, $bathrooms, $area, $total_units, $available_units, $main_image, $property_id);
            } else {
                // No new image - keep existing image
                $stmt = $conn->prepare("UPDATE houses SET 
                                      house_no = ?, 
                                      description = ?, 
                                      price = ?, 
                                      security_deposit = ?,
                                      location = ?, 
                                      category_id = ?, 
                                      status = ?,
                                      bedrooms = ?,
                                      bathrooms = ?,
                                      area = ?,
                                      total_units = ?,
                                      available_units = ?
                                      WHERE id = ?");
                $stmt->bind_param('ssddsiiiiiiii', $house_no, $description, $price, $security_deposit, $location, $category_id, $status, $bedrooms, $bathrooms, $area, $total_units, $available_units, $property_id);
            }
            $stmt->execute();
            
            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                // Return JSON response for AJAX
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Property updated successfully',
                    'debug' => [
                        'main_image' => $main_image,
                        'upload_debug' => $upload_debug
                    ]
                ]);
                exit;
            } else {
                // Regular form submission
                $success = "Property updated successfully";
            }
        } catch (Exception $e) {
            $errorMessage = "Error updating property: " . $e->getMessage();
            error_log("Property update error: " . $e->getMessage());
            
            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                // Return JSON response for AJAX
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $errorMessage,
                    'debug' => [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
                ]);
                exit;
            } else {
                // Regular form submission
                $error = $errorMessage;
            }
        }
    } else {
        $errorMessage = "All fields are required";
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // Return JSON response for AJAX
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $errorMessage
            ]);
            exit;
        } else {
            // Regular form submission
            $error = $errorMessage;
        }
    }
}

// Get landlord's properties
$stmt = $conn->prepare("SELECT h.*, c.name as category_name 
                       FROM houses h 
                       LEFT JOIN categories c ON h.category_id = c.id 
                       WHERE h.landlord_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for dropdown
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Properties - Smart Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Enhanced Modal Styles */
        .modal-xl {
            max-width: 1200px;
        }
        
        .section-title {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }
        
        .step-progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        
        .step.active:not(:last-child)::after {
            background: #0d6efd;
        }
        
        .step span:first-child {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #dee2e6;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            position: relative;
            z-index: 2;
        }
        
        .step.active span:first-child {
            background: #0d6efd;
            color: white;
        }
        
        .step-label {
            margin-top: 5px;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
        }
        
        .step.active .step-label {
            color: #0d6efd;
            font-weight: 600;
        }
        
        .form-label.fw-bold {
            color: #495057;
            font-size: 14px;
        }
        
        .input-group-text {
            background: #f8f9fa;
            border-color: #ced4da;
            color: #6c757d;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .modal-header.bg-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%) !important;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            border: none;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0b5ed7 0%, #0a58ca 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
        }
        
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .img-thumbnail {
            border: 2px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .img-thumbnail:hover {
            border-color: #0d6efd;
            transform: scale(1.05);
        }
        
        .badge {
            font-size: 10px;
            padding: 4px 8px;
        }
        
        .modal-footer.bg-light {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <?php include('./includes/header.php'); ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include('./includes/sidebar.php'); ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Properties</h1>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center">
                    <h2>My Properties</h2>
                    <a href="add_property.php" class="btn btn-primary">
                        Add Property
                    </a>
                </div>



                <!-- Search and Filter -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search properties...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="btn-group float-end">
                            <button class="btn btn-outline-primary" onclick="sortTable(0)">Property</button>
                            <button class="btn btn-outline-primary" onclick="sortTable(2)">Price</button>
                            <button class="btn btn-outline-primary" onclick="sortTable(3)">Location</button>
                        </div>
                    </div>
                </div>

                <!-- Properties Table -->
                <div class="table-responsive">
                    <table class="table table-hover" id="propertiesTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Image</th>
                            <th>Property Name</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Location</th>
                            <th>Status</th>
                                <th>Units</th>
                            <th>Coordinates</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($properties as $property): ?>
                            <tr>
                                <td>
                                    <?php
                                    $imagePath = '../uploads/' . $property['main_image'];
                                    if (file_exists($imagePath)) {
                                        echo '<img src="' . htmlspecialchars($imagePath) . '" 
                                              alt="' . htmlspecialchars($property['house_no']) . '" 
                                              class="property-image" 
                                              style="width: 50px; height: 50px; object-fit: cover;">';
                                    } else {
                                        echo '<div class="text-muted">No image available</div>';
                                        error_log("Image not found: $imagePath for property {$property['id']}");
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($property['house_no']); ?></td>
                                <td><?php echo htmlspecialchars($property['category_name']); ?></td>
                                <td>Ksh. <?php echo number_format($property['price']); ?>/month</td>
                                <td><?php echo htmlspecialchars($property['location']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $property['status'] ? 'success' : 'danger'; ?>">
                                        <?php echo $property['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $property['available_units'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo htmlspecialchars($property['available_units'] . '/' . $property['total_units']); ?>
                                    </span>
                                    <?php if ($property['available_units'] <= 0): ?>
                                        <span class="badge bg-secondary ms-2">UNAVAILABLE</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($property['latitude'] . ', ' . $property['longitude']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-primary" onclick="editProperty(<?php echo $property['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deletePropertyModal" data-property-id="<?php echo $property['id']; ?>" data-property-name="<?php echo htmlspecialchars($property['house_no']); ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Table sorting
        function sortTable(n) {
            const table = document.getElementById("propertiesTable");
            let switching = true;
            let shouldSwitch;
            let switchcount = 0;
            let direction = "asc";

            while (switching) {
                switching = false;
                const rows = table.rows;

                for (let i = 1; i < (rows.length - 1); i++) {
                    shouldSwitch = false;
                    const x = rows[i].getElementsByTagName("TD")[n];
                    const y = rows[i + 1].getElementsByTagName("TD")[n];

                    if (n === 2) { // Price column
                        if (parseFloat(x.innerHTML) > parseFloat(y.innerHTML)) {
                            shouldSwitch = true;
                            break;
                        }
                    } else {
                        if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                            shouldSwitch = true;
                            break;
                        }
                    }
                }

                if (shouldSwitch) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    switchcount++;
                } else {
                    if (switchcount === 0 && direction === "asc") {
                        direction = "desc";
                        switching = true;
                    }
                }
            }
        }

        // Search functionality
        document.getElementById("searchInput").addEventListener("input", function() {
            const filter = this.value.toLowerCase();
            const table = document.getElementById("propertiesTable");
            const rows = table.getElementsByTagName("tr");

            for (let i = 1; i < rows.length; i++) {
                const cols = rows[i].getElementsByTagName("td");
                let found = false;

                for (let j = 0; j < cols.length; j++) {
                    const text = cols[j].textContent || cols[j].innerText;
                    if (text.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }

                rows[i].style.display = found ? "" : "none";
            }
        });

        // Handle form submission
        document.getElementById('propertyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(this);
            
            // Validate required fields
            const requiredFields = ['house_no', 'description', 'category_id', 'price', 'location'];
            let isValid = true;
            let missingFields = [];
            let fieldValues = {};
            
            // Debug output to console
            console.log('Form data:', formData);
            
            // Check each required field
            for (const field of requiredFields) {
                const element = document.querySelector(`[name="${field}"]`);
                if (!element) {
                    console.error(`Field not found: ${field}`);
                    continue;
                }
                
                let value;
                if (element.type === 'file') {
                    value = element.files[0];
                } else {
                    value = element.value;
                }
                
                fieldValues[field] = value;
                
                if (!value || (typeof value === 'string' && value.trim() === '')) {
                    isValid = false;
                    missingFields.push(field);
                    console.log(`Missing value for field: ${field}`);
                }
            }
            
            // Check if category is selected
            const categorySelect = document.getElementById('property_category');
            if (categorySelect && categorySelect.value === '') {
                isValid = false;
                missingFields.push('category_id');
                console.log('Category not selected');
            }
            
            // Check if main image is selected
            const mainImageInput = document.getElementById('property_main_image');
            if (!mainImageInput || !mainImageInput.files[0]) {
                isValid = false;
                missingFields.push('main_image');
                console.log('Main image not selected');
            }
            
            if (!isValid) {
                let message = 'Please fill in the following required fields:\n';
                missingFields.forEach(field => {
                    message += '- ' + field.replace('_', ' ').toUpperCase() + '\n';
                });
                
                // Show detailed error message
                alert(message + '\n\nPlease check the console for more details.');
                console.error('Missing fields:', missingFields);
                console.error('Field values:', fieldValues);
                return;
            }
            
            // Submit the form
            fetch('process_property.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Close modal and refresh property list
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addPropertyModal'));
                    modal.hide();
                    window.location.reload();
                } else {
                    console.error('Server error:', data.message);
                    alert(data.message || 'Failed to add property');
                }
            })
            .catch(error => {
                console.error('Network error:', error);
                alert('An error occurred while saving the property');
            });
        });

        // Add event listener for file input change to show selected files
        document.getElementById('property_main_image').addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                document.getElementById('property_main_image').nextElementSibling.textContent = 
                    `Selected file: ${files[0].name}`;
            }
        });

        document.getElementById('property_additional_media').addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                document.getElementById('property_additional_media').nextElementSibling.textContent = 
                    `Selected ${files.length} files`;
            }
        });
    </script>
    <script src="assets/js/main.js"></script>

    <!-- Edit Property Modal -->
    <div class="modal fade" id="editPropertyModal" tabindex="-1" aria-labelledby="editPropertyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editPropertyModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Property
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="editPropertyForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="edit_property" value="1">
                        <input type="hidden" name="property_id" id="editPropertyId">
                        
                        <!-- Progress Indicator -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="step-progress d-flex">
                                    <div class="step active">
                                        <span>1</span>
                                        <span class="step-label">Basic Info</span>
                                    </div>
                                    <div class="step">
                                        <span>2</span>
                                        <span class="step-label">Details</span>
                                    </div>
                                    <div class="step">
                                        <span>3</span>
                                        <span class="step-label">Location</span>
                                    </div>
                                    <div class="step">
                                        <span>4</span>
                                        <span class="step-label">Media</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Basic Information Section -->
                        <div class="form-section mb-4">
                            <h5 class="section-title text-primary">
                                <i class="fas fa-info-circle me-2"></i>Basic Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="editHouseNo" class="form-label fw-bold">Property Name</label>
                                    <input type="text" class="form-control" id="editHouseNo" name="house_no" required placeholder="e.g., Luxury Apartment">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="editCategory" class="form-label fw-bold">Category</label>
                                    <select class="form-select" id="editCategory" name="category_id" required>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="editDescription" class="form-label fw-bold">Description</label>
                                <textarea class="form-control" id="editDescription" name="description" rows="4" required placeholder="Describe your property in detail..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Property Details Section -->
                        <div class="form-section mb-4">
                            <h5 class="section-title text-primary">
                                <i class="fas fa-list-ul me-2"></i>Property Details
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="editPrice" class="form-label fw-bold">Monthly Price (Ksh)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Ksh</span>
                                        <input type="number" class="form-control" id="editPrice" name="price" required placeholder="e.g., 50000">
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="editSecurityDeposit" class="form-label fw-bold">Security Deposit (Ksh)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Ksh</span>
                                        <input type="number" class="form-control" id="editSecurityDeposit" name="security_deposit" placeholder="e.g., 50000">
                                    </div>
                                    <small class="form-text text-muted">Leave empty to use monthly price as default</small>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="editBedrooms" class="form-label fw-bold">Bedrooms</label>
                                    <input type="number" class="form-control" id="editBedrooms" name="bedrooms" required placeholder="e.g., 3">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="editBathrooms" class="form-label fw-bold">Bathrooms</label>
                                    <input type="number" class="form-control" id="editBathrooms" name="bathrooms" required placeholder="e.g., 2">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="editArea" class="form-label fw-bold">Area (sqm)</label>
                                    <input type="number" step="0.01" class="form-control" id="editArea" name="area" required placeholder="e.g., 120">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="editStatus" name="status" checked>
                                        <label class="form-check-label" for="editStatus">
                                            Available for Rent
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="editTotalUnits" class="form-label fw-bold">Total Units</label>
                                    <input type="number" class="form-control" id="editTotalUnits" name="total_units" min="1" required>
                                    <small class="form-text text-muted">Enter the total number of units/apartments in this property</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="editAvailableUnits" class="form-label fw-bold">Available Units</label>
                                    <input type="number" class="form-control" id="editAvailableUnits" name="available_units" min="0" required>
                                    <small class="form-text text-muted">Enter the number of available units (should be less than or equal to total units)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Location Section -->
                        <div class="form-section mb-4">
                            <h5 class="section-title text-primary">
                                <i class="fas fa-map-marker-alt me-2"></i>Location Details
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="editLocation" class="form-label fw-bold">Location</label>
                                    <input type="text" class="form-control" id="editLocation" name="location" required placeholder="e.g., Nairobi West">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="editLatitude" class="form-label fw-bold">Latitude</label>
                                    <input type="text" class="form-control" id="editLatitude" name="latitude" required placeholder="e.g., -1.2921">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="editLongitude" class="form-label fw-bold">Longitude</label>
                                    <input type="text" class="form-control" id="editLongitude" name="longitude" required placeholder="e.g., 36.8219">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Media Section -->
                        <div class="form-section mb-4">
                            <h5 class="section-title text-primary">
                                <i class="fas fa-images me-2"></i>Media & Images
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="editMainImage" class="form-label fw-bold">Main Image</label>
                                    <input type="file" class="form-control" id="editMainImage" name="main_image" accept="image/*">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Current image: <span id="currentImage" class="fw-bold text-primary"></span>
                                    </div>
                                    <div id="currentImagePreview" class="mt-2"></div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="editAdditionalMedia" class="form-label fw-bold">Additional Images</label>
                                    <input type="file" class="form-control" id="editAdditionalMedia" name="additional_media[]" multiple accept="image/*">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Select multiple images to add to the gallery
                                    </div>
                                    <div id="currentAdditionalImages" class="mt-2"></div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Property Confirmation Modal -->
    <div class="modal fade" id="deletePropertyModal" tabindex="-1" aria-labelledby="deletePropertyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePropertyModalLabel">Delete Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the property "<span id="deletePropertyName"></span>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete Property</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let editModal;
        let deleteModal;

        // Initialize modals
        function initializeModals() {
            editModal = new bootstrap.Modal(document.getElementById('editPropertyModal'));
            deleteModal = new bootstrap.Modal(document.getElementById('deletePropertyModal'));
        }

        // Edit Property Modal
        function editProperty(id) {
            const property = <?php echo json_encode($properties); ?>.find(p => p.id == id);
            if (!property) return;

            // Fill form fields
            const form = document.getElementById('editPropertyForm');
            if (!form) return;

            try {
                // Reset step progress to first step
                const steps = form.querySelectorAll('.step');
                steps.forEach((step, index) => {
                    if (index === 0) {
                        step.classList.add('active');
                    } else {
                        step.classList.remove('active');
                    }
                });
                form.querySelector('#editPropertyId').value = property.id;
                form.querySelector('#editHouseNo').value = property.house_no;
                form.querySelector('#editDescription').value = property.description;
                form.querySelector('#editPrice').value = property.price;
                form.querySelector('#editLocation').value = property.location;
                form.querySelector('#editLatitude').value = property.latitude;
                form.querySelector('#editLongitude').value = property.longitude;
                form.querySelector('#editCategory').value = property.category_id;
                form.querySelector('#editStatus').checked = property.status === 1;
                form.querySelector('#editBedrooms').value = property.bedrooms;
                form.querySelector('#editBathrooms').value = property.bathrooms;
                form.querySelector('#editArea').value = property.area;
                form.querySelector('#editTotalUnits').value = property.total_units;
                form.querySelector('#editAvailableUnits').value = property.available_units;
                form.querySelector('#editSecurityDeposit').value = property.security_deposit || '';
                
                // Show current image
                const currentImage = form.querySelector('#currentImage');
                const currentImagePreview = form.querySelector('#currentImagePreview');
                if (currentImage) {
                    currentImage.textContent = property.main_image || 'No image';
                }
                
                // Show current image preview
                if (currentImagePreview && property.main_image) {
                    currentImagePreview.innerHTML = `
                        <div class="d-inline-block position-relative">
                            <img src="../uploads/${property.main_image}" class="img-thumbnail" style="max-height: 100px; max-width: 150px;">
                            <span class="badge bg-primary position-absolute top-0 start-100 translate-middle">
                                Current
                            </span>
                        </div>
                    `;
                } else if (currentImagePreview) {
                    currentImagePreview.innerHTML = '<span class="text-muted">No image uploaded</span>';
                }
                
                // Show current additional images
                const currentImagesDiv = form.querySelector('#currentAdditionalImages');
                if (currentImagesDiv) {
                    currentImagesDiv.innerHTML = '';
                    if (property.additional_images) {
                        property.additional_images.forEach(img => {
                            const imgEl = document.createElement('div');
                            imgEl.className = 'current-image';
                            imgEl.textContent = img;
                            currentImagesDiv.appendChild(imgEl);
                        });
                    }
                }

                // Show modal
                editModal.show();
            } catch (error) {
                console.error('Error setting form values:', error);
                alert('Error: Unable to edit property. Please refresh the page and try again.');
            }
        }

        // Delete Property Confirmation
        function setupDeleteModal() {
            document.getElementById('deletePropertyModal').addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const propertyId = button.getAttribute('data-property-id');
                const propertyName = button.getAttribute('data-property-name');
                
                // Update modal content
                document.getElementById('deletePropertyName').textContent = propertyName;
                
                // Set up delete button
                const deleteButton = document.getElementById('confirmDelete');
                deleteButton.onclick = function() {
                    // Make AJAX request to delete property
                    fetch('delete_property.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'property_id=' + encodeURIComponent(propertyId)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Close modal
                            deleteModal.hide();
                            // Refresh the page
                            location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the property: ' + error.message);
                    });
                };
            });
        }

        // Handle form submission
        function setupEditForm() {
            const form = document.getElementById('editPropertyForm');
            const modalBody = document.querySelector('#editPropertyModal .modal-body');
            const modalFooter = document.querySelector('#editPropertyModal .modal-footer');
            
            // Add step navigation functionality
            const steps = form.querySelectorAll('.step');
            steps.forEach((step, index) => {
                step.addEventListener('click', () => {
                    // Update active step
                    steps.forEach(s => s.classList.remove('active'));
                    step.classList.add('active');
                    
                    // Scroll to corresponding section
                    const sections = form.querySelectorAll('.form-section');
                    if (sections[index]) {
                        sections[index].scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
            
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Clear any existing error messages
                const errorDiv = modalBody.querySelector('.error-message');
                if (errorDiv) errorDiv.remove();
                
                // Add loading state
                const submitButton = form.querySelector('button[type="submit"]');
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.textContent = 'Saving...';
                
                // Get form data
                const formData = new FormData(this);
                
                try {
                    // Make AJAX request to the same page
                    const response = await fetch('properties.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error('Server returned status: ' + response.status);
                    }

                    const text = await response.text();
                    console.log('Response text:', text); // Log the raw response

                    try {
                        const data = JSON.parse(text);
                        
                        if (data.success) {
                            // Close modal
                            editModal.hide();
                            // Refresh the page
                            location.reload();
                        } else {
                            // Show error in modal with debug info
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'alert alert-danger error-message mt-3';
                            
                            const errorContent = document.createElement('div');
                            errorContent.innerHTML = `
                                <h6>Error: ${data.error || 'Unknown error'}</h6>
                                <pre>${JSON.stringify(data.debug || {}, null, 2)}</pre>
                            `;
                            
                            errorDiv.appendChild(errorContent);
                            modalBody.insertBefore(errorDiv, modalFooter);
                            
                            // Re-enable submit button
                            submitButton.disabled = false;
                            submitButton.textContent = originalText;
                        }
                    } catch (parseError) {
                        console.error('Failed to parse JSON:', parseError);
                        console.error('Response text:', text);
                        
                        // Show error in modal
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger error-message mt-3';
                        errorDiv.textContent = 'Error: Invalid response from server. Please check the console for details.';
                        modalBody.insertBefore(errorDiv, modalFooter);
                        
                        // Re-enable submit button
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    
                    // Show error in modal
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger error-message mt-3';
                    errorDiv.textContent = 'Error: ' + error.message;
                    modalBody.insertBefore(errorDiv, modalFooter);
                    
                    // Re-enable submit button
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            });
        }

        // Initialize everything when the DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            window.editProperty = editProperty; // Make it global
            initializeModals();
            setupDeleteModal();
            setupEditForm();
        });
    </script>
</body>
</html>
