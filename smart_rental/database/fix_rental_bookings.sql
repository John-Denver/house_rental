-- Fix rental_bookings table structure
-- This script ensures the table has all required columns

-- Add monthly_rent column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS monthly_rent DECIMAL(15,2) NOT NULL DEFAULT 0.00;

-- Add landlord_id column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS landlord_id INT(30) NOT NULL DEFAULT 1;

-- Add end_date column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS end_date DATE NOT NULL DEFAULT '2025-12-31';

-- Add special_requests column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS special_requests TEXT DEFAULT NULL;

-- Add security_deposit column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS security_deposit DECIMAL(15,2) DEFAULT 0.00;

-- Add payment_status column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','partial','paid','refunded','cancelled') NOT NULL DEFAULT 'pending';

-- Add payment_method column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT NULL;

-- Add payment_reference column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) DEFAULT NULL;

-- Add status column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS status ENUM('pending','confirmed','cancelled','completed','active') NOT NULL DEFAULT 'pending';

-- Add cancellation_reason column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS cancellation_reason TEXT DEFAULT NULL;

-- Add cancelled_by column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS cancelled_by ENUM('tenant','landlord','system') DEFAULT NULL;

-- Add documents column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS documents TEXT DEFAULT NULL COMMENT 'JSON array of document paths';

-- Add created_at column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Add updated_at column if it doesn't exist
ALTER TABLE rental_bookings ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Show the final table structure
DESCRIBE rental_bookings; 