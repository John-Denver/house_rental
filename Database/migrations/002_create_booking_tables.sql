-- Create rental_bookings table
CREATE TABLE IF NOT EXISTS `rental_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Tenant ID',
  `landlord_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `rental_period` int(11) NOT NULL COMMENT 'In months',
  `total_amount` decimal(10,2) NOT NULL,
  `security_deposit` decimal(10,2) NOT NULL,
  `additional_fees` decimal(10,2) DEFAULT 0.00,
  `special_requests` text DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','completed','rejected') NOT NULL DEFAULT 'pending',
  `payment_status` enum('pending','partial','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` enum('tenant','landlord','admin') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `house_id` (`house_id`),
  KEY `user_id` (`user_id`),
  KEY `landlord_id` (`landlord_id`),
  CONSTRAINT `rental_bookings_ibfk_1` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rental_bookings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rental_bookings_ibfk_3` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create booking_payments table
CREATE TABLE IF NOT EXISTS `booking_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_method` enum('mpesa','bank_transfer','credit_card','cash','other') NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `booking_payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create booking_documents table
CREATE TABLE IF NOT EXISTS `booking_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL COMMENT 'e.g., id_proof, income_proof, contract',
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `review_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `booking_documents_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create booking_reviews table
CREATE TABLE IF NOT EXISTS `booking_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `title` varchar(255) NOT NULL,
  `review` text NOT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `user_id` (`user_id`),
  KEY `property_id` (`property_id`),
  CONSTRAINT `booking_reviews_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_reviews_ibfk_3` FOREIGN KEY (`property_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add booking related columns to houses table if they don't exist
ALTER TABLE `houses` 
ADD COLUMN IF NOT EXISTS `security_deposit` DECIMAL(10,2) DEFAULT NULL AFTER `price`,
ADD COLUMN IF NOT EXISTS `min_rental_period` INT(11) DEFAULT 1 COMMENT 'In months' AFTER `security_deposit`,
ADD COLUMN IF NOT EXISTS `max_rental_period` INT(11) DEFAULT 12 COMMENT 'In months' AFTER `min_rental_period`,
ADD COLUMN IF NOT EXISTS `advance_rent_months` INT(11) DEFAULT 1 COMMENT 'Number of months rent to pay in advance' AFTER `max_rental_period`;

-- Update existing houses with default security deposit (2 months rent)
UPDATE `houses` SET `security_deposit` = `price` * 2 WHERE `security_deposit` IS NULL;
