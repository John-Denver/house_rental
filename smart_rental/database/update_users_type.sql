-- Drop existing type column if it exists and recreate with new ENUM values
ALTER TABLE users 
DROP COLUMN IF EXISTS type;

ALTER TABLE users 
ADD COLUMN type ENUM('admin', 'landlord', 'caretaker', 'customer') DEFAULT 'customer' NOT NULL AFTER password;

-- Update existing users to set appropriate type
-- You might want to adjust these values based on your actual user base
UPDATE users 
SET type = 'admin' 
WHERE email = 'admin@yourdomain.com';

-- Add indexes for better performance
ALTER TABLE users 
ADD INDEX idx_type (type);
