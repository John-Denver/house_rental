-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 29, 2025 at 12:20 AM
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
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(6, 3, 39, '2025-07-28 20:24:52'),
(7, 3, 38, '2025-07-28 20:24:54');

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

INSERT INTO `houses` (`id`, `landlord_id`, `house_no`, `category_id`, `description`, `location`, `city`, `state`, `country`, `latitude`, `longitude`, `price`, `security_deposit`, `min_rental_period`, `max_rental_period`, `advance_rent_months`, `bedrooms`, `bathrooms`, `area`, `image`, `main_image`, `featured`, `status`, `created_at`, `updated_at`, `address`, `total_units`, `available_units`) VALUES
(37, 4, 'Majesty', 21, 'vjvjh', 'Kejen and Sons M pesa, Gatundu-Juja Road, Juja, Kenya', NULL, NULL, NULL, -1.11067200, 37.01836600, 500000, 1000000.00, 1, 12, 1, 2, 1, 500.00, NULL, '1753188761_main_Screenshot 5_Aquila Laundry.png', 0, 1, '2025-07-22 12:52:41', '2025-07-25 04:46:35', '', 10, 1),
(38, 7, 'Zetech', 21, 'A new place to live your life', 'RXW7+W5G, Ruiru, Kenya', NULL, NULL, NULL, -1.15324800, 36.96296000, 25000, NULL, 1, 12, 1, 1, 1, 500.00, NULL, '1753309042_main_ChatGPT Image Jul 21, 2025, 12_57_31 PM.png', 0, 1, '2025-07-23 22:17:22', '2025-07-23 22:17:22', '', 1, 1),
(39, 4, 'Luxury Villa', 25, '<p>A lot of amenities are present here</p>', 'New Admin Block, PAUS Science St, Juja, Kenya', NULL, NULL, NULL, -1.09793100, 37.01464400, 150000, NULL, 1, 12, 1, 5, 3, 498.00, NULL, '1753423510_main_phone splash.png', 0, 1, '2025-07-25 06:05:10', '2025-07-25 06:05:10', '', 1, 1),
(41, 7, 'Posta Makongo', 18, '<p>A single room apartment</p>', 'W225+468, Juja, Kenya', NULL, NULL, NULL, -1.09913200, 37.00781300, 4500, NULL, 1, 12, 1, 0, 1, 100.00, NULL, '1753425619_main_EV125_1 (2).png', 0, 1, '2025-07-25 06:40:19', '2025-07-25 06:40:19', 'W225+468, Juja, Kenya', 7, 5);

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
(28, 37, 'image', '', '1753188761_boda-mama mboga.png', '2025-07-22 12:52:41', '2025-07-22 12:52:41'),
(29, 38, 'image', '', '1753309042_Washing Machine with Colourful Laundry.png', '2025-07-23 22:17:22', '2025-07-23 22:17:22'),
(30, 38, 'image', '', '1753309042_ChatGPT Image Jul 21, 2025, 11_14_07 AM.png', '2025-07-23 22:17:22', '2025-07-23 22:17:22'),
(31, 39, 'image', '', '1753423510_Washing Machine with Colourful Laundry.png', '2025-07-25 06:05:10', '2025-07-25 06:05:10'),
(32, 39, 'image', '', '1753423510_ChatGPT Image Jul 21, 2025, 12_57_31 PM.png', '2025-07-25 06:05:10', '2025-07-25 06:05:10'),
(33, 39, 'image', '', '1753423510_ChatGPT Image Jul 20, 2025, 07_19_21 PM.png', '2025-07-25 06:05:10', '2025-07-25 06:05:10'),
(34, 39, 'image', '', '1753423510_Rectangle.png', '2025-07-25 06:05:10', '2025-07-25 06:05:10'),
(38, 41, 'image', '', '1753425619_logo23.bmp', '2025-07-25 06:40:19', '2025-07-25 06:40:19');

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
(1, 39, 3, 'Thiira Elizabeth', '0712512358', '2025-07-29', '15:00:00', 'pending', '', '2025-07-28 21:04:57', '2025-07-28 21:04:57'),
(2, 38, 4, 'Maureen Tallam ', '0712512358', '2025-08-09', '14:00:00', 'pending', '', '2025-07-28 21:24:05', '2025-07-28 21:24:05'),
(3, 37, 7, 'New Landlord', '0712512358', '2025-08-10', '16:00:00', 'pending', '', '2025-07-28 21:58:10', '2025-07-28 21:58:10');

-- --------------------------------------------------------

--
-- Table structure for table `rental_bookings`
--

CREATE TABLE `rental_bookings` (
  `id` int(30) NOT NULL,
  `house_id` int(30) NOT NULL,
  `landlord_id` int(30) NOT NULL,
  `user_id` int(30) NOT NULL,
  `start_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `end_date` date NOT NULL,
  `check_out_time` time DEFAULT NULL,
  `special_requests` text DEFAULT NULL,
  `rental_period` int(11) NOT NULL COMMENT 'Number of months',
  `total_amount` decimal(15,2) NOT NULL,
  `security_deposit` decimal(15,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','expired','completed','rejected') NOT NULL DEFAULT 'pending',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` enum('tenant','landlord','system') DEFAULT NULL,
  `documents` text DEFAULT NULL COMMENT 'JSON array of document paths',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `password` text NOT NULL,
  `type` enum('admin','landlord','caretaker','customer') NOT NULL DEFAULT 'customer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `phone_number`, `password`, `type`) VALUES
(1, 'Administrator', 'denver@gmail.com', NULL, '$2y$10$JB4x5av/DBOFe1iE022UF.OXuV4.Wg.2T1MkR37KVXUwlYv8g0vcm', 'admin'),
(3, 'Thiira Elizabeth', 'thiira@gmail.com', NULL, '$2y$10$BtkTBVY4vkjF1g1U4D.ytOiWdw2.3Eewzogwv3DhCT.rHsgfBgPKm', 'customer'),
(4, 'Maureen Tallam ', 'tallam@gmail.com', '0712512358', '$2y$10$o3s9/cRzHmWMUDa0CkdJvOG07OZkNqTnk8UbBjedr6.bFfU501WI.', 'landlord'),
(7, 'New Landlord', 'new@gmail.com', '0712512358', '$2y$10$KpA95L7aJhqMigyrEBfmFuu9A8TfxnBSd1KaDkRPdwFstiivhmK0.', 'landlord');

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
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `fk_booking_landlord` (`landlord_id`);

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
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `houses`
--
ALTER TABLE `houses`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `house_media`
--
ALTER TABLE `house_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `property_viewings`
--
ALTER TABLE `property_viewings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rental_bookings`
--
ALTER TABLE `rental_bookings`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
