<?php
require_once '../config/db.php';

echo "Creating maintenance_requests table...\n";

// Create the table
$create_table_sql = "
CREATE TABLE IF NOT EXISTS `maintenance_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL COMMENT 'User ID of the tenant',
  `property_id` int(11) NOT NULL COMMENT 'House ID',
  `booking_id` int(11) NOT NULL COMMENT 'Rental booking ID',
  `title` varchar(255) NOT NULL COMMENT 'Short title of the issue',
  `description` text NOT NULL COMMENT 'Detailed description of the issue',
  `photo_url` varchar(500) DEFAULT NULL COMMENT 'Optional photo of the issue',
  `urgency` enum('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  `status` enum('Pending','In Progress','Completed','Rejected') NOT NULL DEFAULT 'Pending',
  `submission_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_repair_date` datetime DEFAULT NULL COMMENT 'Date when repair is scheduled',
  `assigned_technician` varchar(255) DEFAULT NULL COMMENT 'Name of assigned technician',
  `before_photo_url` varchar(500) DEFAULT NULL COMMENT 'Photo before repair',
  `after_photo_url` varchar(500) DEFAULT NULL COMMENT 'Photo after repair',
  `rejection_reason` text DEFAULT NULL COMMENT 'Reason if request is rejected',
  `rating` int(1) DEFAULT NULL COMMENT 'Star rating 1-5 after completion',
  `feedback` text DEFAULT NULL COMMENT 'Optional feedback after completion',
  `completion_date` datetime DEFAULT NULL COMMENT 'Date when work was completed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_property_id` (`property_id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_status` (`status`),
  KEY `idx_urgency` (`urgency`),
  KEY `idx_submission_date` (`submission_date`),
  FOREIGN KEY (`tenant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`property_id`) REFERENCES `houses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($create_table_sql)) {
    echo "✅ Table created successfully!\n";
} else {
    echo "❌ Error creating table: " . $conn->error . "\n";
}

// Insert sample data
$insert_sample_sql = "
INSERT INTO `maintenance_requests` (`tenant_id`, `property_id`, `booking_id`, `title`, `description`, `urgency`, `status`, `submission_date`) VALUES
(3, 1, 36, 'Leaking Kitchen Sink', 'The kitchen sink has been leaking for the past few days. Water is dripping from under the cabinet.', 'High', 'Pending', NOW()),
(3, 1, 36, 'Broken Window Lock', 'The window lock in the bedroom is not working properly. Cannot secure the window.', 'Medium', 'In Progress', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 1, 36, 'Electrical Outlet Not Working', 'The electrical outlet in the living room stopped working yesterday.', 'High', 'Completed', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 1, 36, 'Hot Water Not Working', 'No hot water coming from the shower. Only cold water available.', 'High', 'Pending', DATE_SUB(NOW(), INTERVAL 1 DAY));
";

if ($conn->query($insert_sample_sql)) {
    echo "✅ Sample data inserted successfully!\n";
} else {
    echo "❌ Error inserting sample data: " . $conn->error . "\n";
}

echo "Setup completed!\n";
?> 