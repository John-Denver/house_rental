-- M-Pesa Payment Requests Table
-- Stores STK Push requests and their responses

CREATE TABLE IF NOT EXISTS `mpesa_payment_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `checkout_request_id` varchar(255) NOT NULL,
  `merchant_request_id` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference` varchar(255) NOT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `result_code` varchar(10) DEFAULT NULL,
  `result_desc` text DEFAULT NULL,
  `mpesa_receipt_number` varchar(50) DEFAULT NULL,
  `transaction_date` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checkout_request_id` (`checkout_request_id`),
  KEY `booking_id` (`booking_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_mpesa_booking` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data for testing (optional)
-- INSERT INTO `mpesa_payment_requests` (`booking_id`, `checkout_request_id`, `phone_number`, `amount`, `reference`, `status`) 
-- VALUES (1, 'ws_CO_123456789', '254700000000', 50000.00, 'RENTAL_1_1234567890', 'pending'); 