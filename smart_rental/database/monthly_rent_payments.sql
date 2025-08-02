-- Monthly Rent Payments Table
-- Tracks monthly rent payments for each booking

CREATE TABLE IF NOT EXISTS `monthly_rent_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `month` date NOT NULL COMMENT 'First day of the month (YYYY-MM-01)',
  `amount` decimal(15,2) NOT NULL,
  `status` enum('paid','unpaid','overdue') NOT NULL DEFAULT 'unpaid',
  `payment_date` datetime DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `mpesa_receipt_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_booking_month` (`booking_id`, `month`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_month` (`month`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `fk_monthly_rent_booking` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data for testing (optional)
-- INSERT INTO `monthly_rent_payments` (`booking_id`, `month`, `amount`, `status`, `payment_date`, `payment_method`) 
-- VALUES 
-- (1, '2025-01-01', 50000.00, 'paid', '2025-01-05 10:30:00', 'M-Pesa'),
-- (1, '2025-02-01', 50000.00, 'paid', '2025-02-03 14:20:00', 'M-Pesa'),
-- (1, '2025-03-01', 50000.00, 'unpaid', NULL, NULL),
-- (1, '2025-04-01', 50000.00, 'unpaid', NULL, NULL); 