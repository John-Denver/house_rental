-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 06, 2025 at 02:24 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `house_rental`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `get_first_unpaid_month` (`booking_id_param` INT) RETURNS DATE DETERMINISTIC READS SQL DATA BEGIN
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
    END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `get_next_unpaid_month` (`booking_id_param` INT) RETURNS DATE DETERMINISTIC READS SQL DATA BEGIN
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
    END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `has_first_payment_been_made` (`booking_id_param` INT) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
        DECLARE first_payment_exists INT DEFAULT 0;
        
        SELECT COUNT(*)
        INTO first_payment_exists
        FROM monthly_rent_payments 
        WHERE booking_id = booking_id_param 
        AND is_first_payment = 1 
        AND status = 'paid';
        
        RETURN first_payment_exists > 0;
    END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `booking_documents`
--

CREATE TABLE `booking_documents` (
  `id` int(30) NOT NULL,
  `booking_id` int(30) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_payments`
--

CREATE TABLE `booking_payments` (
  `id` int(30) NOT NULL,
  `booking_id` int(30) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_gateway` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `receipt_url` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_payments`
--

INSERT INTO `booking_payments` (`id`, `booking_id`, `amount`, `payment_date`, `payment_method`, `payment_gateway`, `transaction_id`, `receipt_url`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(83, 45, 1.00, '2025-08-06 15:00:14', 'deposit', NULL, NULL, NULL, 'pending', NULL, '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(84, 45, 2.00, '2025-08-06 15:01:39', 'M-Pesa', NULL, 'MPESA_1754481699', NULL, 'completed', 'M-Pesa Payment - Checkout Request: ws_CO_060820251501288712512358', '2025-08-06 12:01:39', '2025-08-06 12:01:39');

-- --------------------------------------------------------

--
-- Table structure for table `booking_reviews`
--

CREATE TABLE `booking_reviews` (
  `id` int(30) NOT NULL,
  `booking_id` int(30) NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review` text DEFAULT NULL,
  `review_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(30) NOT NULL,
  `name` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(18, 'single room'),
(19, 'double room'),
(20, 'bedsitter'),
(21, 'one bedroom'),
(22, '2 bedroom'),
(23, '3 bedroom'),
(24, '4 bedroom, mansion'),
(25, '5 bedroom, mansion'),
(26, '6 bedroom, mansion');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `house_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `house_id`, `created_at`) VALUES
(11, 3, 41, '2025-08-04 08:46:35');

-- --------------------------------------------------------

--
-- Table structure for table `houses`
--

