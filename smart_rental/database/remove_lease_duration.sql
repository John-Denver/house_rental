-- Migration: Remove lease_duration and total_rent from bookings table
-- Date: 2025-01-27
-- Description: Remove lease duration and total rent columns as rent is now calculated monthly on rollover basis

-- Remove lease_duration column
ALTER TABLE bookings DROP COLUMN IF EXISTS lease_duration;

-- Remove total_rent column  
ALTER TABLE bookings DROP COLUMN IF EXISTS total_rent;

-- Add monthly_rent column for reference (optional)
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS monthly_rent DECIMAL(10,2) NULL COMMENT 'Monthly rent amount for reference';

-- Update the setup.sql file to reflect the new structure
-- The bookings table should now have:
-- id, user_id, property_id, move_in_date, full_name, email, phone, status, notes, created_at, updated_at, monthly_rent 