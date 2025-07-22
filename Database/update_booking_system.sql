-- Drop existing foreign key constraints
ALTER TABLE `rental_bookings` DROP FOREIGN KEY `rental_bookings_ibfk_1`;
ALTER TABLE `rental_bookings` DROP FOREIGN KEY `rental_bookings_ibfk_2`;

-- Add new columns to rental_bookings table
ALTER TABLE `rental_bookings`
ADD COLUMN `landlord_id` INT(30) NOT NULL AFTER `house_id`,
ADD COLUMN `total_amount` DECIMAL(15,2) NOT NULL AFTER `rental_period`,
ADD COLUMN `security_deposit` DECIMAL(15,2) DEFAULT 0.00 AFTER `total_amount`,
ADD COLUMN `payment_status` ENUM('pending', 'partial', 'paid', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending' AFTER `security_deposit`,
ADD COLUMN `payment_method` VARCHAR(50) DEFAULT NULL AFTER `payment_status`,
ADD COLUMN `payment_reference` VARCHAR(100) DEFAULT NULL AFTER `payment_method`,
ADD COLUMN `check_in_time` TIME DEFAULT NULL AFTER `start_date`,
ADD COLUMN `check_out_time` TIME DEFAULT NULL AFTER `end_date`,
ADD COLUMN `special_requests` TEXT DEFAULT NULL AFTER `check_out_time`,
ADD COLUMN `cancellation_reason` TEXT DEFAULT NULL AFTER `status`,
ADD COLUMN `cancelled_by` ENUM('tenant', 'landlord', 'system') DEFAULT NULL AFTER `cancellation_reason`,
ADD COLUMN `documents` TEXT DEFAULT NULL COMMENT 'JSON array of document paths' AFTER `cancelled_by`,
MODIFY COLUMN `status` ENUM('pending', 'confirmed', 'cancelled', 'expired', 'completed', 'rejected') NOT NULL DEFAULT 'pending';

-- Recreate foreign key constraints with ON DELETE and ON UPDATE actions
ALTER TABLE `rental_bookings`
ADD CONSTRAINT `fk_booking_house` 
    FOREIGN KEY (`house_id`) 
    REFERENCES `houses` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
ADD CONSTRAINT `fk_booking_tenant` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
ADD CONSTRAINT `fk_booking_landlord` 
    FOREIGN KEY (`landlord_id`) 
    REFERENCES `users` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE;

-- Create booking_payments table for tracking payment installments
CREATE TABLE IF NOT EXISTS `booking_payments` (
  `id` INT(30) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(30) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payment_date` DATETIME NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`booking_id`),
  CONSTRAINT `fk_payment_booking` 
    FOREIGN KEY (`booking_id`) 
    REFERENCES `rental_bookings` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create booking_reviews table for tenant reviews
CREATE TABLE IF NOT EXISTS `booking_reviews` (
  `id` INT(30) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(30) NOT NULL,
  `rating` TINYINT(1) NOT NULL CHECK (rating >= 1 AND rating <= 5),
  `review` TEXT DEFAULT NULL,
  `review_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_booking_review` (`booking_id`),
  CONSTRAINT `fk_review_booking` 
    FOREIGN KEY (`booking_id`) 
    REFERENCES `rental_bookings` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create booking_documents table for storing tenant documents
CREATE TABLE IF NOT EXISTS `booking_documents` (
  `id` INT(30) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(30) NOT NULL,
  `document_type` VARCHAR(100) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_booking_doc` (`booking_id`),
  CONSTRAINT `fk_document_booking` 
    FOREIGN KEY (`booking_id`) 
    REFERENCES `rental_bookings` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