CREATE TABLE `houses` (
  `id` int(30) NOT NULL,
  `landlord_id` int(30) NOT NULL,
  `house_no` varchar(50) NOT NULL,
  `category_id` int(30) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `price` double NOT NULL,
  `security_deposit` decimal(10,2) DEFAULT NULL,
  `min_rental_period` int(11) DEFAULT 1 COMMENT 'In months',
  `max_rental_period` int(11) DEFAULT 12 COMMENT 'In months',
  `advance_rent_months` int(11) DEFAULT 1 COMMENT 'Number of months rent to pay in advance',
  `payment_cycle` enum('monthly','quarterly','annually') NOT NULL DEFAULT 'monthly',
  `late_fee_percentage` decimal(5,2) DEFAULT 5.00,
  `bedrooms` int(11) DEFAULT 0,
  `bathrooms` int(11) DEFAULT 0,
  `area` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `main_image` varchar(255) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `address` text DEFAULT NULL,
  `total_units` int(11) NOT NULL DEFAULT 1,
  `available_units` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `houses`
--

INSERT INTO `houses` (`id`, `landlord_id`, `house_no`, `category_id`, `description`, `location`, `city`, `state`, `country`, `latitude`, `longitude`, `price`, `security_deposit`, `min_rental_period`, `max_rental_period`, `advance_rent_months`, `payment_cycle`, `late_fee_percentage`, `bedrooms`, `bathrooms`, `area`, `image`, `main_image`, `featured`, `status`, `created_at`, `updated_at`, `address`, `total_units`, `available_units`) VALUES
(38, 7, 'Zetech', 21, 'A new place to live your life', 'RXW7+W5G, Ruiru, Kenya', NULL, NULL, NULL, -1.15324800, 36.96296000, 25000, 25000.00, 1, 12, 1, 'monthly', 5.00, 1, 1, 500.00, NULL, '1753309042_main_ChatGPT Image Jul 21, 2025, 12_57_31 PM.png', 0, 1, '2025-07-23 22:17:22', '2025-07-31 21:02:27', '', 1, 1),
(41, 7, 'Posta Makongo', 18, '<p>A single room apartment</p>', 'W225+468, Juja, Kenya', NULL, NULL, NULL, -1.09913200, 37.00781300, 4500, 4500.00, 1, 12, 1, 'monthly', 5.00, 0, 1, 100.00, NULL, '1753425619_main_EV125_1 (2).png', 0, 1, '2025-07-25 06:40:19', '2025-07-31 21:02:50', 'W225+468, Juja, Kenya', 7, 5),
(42, 4, 'Zetech Building', 22, '<p>Property in detail</p>', 'V2X5+VC6, Juja, Kenya', NULL, NULL, NULL, -1.10011600, 37.00848400, 1, 1.00, 1, 12, 1, 'monthly', 5.00, 1, 1, 500.00, NULL, '1753997591_main_Washing Machine with Colourful Laundry.png', 0, 1, '2025-07-31 21:33:11', '2025-08-01 10:27:14', 'V2X5+VC6, Juja, Kenya', 17, 7);

-- --------------------------------------------------------

--
-- Table structure for table `house_media`
--

CREATE TABLE `house_media` (
  `id` int(11) NOT NULL,
  `house_id` int(11) NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `media_path` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `house_media`
--

INSERT INTO `house_media` (`id`, `house_id`, `media_type`, `media_path`, `file_path`, `created_at`, `updated_at`) VALUES
(29, 38, 'image', '', '1753309042_Washing Machine with Colourful Laundry.png', '2025-07-23 22:17:22', '2025-07-23 22:17:22'),
(30, 38, 'image', '', '1753309042_ChatGPT Image Jul 21, 2025, 11_14_07 AM.png', '2025-07-23 22:17:22', '2025-07-23 22:17:22'),
(38, 41, 'image', '', '1753425619_logo23.bmp', '2025-07-25 06:40:19', '2025-07-25 06:40:19'),
(39, 42, 'image', '', '1753997591_tenant_schedule.png', '2025-07-31 21:33:11', '2025-07-31 21:33:11'),
(40, 42, 'image', '', '1753997591_shceduled.png', '2025-07-31 21:33:11', '2025-07-31 21:33:11');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_rent_payments`
--

CREATE TABLE `monthly_rent_payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `month` date NOT NULL COMMENT 'First day of the month (YYYY-MM-01)',
  `amount` decimal(15,2) NOT NULL,
  `status` enum('paid','unpaid','overdue') NOT NULL DEFAULT 'unpaid',
  `payment_type` varchar(50) DEFAULT 'monthly_rent',
  `is_first_payment` tinyint(1) DEFAULT 0,
  `security_deposit_amount` decimal(15,2) DEFAULT 0.00,
  `monthly_rent_amount` decimal(15,2) DEFAULT 0.00,
  `payment_date` datetime DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `mpesa_receipt_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `monthly_rent_payments`
--

INSERT INTO `monthly_rent_payments` (`id`, `booking_id`, `month`, `amount`, `status`, `payment_type`, `is_first_payment`, `security_deposit_amount`, `monthly_rent_amount`, `payment_date`, `payment_method`, `transaction_id`, `mpesa_receipt_number`, `notes`, `created_at`, `updated_at`) VALUES
(265, 45, '2025-08-01', 2.00, 'paid', 'initial_payment', 1, 0.00, 0.00, '2025-08-06 14:01:39', 'M-Pesa', 'MPESA_1754481699', 'TH676C0E55', 'First month rent + security deposit - Paid on 2025-08-06 14:01:39', '2025-08-06 12:00:14', '2025-08-06 12:01:39'),
(266, 45, '2025-09-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(267, 45, '2025-10-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(268, 45, '2025-11-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(269, 45, '2025-12-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(270, 45, '2026-01-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(271, 45, '2026-02-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(272, 45, '2026-03-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(273, 45, '2026-04-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(274, 45, '2026-05-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(275, 45, '2026-06-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(276, 45, '2026-07-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(277, 45, '2026-08-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-06 12:00:14', '2025-08-06 12:00:14');

-- --------------------------------------------------------

--
-- Table structure for table `mpesa_payment_requests`
--

CREATE TABLE `mpesa_payment_requests` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `checkout_request_id` varchar(255) NOT NULL,
  `merchant_request_id` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_type` varchar(50) DEFAULT 'initial',
  `reference` varchar(255) NOT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `result_code` varchar(10) DEFAULT NULL,
  `result_desc` text DEFAULT NULL,
  `mpesa_receipt_number` varchar(50) DEFAULT NULL,
  `transaction_date` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mpesa_payment_requests`
--

INSERT INTO `mpesa_payment_requests` (`id`, `booking_id`, `checkout_request_id`, `merchant_request_id`, `phone_number`, `amount`, `payment_type`, `reference`, `status`, `result_code`, `result_desc`, `mpesa_receipt_number`, `transaction_date`, `created_at`, `updated_at`) VALUES
(94, 45, 'ws_CO_060820251501288712512358', NULL, '254712512358', 2.00, 'initial', 'RENTAL_45_1754481688', 'completed', '0', 'The service request is processed successfully', 'TH676C0E55', NULL, '2025-08-06 12:01:28', '2025-08-06 12:01:39');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(30) NOT NULL,
  `tenant_id` int(30) NOT NULL,
  `amount` float NOT NULL,
  `invoice` varchar(50) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `tenant_id`, `amount`, `invoice`, `date_created`) VALUES
(15, 18, 7500, '001', '2025-06-23 00:00:00'),
(17, 19, 3500, '002', '2025-06-23 00:00:00'),
(18, 20, 5000, '003', '2025-06-23 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `payment_tracking`
--

CREATE TABLE `payment_tracking` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_tracking`
--

INSERT INTO `payment_tracking` (`id`, `booking_id`, `payment_type`, `amount`, `security_deposit_amount`, `monthly_rent_amount`, `month`, `is_first_payment`, `status`, `payment_date`, `payment_method`, `transaction_id`, `mpesa_receipt_number`, `notes`, `created_at`, `updated_at`) VALUES
(1, 29, 'monthly_rent', 0.00, 0.00, 0.00, '2025-09-01', 0, 'completed', '2025-08-05 22:20:32', 'manual', 'TEST-1754425232', NULL, 'Test pre-payment from debug script', '2025-08-05 20:20:32', '2025-08-05 20:20:32'),
(2, 29, 'monthly_rent', 0.00, 0.00, 0.00, '2025-09-01', 0, 'completed', '2025-08-05 22:20:36', 'manual', 'TEST-1754425236', NULL, 'Test pre-payment from debug script', '2025-08-05 20:20:36', '2025-08-05 20:20:36'),
(3, 29, 'monthly_rent', 0.00, 0.00, 0.00, '2025-09-01', 0, 'completed', '2025-08-05 22:22:51', 'manual', 'TEST-1754425371', NULL, 'Test pre-payment from debug script', '2025-08-05 20:22:51', '2025-08-05 20:22:51'),
(4, 29, 'monthly_rent', 0.00, 0.00, 0.00, '2025-10-01', 0, 'completed', '2025-08-05 22:27:13', 'manual', 'TEST-1754425633', NULL, 'Test pre-payment from debug script', '2025-08-05 20:27:13', '2025-08-05 20:27:13'),
(5, 29, 'monthly_rent', 0.00, 0.00, 0.00, '2025-11-01', 0, 'completed', '2025-08-05 22:27:19', 'manual', 'TEST-1754425639', NULL, 'Test pre-payment from debug script', '2025-08-05 20:27:19', '2025-08-05 20:27:19'),
(6, 29, 'monthly_rent', 0.00, 0.00, 0.00, '2025-12-01', 0, 'completed', '2025-08-05 22:27:19', 'manual', 'TEST-1754425639', NULL, 'Test pre-payment from debug script', '2025-08-05 20:27:19', '2025-08-05 20:27:19');

-- --------------------------------------------------------

--
-- Table structure for table `payment_types`
--

CREATE TABLE `payment_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_types`
--

INSERT INTO `payment_types` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'initial_payment', 'Security deposit + first month rent', 1, '2025-08-02 19:25:25'),
(2, 'monthly_rent', 'Monthly rent payment', 1, '2025-08-02 19:25:25'),
(3, 'security_deposit', 'Security deposit only', 1, '2025-08-02 19:25:25'),
(4, 'additional_fees', 'Additional fees or charges', 1, '2025-08-02 19:25:25'),
(5, 'penalty', 'Late payment penalty', 1, '2025-08-02 19:25:25'),
(6, 'refund', 'Refund of security deposit or overpayment', 1, '2025-08-02 19:25:25');

-- --------------------------------------------------------

--
-- Table structure for table `property_viewings`
--

CREATE TABLE `property_viewings` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `viewer_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `viewing_date` date NOT NULL,
  `viewing_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_viewings`
--

INSERT INTO `property_viewings` (`id`, `property_id`, `user_id`, `viewer_name`, `contact_number`, `viewing_date`, `viewing_time`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(2, 38, 4, 'Maureen Tallam ', '0712512358', '2025-08-09', '14:00:00', 'pending', '', '2025-07-28 21:24:05', '2025-07-28 21:24:05'),
(4, 38, NULL, 'Guest', '0712512358', '2025-08-08', '12:00:00', 'pending', '', '2025-07-30 07:40:51', '2025-07-30 07:40:51'),
(5, 42, 3, 'Thiira Elizabeth', '0712512358', '2025-08-15', '15:00:00', 'completed', '', '2025-08-04 08:47:49', '2025-08-04 20:11:12'),
(6, 42, 3, 'Thiira Elizabeth', '0712512358', '2025-08-06', '16:00:00', 'cancelled', '\n[CANCELLED: I have found a better house]', '2025-08-06 12:00:01', '2025-08-06 12:05:07');

-- --------------------------------------------------------

--
-- Table structure for table `rental_bookings`
--

CREATE TABLE `rental_bookings` (
  `id` int(30) NOT NULL,
  `house_id` int(30) NOT NULL,
  `monthly_rent` decimal(15,2) NOT NULL,
  `landlord_id` int(30) NOT NULL,
  `user_id` int(30) NOT NULL,
  `start_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `end_date` date NOT NULL,
  `check_out_time` time DEFAULT NULL,
  `special_requests` text DEFAULT NULL,
  `last_payment_date` date DEFAULT NULL,
  `next_payment_due` date DEFAULT NULL,
  `security_deposit` decimal(15,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','completed','active') NOT NULL DEFAULT 'pending',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` enum('tenant','landlord','system') DEFAULT NULL,
  `documents` text DEFAULT NULL COMMENT 'JSON array of document paths',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_bookings`
--

INSERT INTO `rental_bookings` (`id`, `house_id`, `monthly_rent`, `landlord_id`, `user_id`, `start_date`, `check_in_time`, `end_date`, `check_out_time`, `special_requests`, `last_payment_date`, `next_payment_due`, `security_deposit`, `payment_status`, `payment_method`, `payment_reference`, `status`, `cancellation_reason`, `cancelled_by`, `documents`, `created_at`, `updated_at`) VALUES
(45, 42, 1.00, 4, 3, '2025-08-12', NULL, '2026-08-12', NULL, NULL, NULL, NULL, 1.00, 'paid', NULL, NULL, 'confirmed', NULL, NULL, NULL, '2025-08-06 12:00:14', '2025-08-06 12:01:39');

-- --------------------------------------------------------

--
-- Table structure for table `rent_payments`
--

CREATE TABLE `rent_payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `month` date NOT NULL,
  `amount_due` decimal(15,2) NOT NULL,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `late_fee` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','paid','overdue','partial') NOT NULL DEFAULT 'pending',
  `due_date` date NOT NULL,
  `paid_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rent_payments`
--

INSERT INTO `rent_payments` (`id`, `booking_id`, `month`, `amount_due`, `amount_paid`, `previous_balance`, `late_fee`, `status`, `due_date`, `paid_date`, `created_at`, `updated_at`) VALUES
(1, 3, '2025-07-01', 25000.00, 0.00, 0.00, 0.00, 'pending', '2025-07-05', NULL, '2025-07-31 19:02:06', '2025-07-31 19:02:06'),
(2, 4, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-07-31 22:33:10', '2025-07-31 22:33:10'),
(3, 5, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-01 09:26:14', '2025-08-01 09:26:14'),
(4, 6, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-01 11:42:14', '2025-08-01 11:42:14'),
(5, 7, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-04 08:50:45', '2025-08-04 08:50:45'),
(6, 8, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-04 09:25:00', '2025-08-04 09:25:00'),
(7, 9, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-04 20:05:00', '2025-08-04 20:05:00'),
(8, 10, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-04 20:06:38', '2025-08-04 20:06:38'),
(9, 11, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-04 20:14:35', '2025-08-04 20:14:35'),
(10, 12, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-04 20:15:28', '2025-08-04 20:15:28'),
(11, 13, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 09:23:18', '2025-08-05 09:23:18'),
(12, 14, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 10:00:42', '2025-08-05 10:00:42'),
(13, 15, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 10:14:09', '2025-08-05 10:14:09'),
(14, 16, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 10:16:50', '2025-08-05 10:16:50'),
(15, 17, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 10:36:31', '2025-08-05 10:36:31'),
(16, 18, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 10:36:42', '2025-08-05 10:36:42'),
(17, 19, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 10:37:36', '2025-08-05 10:37:36'),
(18, 20, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 11:22:07', '2025-08-05 11:22:07'),
(19, 21, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 11:32:13', '2025-08-05 11:32:13'),
(20, 22, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 15:50:37', '2025-08-05 15:50:37'),
(21, 23, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 16:06:10', '2025-08-05 16:06:10'),
(22, 24, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 16:53:43', '2025-08-05 16:53:43'),
(23, 25, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 17:02:13', '2025-08-05 17:02:13'),
(24, 26, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 17:12:46', '2025-08-05 17:12:46'),
(25, 27, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 17:22:58', '2025-08-05 17:22:58'),
(26, 28, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 17:42:39', '2025-08-05 17:42:39'),
(27, 29, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 18:28:55', '2025-08-05 18:28:55'),
(28, 30, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-05 18:38:31', '2025-08-05 18:38:31'),
(29, 31, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 07:46:25', '2025-08-06 07:46:25'),
(30, 32, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 08:57:42', '2025-08-06 08:57:42'),
(31, 33, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 09:44:04', '2025-08-06 09:44:04'),
(32, 34, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 09:44:32', '2025-08-06 09:44:32'),
(33, 35, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 09:51:08', '2025-08-06 09:51:08'),
(34, 36, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 10:08:43', '2025-08-06 10:08:43'),
(35, 37, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 10:35:32', '2025-08-06 10:35:32'),
(36, 38, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 10:40:11', '2025-08-06 10:40:11'),
(37, 39, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 10:46:59', '2025-08-06 10:46:59'),
(38, 40, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 11:01:34', '2025-08-06 11:01:34'),
(39, 41, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 11:08:27', '2025-08-06 11:08:27'),
(40, 42, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 11:12:50', '2025-08-06 11:12:50'),
(41, 43, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 11:18:54', '2025-08-06 11:18:54'),
(42, 44, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 11:45:43', '2025-08-06 11:45:43'),
(43, 45, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 12:00:14', '2025-08-06 12:00:14');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(30) NOT NULL,
  `name` text NOT NULL,
  `email` varchar(200) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `cover_img` text NOT NULL,
  `about_content` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `name`, `email`, `contact`, `cover_img`, `about_content`) VALUES
(1, 'House Rental Management System', 'info@sample.comm', '+6948 8542 623', '1603344720_1602738120_pngtree-purple-hd-business-banner-image_5493.jpg', '&lt;p style=&quot;text-align: center; background: transparent; position: relative;&quot;&gt;&lt;span style=&quot;color: rgb(0, 0, 0); font-family: &amp;quot;Open Sans&amp;quot;, Arial, sans-serif; font-weight: 400; text-align: justify;&quot;&gt;&amp;nbsp;is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry&rsquo;s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.&lt;/span&gt;&lt;br&gt;&lt;/p&gt;&lt;p style=&quot;text-align: center; background: transparent; position: relative;&quot;&gt;&lt;br&gt;&lt;/p&gt;&lt;p style=&quot;text-align: center; background: transparent; position: relative;&quot;&gt;&lt;br&gt;&lt;/p&gt;&lt;p&gt;&lt;/p&gt;');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(30) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(50) NOT NULL,
  `house_id` int(30) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0= inactive',
  `date_in` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `firstname`, `middlename`, `lastname`, `email`, `contact`, `house_id`, `status`, `date_in`) VALUES
(18, 'Denver', 'John', 'Ogamba', 'denverogamba@gmail.com', '0712512358', 22, 1, '2025-06-05'),
(19, 'Chebet ', 'Tellam', 'MAureen ', 'moh@gmail.com', '0712512358', 22, 1, '2025-06-11'),
(20, 'Thiira', 'Elizabeth', 'Liz', 'liz@gmail.com', '0712512358', 21, 1, '2025-06-03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(30) NOT NULL,
  `name` text NOT NULL,
  `username` varchar(200) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL COMMENT 'User''s contact phone number',
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `default_payment_method` varchar(50) DEFAULT NULL,
  `password` text NOT NULL,
  `type` enum('admin','landlord','caretaker','customer') NOT NULL DEFAULT 'customer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `phone_number`, `current_balance`, `default_payment_method`, `password`, `type`) VALUES
(1, 'Administrator', 'denver@gmail.com', NULL, 0.00, NULL, '$2y$10$JB4x5av/DBOFe1iE022UF.OXuV4.Wg.2T1MkR37KVXUwlYv8g0vcm', 'admin'),
(3, 'Thiira Elizabeth', 'thiira@gmail.com', NULL, 0.00, NULL, '$2y$10$BtkTBVY4vkjF1g1U4D.ytOiWdw2.3Eewzogwv3DhCT.rHsgfBgPKm', 'customer'),
(4, 'Maureen Tallam ', 'tallam@gmail.com', '0712512358', 0.00, NULL, '$2y$10$o3s9/cRzHmWMUDa0CkdJvOG07OZkNqTnk8UbBjedr6.bFfU501WI.', 'landlord'),
(7, 'New Landlord', 'new@gmail.com', '0712512358', 0.00, NULL, '$2y$10$KpA95L7aJhqMigyrEBfmFuu9A8TfxnBSd1KaDkRPdwFstiivhmK0.', 'landlord'),
(8, 'pittah Class Rep', 'pittah@gmail.com', '0712512358', 0.00, NULL, '$2y$10$w3kDHFPTQ4dSo23xDMqdoeVKbAtNCJ8ddgxts5N208lwephKbfCvW', 'customer');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `booking_documents`
--
ALTER TABLE `booking_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_doc` (`booking_id`);

--
-- Indexes for table `booking_payments`
--
ALTER TABLE `booking_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking` (`booking_id`);

--
-- Indexes for table `booking_reviews`
--
ALTER TABLE `booking_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_review` (`booking_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_house_unique` (`user_id`,`house_id`),
  ADD KEY `house_id` (`house_id`);

--
-- Indexes for table `houses`
--
ALTER TABLE `houses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_featured` (`featured`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_bedrooms` (`bedrooms`),
  ADD KEY `idx_bathrooms` (`bathrooms`),
  ADD KEY `idx_location` (`location`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_state` (`state`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_houses_location` (`latitude`,`longitude`),
  ADD KEY `idx_houses_units` (`available_units`);

--
-- Indexes for table `house_media`
--
ALTER TABLE `house_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_house_media` (`house_id`,`media_type`),
  ADD KEY `idx_house_media_updated` (`updated_at`);

--
-- Indexes for table `monthly_rent_payments`
--
ALTER TABLE `monthly_rent_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_booking_month` (`booking_id`,`month`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_month` (`month`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_payment_type` (`payment_type`),
  ADD KEY `idx_is_first_payment` (`is_first_payment`);

--
-- Indexes for table `mpesa_payment_requests`
--
ALTER TABLE `mpesa_payment_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `checkout_request_id` (`checkout_request_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_tracking`
--
ALTER TABLE `payment_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_payment_type` (`payment_type`),
  ADD KEY `idx_month` (`month`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_first_payment` (`is_first_payment`);

--
-- Indexes for table `payment_types`
--
ALTER TABLE `payment_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_name` (`name`);

--
-- Indexes for table `property_viewings`
--
ALTER TABLE `property_viewings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `rental_bookings`
--
ALTER TABLE `rental_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booking_house` (`house_id`),
  ADD KEY `fk_booking_tenant` (`user_id`),
  ADD KEY `fk_booking_landlord` (`landlord_id`),
  ADD KEY `idx_rental_status` (`status`),
  ADD KEY `idx_rental_dates` (`start_date`,`end_date`);

--
-- Indexes for table `rent_payments`
--
ALTER TABLE `rent_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_booking_month` (`booking_id`,`month`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `booking_documents`
--
ALTER TABLE `booking_documents`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_payments`
--
ALTER TABLE `booking_payments`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `booking_reviews`
--
ALTER TABLE `booking_reviews`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `houses`
--
ALTER TABLE `houses`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `house_media`
--
ALTER TABLE `house_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `monthly_rent_payments`
--
ALTER TABLE `monthly_rent_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=278;

--
-- AUTO_INCREMENT for table `mpesa_payment_requests`
--
ALTER TABLE `mpesa_payment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `payment_tracking`
--
ALTER TABLE `payment_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payment_types`
--
ALTER TABLE `payment_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `property_viewings`
--
ALTER TABLE `property_viewings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rental_bookings`
--
ALTER TABLE `rental_bookings`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `rent_payments`
--
ALTER TABLE `rent_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_documents`
--
ALTER TABLE `booking_documents`
  ADD CONSTRAINT `fk_document_booking` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `booking_payments`
--
ALTER TABLE `booking_payments`
  ADD CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `booking_reviews`
--
ALTER TABLE `booking_reviews`
  ADD CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `house_media`
--
ALTER TABLE `house_media`
  ADD CONSTRAINT `house_media_ibfk_1` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_rent_payments`
--
ALTER TABLE `monthly_rent_payments`
  ADD CONSTRAINT `fk_monthly_rent_booking` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mpesa_payment_requests`
--
ALTER TABLE `mpesa_payment_requests`
  ADD CONSTRAINT `fk_mpesa_booking` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_viewings`
--
ALTER TABLE `property_viewings`
  ADD CONSTRAINT `property_viewings_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_viewings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `rental_bookings`
--
ALTER TABLE `rental_bookings`
  ADD CONSTRAINT `fk_booking_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_booking_landlord` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_booking_tenant` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
