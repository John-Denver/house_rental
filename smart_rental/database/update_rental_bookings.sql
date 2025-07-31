-- Add monthly_rent column to rental_bookings table
ALTER TABLE `rental_bookings` 
ADD COLUMN `monthly_rent` DECIMAL(15,2) NOT NULL AFTER `house_id`,
MODIFY COLUMN `status` ENUM('pending', 'confirmed', 'cancelled', 'completed', 'active') NOT NULL DEFAULT 'pending';

-- Update existing records to set monthly_rent from the related house
UPDATE rental_bookings rb
JOIN houses h ON rb.house_id = h.id
SET rb.monthly_rent = h.price;

-- Remove rental_period column as it's no longer needed
ALTER TABLE `rental_bookings` 
DROP COLUMN `rental_period`,
DROP COLUMN `total_amount`;

-- Update the status of existing bookings to 'active' if they are confirmed and not completed
UPDATE rental_bookings 
SET status = 'active' 
WHERE status = 'confirmed' 
AND end_date >= CURDATE();
