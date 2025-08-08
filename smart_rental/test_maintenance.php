<?php
require_once '../config/db.php';

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'maintenance_requests'");
if ($result->num_rows == 0) {
    echo "Table doesn't exist. Creating it...\n";
    
    // Simple table creation
    $sql = "CREATE TABLE maintenance_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        property_id INT NOT NULL,
        booking_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        photo_url VARCHAR(500),
        urgency ENUM('Low','Medium','High') DEFAULT 'Medium',
        status ENUM('Pending','In Progress','Completed','Rejected') DEFAULT 'Pending',
        submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        assigned_repair_date DATETIME,
        assigned_technician VARCHAR(255),
        before_photo_url VARCHAR(500),
        after_photo_url VARCHAR(500),
        rejection_reason TEXT,
        rating INT(1),
        feedback TEXT,
        completion_date DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "Table created successfully!\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
} else {
    echo "Table already exists!\n";
}
?> 