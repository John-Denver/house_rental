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
                                <td><?php echo htmlspecialchars($property['house_no']); ?></td>
                                <td><?php echo htmlspecialchars($property['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($property['price']); ?></td>
                                <td><?php echo htmlspecialchars($property['location']); ?></td>
                                <td>
                                    <span class="badge <?php echo $property['status'] ? 'bg-success' : 'bg-danger'; ?>">
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPropertyModalLabel">Edit Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editPropertyForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="edit_property" value="1">
                        <input type="hidden" name="property_id" id="editPropertyId">
                        
                        <div class="mb-3">
                            <label for="editHouseNo" class="form-label">Property Name</label>
                            <input type="text" class="form-control" id="editHouseNo" name="house_no" required>
                        </div>

                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editDescription" name="description" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="editPrice" class="form-label">Price</label>
                            <input type="number" step="0.01" class="form-control" id="editPrice" name="price" required>
                        </div>

                        <div class="mb-3">
                            <label for="editLocation" class="form-label">Location</label>
                            <input type="text" class="form-control" id="editLocation" name="location" required>
                        </div>

                        <div class="mb-3">
                            <label for="editLatitude" class="form-label">Latitude</label>
                            <input type="text" class="form-control" id="editLatitude" name="latitude" required>
                        </div>

                        <div class="mb-3">
                            <label for="editLongitude" class="form-label">Longitude</label>
                            <input type="text" class="form-control" id="editLongitude" name="longitude" required>
                        </div>

                        <div class="mb-3">
                            <label for="editCategory" class="form-label">Category</label>
                            <select class="form-select" id="editCategory" name="category_id" required>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="editStatus" name="status" checked>
                                <label class="form-check-label" for="editStatus">
                                    Active
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editBedrooms" class="form-label">Bedrooms</label>
                            <input type="number" class="form-control" id="editBedrooms" name="bedrooms" required>
                        </div>

                        <div class="mb-3">
                            <label for="editBathrooms" class="form-label">Bathrooms</label>
                            <input type="number" class="form-control" id="editBathrooms" name="bathrooms" required>
                        </div>

                        <div class="mb-3">
                            <label for="editArea" class="form-label">Area (sqm)</label>
                            <input type="number" step="0.01" class="form-control" id="editArea" name="area" required>
                        </div>

                        <div class="mb-3">
                            <label for="editTotalUnits" class="form-label">Total Units</label>
                            <input type="number" class="form-control" id="editTotalUnits" name="total_units" min="0" required>
                            <div class="form-text">Enter the total number of units/apartments in this property (0 for no units)</div>
                        </div>

                        <div class="mb-3">
                            <label for="editMainImage" class="form-label">Main Image</label>
                            <input type="file" class="form-control" id="editMainImage" name="main_image">
                            <div class="form-text">Leave empty to keep existing image</div>
                        </div>

                        <div class="mb-3">
                            <label for="editAdditionalMedia" class="form-label">Additional Images</label>
                            <input type="file" class="form-control" id="editAdditionalMedia" name="additional_media[]" multiple>
                            <div class="form-text">Select multiple files to upload</div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
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
        window.editProperty = function(id) {
            const property = <?php echo json_encode($properties); ?>.find(p => p.id == id);
            if (!property) return;

            // Fill form fields
            document.getElementById('editPropertyId').value = property.id;
            document.getElementById('editHouseNo').value = property.house_no;
            document.getElementById('editDescription').value = property.description;
            document.getElementById('editPrice').value = property.price;
            document.getElementById('editLocation').value = property.location;
            document.getElementById('editLatitude').value = property.latitude;
            document.getElementById('editLongitude').value = property.longitude;
            document.getElementById('editCategory').value = property.category_id;
            document.getElementById('editStatus').checked = property.status === 1;
            document.getElementById('editBedrooms').value = property.bedrooms;
            document.getElementById('editBathrooms').value = property.bathrooms;
            document.getElementById('editArea').value = property.area;
            document.getElementById('editTotalUnits').value = property.total_units;

            // Show modal
            editModal.show();
        };

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
            document.getElementById('editPropertyForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Get form data
                const formData = new FormData(this);
                
                try {
                    // Make AJAX request
                    const response = await fetch('edit_property.php', {
                        method: 'POST',
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
                            alert('Error: ' + (data.error || 'Unknown error'));
                        }
                    } catch (parseError) {
                        console.error('Failed to parse JSON:', parseError);
                        console.error('Response text:', text);
                        alert('Error: Invalid response from server. Please check the console for details.');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while saving the property: ' + error.message);
                }
            });
        }

        // Initialize everything when the DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeModals();
            setupDeleteModal();
            setupEditForm();
        });
    </script>
</body>
</html>
