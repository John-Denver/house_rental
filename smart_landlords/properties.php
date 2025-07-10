<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_landlord();

// Handle property addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_property'])) {
    $house_no = $_POST['house_no'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
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
            $stmt = $conn->prepare("INSERT INTO houses (house_no, description, price, location, category_id, status, landlord_id, bedrooms, bathrooms, area) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssdssiiiii', $house_no, $description, $price, $location, $category_id, $status, $_SESSION['user_id'], $bedrooms, $bathrooms, $area);
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
    $location = $_POST['location'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;
    $bedrooms = $_POST['bedrooms'] ?? 0;
    $bathrooms = $_POST['bathrooms'] ?? 0;
    $area = $_POST['area'] ?? 0;
    
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
            $stmt = $conn->prepare("UPDATE houses SET 
                                  house_no = ?, 
                                  description = ?, 
                                  price = ?, 
                                  location = ?, 
                                  category_id = ?, 
                                  status = ?,
                                  bedrooms = ?,
                                  bathrooms = ?,
                                  area = ?
                                  WHERE id = ?");
            $stmt->bind_param('ssdssiiiiii', $house_no, $description, $price, $location, $category_id, $status, $bedrooms, $bathrooms, $area, $property_id);
            $stmt->execute();
            $success = "Property updated successfully";
        } catch (Exception $e) {
            $error = "Error updating property: " . $e->getMessage();
            error_log("Property update error: " . $e->getMessage());
        }
    } else {
        $error = "All fields are required";
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
</head>
<body>
    <?php include('./includes/header.php'); ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="properties.php">
                                <i class="fas fa-home"></i> My Properties
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
                </div>
            </nav>

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

                <!-- Add Property Modal -->
                <div class="modal fade" id="addPropertyModal" tabindex="-1" aria-labelledby="addPropertyModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addPropertyModalLabel">Add New Property</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <p>Please click the "Add Property" button above to add a new property.</p>
                                    <p>This modal is no longer used as we now have a dedicated page for adding properties.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Properties Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($properties as $property): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($property['house_no']); ?></td>
                                <td><?php echo htmlspecialchars($property['category_name']); ?></td>
                                <td>$<?php echo number_format($property['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($property['location']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $property['status'] ? 'success' : 'danger'; ?>">
                                        <?php echo $property['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit-property.php?id=<?php echo $property['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="delete-property.php?id=<?php echo $property['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this property?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
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
</body>
</html>
