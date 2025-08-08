-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 08, 2025 at 08:11 PM
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
(118, 75, 1.00, '2025-08-07 14:58:04', 'deposit', NULL, NULL, NULL, 'pending', NULL, '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(119, 76, 300000.00, '2025-08-07 15:29:17', 'deposit', NULL, NULL, NULL, 'pending', NULL, '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(120, 77, 1.00, '2025-08-08 09:15:17', 'deposit', NULL, NULL, NULL, 'pending', NULL, '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(121, 78, 1.00, '2025-08-08 09:42:44', 'deposit', NULL, NULL, NULL, 'pending', NULL, '2025-08-08 06:42:44', '2025-08-08 06:42:44');

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
  `available_units` int(11) NOT NULL DEFAULT 1,
  `utilities` text DEFAULT NULL COMMENT 'JSON array of included utilities',
  `utilities_notes` text DEFAULT NULL COMMENT 'Additional notes about utilities'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `houses`
--

INSERT INTO `houses` (`id`, `landlord_id`, `house_no`, `category_id`, `description`, `location`, `city`, `state`, `country`, `latitude`, `longitude`, `price`, `security_deposit`, `min_rental_period`, `max_rental_period`, `advance_rent_months`, `payment_cycle`, `late_fee_percentage`, `bedrooms`, `bathrooms`, `area`, `image`, `main_image`, `featured`, `status`, `created_at`, `updated_at`, `address`, `total_units`, `available_units`, `utilities`, `utilities_notes`) VALUES
(42, 4, 'Zion Properties', 22, '✔ Bright living area\r\n✔ Modern kitchen with [appliances/countertops]\r\n✔ Spacious bedrooms\r\n✔ Updated bathroom(s)\r\n✔ Private outdoor space ([yard/patio/balcony])\r\n✔ Convenient location near [amenities]', 'V2X5+VC6, Juja, Kenya', NULL, NULL, NULL, -1.10011600, 37.00848400, 1, 1.00, 1, 12, 1, 'monthly', 5.00, 1, 1, 500.00, NULL, 'property_68939e85e15e7.jpeg', 0, 1, '2025-07-31 21:33:11', '2025-08-07 12:03:00', 'V2X5+VC6, Juja, Kenya', 17, 5, '[]', NULL),
(43, 4, 'Green Ville ', 26, '<p>✔ <strong>Expansive Layout</strong> – Generous living and dining areas, perfect for family gatherings and entertaining guests</p><p> ✔ <strong>Modern Kitchen</strong> – Equipped with stainless steel appliances, ample cabinet space, and a granite countertop island</p><p> ✔ <strong>Large Bedrooms</strong> – Six spacious bedrooms, including a master suite with a walk-in closet and en-suite bathroom</p><p> ✔ <strong>Multiple Bathrooms</strong> – Features updated fixtures, with a mix of bathtub/shower combos for convenience</p><p> ✔ <strong>Outdoor Space</strong> – Private backyard with a patio and garden, perfect for relaxing or hosting events</p><p> ✔ <strong>Bonus Features</strong> – Includes a laundry room, a two-car garage, and extra storage space</p><p> ✔ <strong>Prime Location</strong> – Conveniently located near top-rated schools, public parks, shopping centers, and transit routes</p>', 'RXW7+W5G, Ruiru, Kenya', NULL, NULL, NULL, -1.15325800, 36.96333800, 120000, 1.00, 1, 12, 1, 'monthly', 5.00, 6, 6, 300.00, NULL, '1754567463_main_6 Bed New America House Plan with 3-Car Garage - 4,464 Sq Ft.jpeg', 0, 1, '2025-08-07 11:51:03', '2025-08-07 12:03:00', 'RXW7+W5G, Ruiru, Kenya', 3, 1, '[]', NULL),
(44, 4, 'Green Ville ', 26, '<p>✔ <strong>Expansive Layout</strong> – Generous living and dining areas, perfect for family gatherings and entertaining guests</p><p> ✔ <strong>Modern Kitchen</strong> – Equipped with stainless steel appliances, ample cabinet space, and a granite countertop island</p><p> ✔ <strong>Large Bedrooms</strong> – Six spacious bedrooms, including a master suite with a walk-in closet and en-suite bathroom</p><p> ✔ <strong>Multiple Bathrooms</strong> – Features updated fixtures, with a mix of bathtub/shower combos for convenience</p><p> ✔ <strong>Outdoor Space</strong> – Private backyard with a patio and garden, perfect for relaxing or hosting events</p><p> ✔ <strong>Bonus Features</strong> – Includes a laundry room, a two-car garage, and extra storage space</p><p> ✔ <strong>Prime Location</strong> – Conveniently located near top-rated schools, public parks, shopping centers, and transit routes</p>', 'RXW7+W5G, Ruiru, Kenya', NULL, NULL, NULL, -1.15325800, 36.96333800, 120000, 1.00, 1, 12, 1, 'monthly', 5.00, 6, 6, 300.00, NULL, '1754567466_main_6 Bed New America House Plan with 3-Car Garage - 4,464 Sq Ft.jpeg', 0, 1, '2025-08-07 11:51:06', '2025-08-07 12:03:00', 'RXW7+W5G, Ruiru, Kenya', 3, 1, '[]', NULL),
(45, 4, 'Tallam Building', 21, '<h3>🏡 <strong>1-Bedroom Apartment Flat – Unfurnished</strong></h3><p><strong>Open-plan living &amp; kitchen area</strong></p><ul><li> Spacious layout ideal for both relaxing and dining, with room for a full sofa, coffee table, and dining set. Neutral finishes suit any interior style.</li></ul><p><strong>Large windows</strong></p><ul><li> Provide plenty of natural light throughout the day, creating a bright and airy feel.</li></ul><p><strong>Modern kitchen</strong></p><ul><li> Fitted with essential appliances (oven, cooktop, rangehood), sleek cabinetry, and ample countertop space for cooking and storage.</li></ul><p><strong>Comfortable bedroom</strong></p><ul><li> Generously sized to fit a double or queen bed, with a built-in wardrobe offering practical storage space for clothing and personal items.</li></ul><p><strong>Contemporary bathroom</strong></p><ul><li> Features a glass-enclosed shower (or shower-over-bath, depending on unit), modern vanity with under-sink storage, large mirror, and quality fittings.</li></ul><p><strong>Unfurnished</strong></p><ul><li> Offers a blank canvas to style the space to your taste. No furniture included.</li></ul><p><strong>Climate control</strong></p><ul><li> Equipped with heating and/or air conditioning (split system or central, depending on unit).</li></ul><p><strong>Secure building access</strong></p><ul><li> Includes intercom or key fob entry for added safety and peace of mind.</li></ul><p><strong>Laundry facilities</strong></p><ul><li> In-unit washer/dryer setup or access to shared laundry room within the building (varies by complex).</li></ul><p><strong>Lift access</strong></p><ul><li> Elevator service to all floors, suitable for easy moving and accessibility.</li></ul><p><strong>Convenient location</strong></p><ul><li> Close to public transport (bus, train, or tram), supermarkets, cafes, parks, and other essential services — perfect for everyday convenience.</li></ul>', 'PR9H+G76, Voi Rd, Nairobi, Kenya', NULL, NULL, NULL, -1.28122900, 36.82795500, 20000, 13000.00, 1, 12, 1, 'monthly', 5.00, 1, -3, 300.00, NULL, '1754567903_main_WhatsApp Image 2025-08-07 at 14.39.16.jpeg', 0, 1, '2025-08-07 11:58:23', '2025-08-07 12:03:00', 'PR9H+G76, Voi Rd, Nairobi, Kenya', 50, 12, '[]', NULL),
(46, 4, 'Zetech Building', 19, '<p>Described</p>', 'PR9H+G76, Voi Rd, Nairobi, Kenya', NULL, NULL, NULL, -1.28122900, 36.82795500, 32, 32.00, 1, 12, 1, 'monthly', 5.00, 4, 3, 500.00, NULL, '1754568064_main_WhatsApp Image 2025-07-29 at 20.02.21 (2).jpeg', 0, 1, '2025-08-07 12:01:04', '2025-08-07 12:03:00', 'PR9H+G76, Voi Rd, Nairobi, Kenya', 1, 1, '[]', NULL),
(47, 4, 'The Willow Haven', 25, '<p>✔ <strong>Spacious Living</strong> – Open-concept living and dining areas with high ceilings and large windows for natural light</p><p> ✔ <strong>Modern Kitchen</strong> – Fully fitted with granite countertops, an island, ample cabinetry, and built-in stainless steel appliances</p><p> ✔ <strong>Luxurious Bedrooms</strong> – Five generously sized bedrooms, including a stunning master suite with a walk-in closet and spa-style en-suite bathroom</p><p> ✔ <strong>Multiple Bathrooms</strong> – Elegant bathrooms with quality fixtures, bathtub/shower combos, and a guest powder room</p><p> ✔ <strong>Private Study/Office</strong> – Ideal for remote work or quiet reading space</p><p> ✔ <strong>Outdoor Retreat</strong> – Landscaped backyard with a covered patio, perfect for entertaining or relaxing</p><p> ✔ <strong>Convenience Features</strong> – Laundry room, pantry, storage areas, and a two-car garage</p><p> ✔ <strong>Prime Location</strong> – Located near top schools, shopping centers, recreational parks, and major transport links</p>', 'PRJV+VWV, Kirongothi St, Nairobi, Kenya', NULL, NULL, NULL, -1.26785400, 36.84515300, 100000, 1.00, 1, 12, 1, 'monthly', 5.00, 5, 4, 200.00, NULL, '1754568145_main_Modern 5 Bedroom Mansion - ID 25706.jpeg', 0, 1, '2025-08-07 12:02:25', '2025-08-07 12:03:00', 'PRJV+VWV, Kirongothi St, Nairobi, Kenya', 6, 2, '[]', NULL),
(49, 4, 'The Willow Haven', 25, '<p>✔ <strong>Spacious Living</strong> – Open-concept living and dining areas with high ceilings and large windows for natural light</p><p> ✔ <strong>Modern Kitchen</strong> – Fully fitted with granite countertops, an island, ample cabinetry, and built-in stainless steel appliances</p><p> ✔ <strong>Luxurious Bedrooms</strong> – Five generously sized bedrooms, including a stunning master suite with a walk-in closet and spa-style en-suite bathroom</p><p> ✔ <strong>Multiple Bathrooms</strong> – Elegant bathrooms with quality fixtures, bathtub/shower combos, and a guest powder room</p><p> ✔ <strong>Private Study/Office</strong> – Ideal for remote work or quiet reading space</p><p> ✔ <strong>Outdoor Retreat</strong> – Landscaped backyard with a covered patio, perfect for entertaining or relaxing</p><p> ✔ <strong>Convenience Features</strong> – Laundry room, pantry, storage areas, and a two-car garage</p><p> ✔ <strong>Prime Location</strong> – Located near top schools, shopping centers, recreational parks, and major transport links</p>', 'PRJV+VWV, Kirongothi St, Nairobi, Kenya', NULL, NULL, NULL, -1.26785400, 36.84515300, 100000, 1.00, 1, 12, 1, 'monthly', 5.00, 5, 4, 200.00, NULL, '1754568193_main_Modern 5 Bedroom Mansion - ID 25706.jpeg', 0, 1, '2025-08-07 12:03:13', '2025-08-07 12:03:13', 'PRJV+VWV, Kirongothi St, Nairobi, Kenya', 6, 2, '[\"electricity\",\"water\",\"internet\",\"gas\",\"parking\",\"security\",\"gym\",\"pool\",\"garden\",\"balcony\",\"elevator\",\"ac\",\"furnished\"]', ''),
(50, 4, 'Denver\'s Building', 25, '<p>A very huge house</p>', 'RXW7+W5G, Ruiru, Kenya', NULL, NULL, NULL, -1.15329800, 36.96313900, 350000, 350000.00, 1, 12, 1, 'monthly', 5.00, 2, 2, 500.00, NULL, '1754568875_main_Huge house!!.jpg', 0, 1, '2025-08-07 12:14:35', '2025-08-07 12:14:35', 'RXW7+W5G, Ruiru, Kenya', 1, 1, '[\"electricity\",\"water\",\"internet\",\"parking\",\"security\",\"gym\"]', ''),
(51, 4, 'Metro Nest', 22, '<p>Step into comfort and convenience with this beautifully presented 2-bedroom house-style apartment. Offering generous living spaces, modern finishes, and a smart layout, this home is perfect for small families, professionals, or anyone seeking extra space and flexibility.</p><h4>🛋️ <strong>Key Features:</strong></h4><ul><li><strong>Two generously sized bedrooms</strong> with built-in wardrobes; ideal for a master bedroom and guest room, home office, or children’s room</li><li><strong>Open-plan living and dining area</strong> filled with natural light, perfect for entertaining or relaxing</li><li><strong>Well-appointed kitchen</strong> with modern appliances, plenty of cupboard space, and sleek countertops</li><li><strong>Bright, tiled bathroom</strong> with shower, vanity, and toilet; clean and easy to maintain</li><li><strong>Private balcony/patio</strong> (if applicable), offering outdoor space for fresh air and relaxation</li><li><strong>Internal laundry facilities</strong> (in-unit or shared, depending on complex)</li><li><strong>Unfurnished</strong>, giving you the freedom to make it your own</li><li><strong>Secure entry</strong>, off-street parking or on-site parking (subject to availability)</li></ul>', 'PR66+F9W, Nairobi, Kenya', NULL, NULL, NULL, -1.28839200, 36.81119300, 30000, 20000.00, 1, 12, 1, 'monthly', 5.00, 2, 2, 500.00, NULL, '1754568882_main_WhatsApp Image 2025-08-07 at 14.39.12.jpeg', 0, 1, '2025-08-07 12:14:42', '2025-08-07 12:14:42', 'PR66+F9W, Nairobi, Kenya', 80, 18, '[\"electricity\",\"water\",\"parking\",\"security\",\"balcony\",\"elevator\",\"furnished\"]', ''),
(52, 4, 'The Amber Nest', 24, '<p>✔ <strong>Inviting Living Spaces</strong> – Open-plan lounge and dining area with elegant finishes and natural light flow</p><p> ✔ <strong>Contemporary Kitchen</strong> – Fully equipped with sleek cabinetry, granite countertops, and a central island</p><p> ✔ <strong>Comfortable Bedrooms</strong> – Four spacious bedrooms including a luxurious master suite with a walk-in wardrobe and en-suite bathroom</p><p> ✔ <strong>Modern Bathrooms</strong> – Tastefully designed with quality fittings, including a shared bathroom and guest washroom</p><p> ✔ <strong>Multi-Use Room</strong> – Bonus space for a home office, playroom, or TV lounge</p><p> ✔ <strong>Outdoor Charm</strong> – Private garden and shaded patio, ideal for weekend relaxation or entertaining guests</p><p> ✔ <strong>Utility &amp; Storage</strong> – Includes a laundry area, pantry, and optional servant’s quarters or garage</p><p> ✔ <strong>Ideal Location</strong> – Situated in a peaceful neighborhood close to schools, shopping areas, healthcare facilities, and public transport</p>', 'PRJV+WJP, Juja Rd, Nairobi, Kenya', NULL, NULL, NULL, -1.26785400, 36.84412300, 100000, 1.00, 1, 12, 1, 'monthly', 5.00, 4, 3, 100.00, NULL, '1754569345_main_4 bedroom house.jpeg', 0, 1, '2025-08-07 12:22:25', '2025-08-07 12:22:25', 'PRJV+WJP, Juja Rd, Nairobi, Kenya', 10, 6, '[\"electricity\",\"water\",\"internet\",\"gas\",\"parking\",\"security\",\"gym\",\"pool\",\"garden\",\"balcony\",\"elevator\",\"ac\",\"furnished\"]', ''),
(53, 4, 'Chebet\'s Villa', 26, '<h3>🏡 <strong>Expansive 6-Bedroom Family Home – Space, Comfort &amp; Versatility</strong></h3><p>Welcome to this generously sized 6-bedroom home, perfectly designed for large families, multi-generational living, or those who simply need extra space. Set in a quiet and convenient location, this property offers multiple living zones, a functional layout, and the flexibility to suit a wide range of lifestyles.</p><h4>🛋️ <strong>Key Features:</strong></h4><ul><li><strong>Six spacious bedrooms</strong>, each with built-in wardrobes; ideal for large families, guest rooms, home offices, or study spaces</li><li><strong>Multiple living areas</strong> including a formal lounge, open-plan family room, and a separate dining area</li><li><strong>Modern kitchen</strong> with ample bench space, storage, and quality appliances – perfect for everyday cooking or entertaining</li><li><strong>Three bathrooms</strong> (or more, depending on the layout), ensuring comfort and convenience for larger households</li><li><strong>Private outdoor area</strong> – ideal for kids, pets, or weekend gatherings</li><li><strong>Laundry room</strong> with external access</li><li><strong>Ample storage throughout</strong> the home, including linen closets and extra cupboard space</li><li><strong>Unfurnished</strong>, ready for you to add your personal touch</li><li><strong>Secure parking</strong> – double garage or off-street parking for multiple vehicles</li></ul><p><br></p>', '209, Nairobi, Kenya', NULL, NULL, NULL, -1.28385900, 36.80728700, 500000, 300000.00, 1, 12, 1, 'monthly', 5.00, 6, 7, 1000.00, NULL, '1754569622_main_download (13).jpeg', 0, 1, '2025-08-07 12:27:02', '2025-08-07 12:29:38', '209, Nairobi, Kenya', 1, 0, '[\"electricity\",\"water\",\"internet\",\"gas\",\"parking\",\"security\",\"gym\",\"pool\",\"garden\",\"balcony\",\"elevator\",\"ac\",\"furnished\"]', '');

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
(39, 42, 'image', '', '1753997591_tenant_schedule.png', '2025-07-31 21:33:11', '2025-07-31 21:33:11'),
(40, 42, 'image', '', '1753997591_shceduled.png', '2025-07-31 21:33:11', '2025-07-31 21:33:11'),
(41, 43, 'image', '', '1754567463_bathroom 2.jpeg', '2025-08-07 11:51:03', '2025-08-07 11:51:03'),
(42, 43, 'image', '', '1754567463_closet.jpeg', '2025-08-07 11:51:03', '2025-08-07 11:51:03'),
(43, 43, 'image', '', '1754567463_bathroom 1.jpeg', '2025-08-07 11:51:03', '2025-08-07 11:51:03'),
(44, 43, 'image', '', '1754567463_Bedroom Inspiration.jpeg', '2025-08-07 11:51:03', '2025-08-07 11:51:03'),
(45, 43, 'image', '', '1754567463_modern bedroom design.jpeg', '2025-08-07 11:51:03', '2025-08-07 11:51:03'),
(46, 44, 'image', '', '1754567466_bathroom 2.jpeg', '2025-08-07 11:51:06', '2025-08-07 11:51:06'),
(47, 44, 'image', '', '1754567466_closet.jpeg', '2025-08-07 11:51:06', '2025-08-07 11:51:06'),
(48, 44, 'image', '', '1754567466_bathroom 1.jpeg', '2025-08-07 11:51:06', '2025-08-07 11:51:06'),
(49, 44, 'image', '', '1754567466_Bedroom Inspiration.jpeg', '2025-08-07 11:51:06', '2025-08-07 11:51:06'),
(50, 44, 'image', '', '1754567466_modern bedroom design.jpeg', '2025-08-07 11:51:06', '2025-08-07 11:51:06'),
(51, 49, 'image', '', '1754568193_41 Butler’s Pantry Ideas That Combine Flair and Function.jpeg', '2025-08-07 12:03:13', '2025-08-07 12:03:13'),
(52, 49, 'image', '', '1754568193_✨ Create the perfect outdoor retreat! 🌿 From cozy pallet seating to stylish illuminated lounges, these outdoor sitting ideas redefine comfort and elegance_ 🌙 Which one is your dream setup_ 💡 #OutdoorLiving #Gar.jpeg', '2025-08-07 12:03:13', '2025-08-07 12:03:13'),
(53, 49, 'image', '', '1754568193_private study.jpeg', '2025-08-07 12:03:13', '2025-08-07 12:03:13'),
(54, 49, 'image', '', '1754568193_modern kitchen.jpeg', '2025-08-07 12:03:13', '2025-08-07 12:03:13'),
(55, 49, 'image', '', '1754568193_Bedroom 2.jpeg', '2025-08-07 12:03:13', '2025-08-07 12:03:13'),
(56, 49, 'image', '', '1754568193_bedroom 1.jpeg', '2025-08-07 12:03:13', '2025-08-07 12:03:13'),
(57, 50, 'video', '', '1754568875_WhatsApp Video 2025-03-18 at 17.08.38.mp4', '2025-08-07 12:14:35', '2025-08-07 12:14:35'),
(58, 51, 'image', '', '1754568882_download (7).jpeg', '2025-08-07 12:14:42', '2025-08-07 12:14:42'),
(59, 51, 'image', '', '1754568882_Beautiful Bathroom Design 🤍🤎.jpeg', '2025-08-07 12:14:42', '2025-08-07 12:14:42'),
(60, 51, 'image', '', '1754568882_download (6).jpeg', '2025-08-07 12:14:42', '2025-08-07 12:14:42'),
(61, 51, 'image', '', '1754568882_download (5).jpeg', '2025-08-07 12:14:42', '2025-08-07 12:14:42'),
(62, 51, 'image', '', '1754568882_download (1).jpeg', '2025-08-07 12:14:42', '2025-08-07 12:14:42'),
(63, 52, 'image', '', '1754569345_Pergola Design Ideas You Will Love.jpeg', '2025-08-07 12:22:25', '2025-08-07 12:22:25'),
(64, 52, 'image', '', '1754569345_gameroom.jpeg', '2025-08-07 12:22:25', '2025-08-07 12:22:25'),
(65, 52, 'image', '', '1754569345_Master Bathroom Ideas for a Stylish and Functional Space.jpeg', '2025-08-07 12:22:25', '2025-08-07 12:22:25'),
(66, 52, 'image', '', '1754569345_walk in closet.jpeg', '2025-08-07 12:22:25', '2025-08-07 12:22:25'),
(67, 52, 'image', '', '1754569345_Central Kitchen Island.jpeg', '2025-08-07 12:22:25', '2025-08-07 12:22:25'),
(68, 52, 'image', '', '1754569345_Spacious Open-Concept Living and Dining.jpeg', '2025-08-07 12:22:25', '2025-08-07 12:22:25'),
(69, 53, 'image', '', '1754569622_𝐴𝑙𝑙 𝑐𝑟𝑒𝑑𝑖𝑡𝑠 𝑡𝑜 𝑡ℎ𝑒 𝑜𝑤𝑛𝑒𝑟 ♡₊˚ 🦢・₊✧.jpeg', '2025-08-07 12:27:02', '2025-08-07 12:27:02'),
(70, 53, 'image', '', '1754569622_download (14).jpeg', '2025-08-07 12:27:02', '2025-08-07 12:27:02'),
(71, 53, 'image', '', '1754569622_Great Design for Teenager Bedroom_.jpeg', '2025-08-07 12:27:02', '2025-08-07 12:27:02'),
(72, 53, 'image', '', '1754569622_Modern Pottery Studio.jpeg', '2025-08-07 12:27:02', '2025-08-07 12:27:02'),
(73, 53, 'image', '', '1754569622_download (11).jpeg', '2025-08-07 12:27:02', '2025-08-07 12:27:02');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL COMMENT 'User ID of the tenant',
  `property_id` int(11) NOT NULL COMMENT 'House ID',
  `booking_id` int(11) NOT NULL COMMENT 'Rental booking ID',
  `title` varchar(255) NOT NULL COMMENT 'Short title of the issue',
  `description` text NOT NULL COMMENT 'Detailed description of the issue',
  `photo_url` varchar(500) DEFAULT NULL COMMENT 'Optional photo of the issue',
  `urgency` enum('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  `status` enum('Pending','In Progress','Completed','Rejected') NOT NULL DEFAULT 'Pending',
  `submission_date` datetime NOT NULL DEFAULT current_timestamp(),
  `assigned_repair_date` datetime DEFAULT NULL COMMENT 'Date when repair is scheduled',
  `assigned_technician` varchar(255) DEFAULT NULL COMMENT 'Name of assigned technician',
  `before_photo_url` varchar(500) DEFAULT NULL COMMENT 'Photo before repair',
  `after_photo_url` varchar(500) DEFAULT NULL COMMENT 'Photo after repair',
  `rejection_reason` text DEFAULT NULL COMMENT 'Reason if request is rejected',
  `rating` int(1) DEFAULT NULL COMMENT 'Star rating 1-5 after completion',
  `feedback` text DEFAULT NULL COMMENT 'Optional feedback after completion',
  `completion_date` datetime DEFAULT NULL COMMENT 'Date when work was completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_requests`
--

INSERT INTO `maintenance_requests` (`id`, `tenant_id`, `property_id`, `booking_id`, `title`, `description`, `photo_url`, `urgency`, `status`, `submission_date`, `assigned_repair_date`, `assigned_technician`, `before_photo_url`, `after_photo_url`, `rejection_reason`, `rating`, `feedback`, `completion_date`, `created_at`, `updated_at`) VALUES
(6, 8, 53, 76, 'Leaking Sink', 'My sink is leaking', NULL, 'Medium', 'In Progress', '2025-08-07 15:35:05', '2025-08-20 15:53:00', 'Tenges Kipruto', NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-07 12:35:05', '2025-08-07 12:53:34');

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
(654, 75, '2025-08-01', 120001.00, 'unpaid', 'initial_payment', 1, 0.00, 0.00, NULL, NULL, NULL, NULL, 'First month rent + security deposit', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(655, 75, '2025-09-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(656, 75, '2025-10-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(657, 75, '2025-11-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(658, 75, '2025-12-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(659, 75, '2026-01-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(660, 75, '2026-02-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(661, 75, '2026-03-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(662, 75, '2026-04-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(663, 75, '2026-05-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(664, 75, '2026-06-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(665, 75, '2026-07-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(666, 75, '2026-08-01', 120000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(667, 76, '2025-08-01', 800000.00, 'unpaid', 'initial_payment', 1, 0.00, 0.00, NULL, NULL, NULL, NULL, 'First month rent + security deposit', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(668, 76, '2025-09-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(669, 76, '2025-10-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(670, 76, '2025-11-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(671, 76, '2025-12-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(672, 76, '2026-01-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(673, 76, '2026-02-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(674, 76, '2026-03-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(675, 76, '2026-04-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(676, 76, '2026-05-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(677, 76, '2026-06-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(678, 76, '2026-07-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(679, 76, '2026-08-01', 500000.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(680, 77, '2025-08-01', 2.00, 'unpaid', 'initial_payment', 1, 0.00, 0.00, NULL, NULL, NULL, NULL, 'First month rent + security deposit', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(681, 77, '2025-09-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(682, 77, '2025-10-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(683, 77, '2025-11-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(684, 77, '2025-12-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(685, 77, '2026-01-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(686, 77, '2026-02-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(687, 77, '2026-03-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(688, 77, '2026-04-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(689, 77, '2026-05-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(690, 77, '2026-06-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(691, 77, '2026-07-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(692, 77, '2026-08-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(693, 78, '2025-08-01', 2.00, 'unpaid', 'initial_payment', 1, 0.00, 0.00, NULL, NULL, NULL, NULL, 'First month rent + security deposit', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(694, 78, '2025-09-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(695, 78, '2025-10-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(696, 78, '2025-11-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(697, 78, '2025-12-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(698, 78, '2026-01-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(699, 78, '2026-02-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(700, 78, '2026-03-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(701, 78, '2026-04-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(702, 78, '2026-05-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(703, 78, '2026-06-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(704, 78, '2026-07-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44'),
(705, 78, '2026-08-01', 1.00, 'unpaid', 'monthly_rent', 0, 0.00, 0.00, NULL, NULL, NULL, NULL, 'Monthly rent payment', '2025-08-08 06:42:44', '2025-08-08 06:42:44');

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
(99, 76, 'ws_CO_070820251531132712512358', NULL, '254712512358', 800000.00, 'initial', 'RENTAL_76_1754569872', 'failed', '1', 'The balance is insufficient for the transaction.', NULL, NULL, '2025-08-07 12:31:13', '2025-08-07 12:31:25');

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
(75, 44, 120000.00, 4, 3, '2025-08-08', NULL, '2026-08-08', NULL, NULL, NULL, NULL, 1.00, 'pending', NULL, NULL, 'pending', NULL, NULL, NULL, '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(76, 53, 500000.00, 4, 8, '2025-08-17', NULL, '2026-08-17', NULL, NULL, NULL, NULL, 300000.00, 'pending', NULL, NULL, 'confirmed', 'Landlord action: approve', NULL, NULL, '2025-08-07 12:29:17', '2025-08-07 12:29:38'),
(77, 42, 1.00, 4, 3, '2025-08-11', NULL, '2026-08-11', NULL, NULL, NULL, NULL, 1.00, 'pending', NULL, NULL, 'pending', NULL, NULL, NULL, '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(78, 42, 1.00, 4, 3, '2025-08-09', NULL, '2026-08-09', NULL, NULL, NULL, NULL, 1.00, 'pending', NULL, NULL, 'pending', NULL, NULL, NULL, '2025-08-08 06:42:44', '2025-08-08 06:42:44');

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
(43, 45, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 12:00:14', '2025-08-06 12:00:14'),
(44, 46, '2025-08-01', 3.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 13:03:24', '2025-08-06 13:03:24'),
(45, 47, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 17:19:19', '2025-08-06 17:19:19'),
(46, 48, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-06 17:21:18', '2025-08-06 17:21:18'),
(47, 49, '2025-08-01', 25000.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:44:15', '2025-08-07 07:44:15'),
(48, 50, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:50:22', '2025-08-07 07:50:22'),
(49, 51, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:51:24', '2025-08-07 07:51:24'),
(50, 52, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:51:57', '2025-08-07 07:51:57'),
(51, 53, '2025-08-01', 25000.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:52:19', '2025-08-07 07:52:19'),
(52, 54, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:57:31', '2025-08-07 07:57:31'),
(53, 55, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:57:39', '2025-08-07 07:57:39'),
(54, 56, '2025-08-01', 25000.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:58:07', '2025-08-07 07:58:07'),
(55, 57, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:58:16', '2025-08-07 07:58:16'),
(56, 58, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:58:52', '2025-08-07 07:58:52'),
(57, 59, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:59:23', '2025-08-07 07:59:23'),
(58, 60, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 07:59:37', '2025-08-07 07:59:37'),
(59, 61, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:00:12', '2025-08-07 08:00:12'),
(60, 62, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:00:21', '2025-08-07 08:00:21'),
(61, 63, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:02:06', '2025-08-07 08:02:06'),
(62, 64, '2025-08-01', 25000.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:02:19', '2025-08-07 08:02:19'),
(63, 65, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:03:23', '2025-08-07 08:03:23'),
(64, 66, '2025-08-01', 25000.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:03:35', '2025-08-07 08:03:35'),
(65, 67, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:10:53', '2025-08-07 08:10:53'),
(66, 68, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:11:05', '2025-08-07 08:11:05'),
(67, 69, '2025-08-01', 25000.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:12:21', '2025-08-07 08:12:21'),
(68, 70, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:12:36', '2025-08-07 08:12:36'),
(69, 71, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:13:22', '2025-08-07 08:13:22'),
(70, 72, '2025-08-01', 4500.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:15:27', '2025-08-07 08:15:27'),
(71, 73, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:16:43', '2025-08-07 08:16:43'),
(72, 74, '2025-08-01', 25000.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 08:17:05', '2025-08-07 08:17:05'),
(73, 75, '2025-08-01', 120000.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 11:58:04', '2025-08-07 11:58:04'),
(74, 76, '2025-08-01', 500000.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-07 12:29:17', '2025-08-07 12:29:17'),
(75, 77, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-08 06:15:17', '2025-08-08 06:15:17'),
(76, 78, '2025-08-01', 1.00, 0.00, 0.00, 0.00, 'pending', '2025-08-05', NULL, '2025-08-08 06:42:44', '2025-08-08 06:42:44');

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
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_property_id` (`property_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_urgency` (`urgency`),
  ADD KEY `idx_submission_date` (`submission_date`);

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
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `houses`
--
ALTER TABLE `houses`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `house_media`
--
ALTER TABLE `house_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `monthly_rent_payments`
--
ALTER TABLE `monthly_rent_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=706;

--
-- AUTO_INCREMENT for table `mpesa_payment_requests`
--
ALTER TABLE `mpesa_payment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `property_viewings`
--
ALTER TABLE `property_viewings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rental_bookings`
--
ALTER TABLE `rental_bookings`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `rent_payments`
--
ALTER TABLE `rent_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

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
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_requests_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `rental_bookings` (`id`) ON DELETE CASCADE;

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
