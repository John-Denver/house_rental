-- Payment Tracking System
-- Tracks different types of payments and their purposes

-- Payment Types Table
CREATE TABLE IF NOT EXISTS `payment_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default payment types
INSERT INTO `payment_types` (`name`, `description`) VALUES
('initial_payment', 'Security deposit + first month rent'),
('monthly_rent', 'Monthly rent payment'),
('security_deposit', 'Security deposit only'),
('additional_fees', 'Additional fees or charges'),
('penalty', 'Late payment penalty'),
('refund', 'Refund of security deposit or overpayment')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Enhanced Monthly Rent Payments Table
-- Add payment_type and is_first_payment columns
ALTER TABLE `monthly_rent_payments` 
ADD COLUMN `payment_type` varchar(50) DEFAULT 'monthly_rent' AFTER `status`,
ADD COLUMN `is_first_payment` tinyint(1) DEFAULT 0 AFTER `payment_type`,
ADD COLUMN `security_deposit_amount` decimal(15,2) DEFAULT 0.00 AFTER `is_first_payment`,
ADD COLUMN `monthly_rent_amount` decimal(15,2) DEFAULT 0.00 AFTER `security_deposit_amount`;

-- Add index for payment type
ALTER TABLE `monthly_rent_payments` 
ADD INDEX `idx_payment_type` (`payment_type`),
ADD INDEX `idx_is_first_payment` (`is_first_payment`);

-- Payment Tracking Table
-- Tracks all payments with their types and purposes
CREATE TABLE IF NOT EXISTS `payment_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `security_deposit_amount` decimal(15,2) DEFAULT 0.00,
  `monthly_rent_amount` decimal(15,2) DEFAULT 0.00,
  `month` date DEFAULT NULL COMMENT 'For monthly payments, the month this payment covers',
  `is_first_payment` tinyint(1) DEFAULT 0,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `mpesa_receipt_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_month` (`month`),
  KEY `idx_status` (`status`),
  KEY `idx_is_first_payment` (`is_first_payment`),
  CONSTRAINT `fk_payment_tracking_booking` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Function to get next unpaid month for a booking
DELIMITER //
CREATE FUNCTION IF NOT EXISTS `get_next_unpaid_month`(booking_id_param INT) 
RETURNS DATE
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE next_month DATE;
    
    -- Get the next unpaid month after the last paid month
    SELECT DATE_ADD(month, INTERVAL 1 MONTH)
    INTO next_month
    FROM monthly_rent_payments 
    WHERE booking_id = booking_id_param 
    AND status = 'paid'
    ORDER BY month DESC 
    LIMIT 1;
    
    -- If no paid months found, get the first month of the booking
    IF next_month IS NULL THEN
        SELECT start_date
        INTO next_month
        FROM rental_bookings 
        WHERE id = booking_id_param;
        
        -- Set to first day of the month
        SET next_month = DATE_FORMAT(next_month, '%Y-%m-01');
    END IF;
    
    RETURN next_month;
END //
DELIMITER ;

-- Function to check if first payment has been made
DELIMITER //
CREATE FUNCTION IF NOT EXISTS `has_first_payment_been_made`(booking_id_param INT) 
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE first_payment_exists INT DEFAULT 0;
    
    SELECT COUNT(*)
    INTO first_payment_exists
    FROM monthly_rent_payments 
    WHERE booking_id = booking_id_param 
    AND is_first_payment = 1 
    AND status = 'paid';
    
    RETURN first_payment_exists > 0;
END //
DELIMITER ;

-- Function to get the first unpaid month
DELIMITER //
CREATE FUNCTION IF NOT EXISTS `get_first_unpaid_month`(booking_id_param INT) 
RETURNS DATE
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE first_unpaid_month DATE;
    
    -- Get the first unpaid month
    SELECT month
    INTO first_unpaid_month
    FROM monthly_rent_payments 
    WHERE booking_id = booking_id_param 
    AND status = 'unpaid'
    ORDER BY month ASC 
    LIMIT 1;
    
    RETURN first_unpaid_month;
END //
DELIMITER ; 