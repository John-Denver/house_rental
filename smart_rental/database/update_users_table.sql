-- Add username and type columns to users table
ALTER TABLE users 
ADD COLUMN username VARCHAR(50) NOT NULL UNIQUE AFTER id,
ADD COLUMN type ENUM('user', 'admin') DEFAULT 'user' AFTER password;

-- Update existing users to have default usernames (based on email)
UPDATE users 
SET username = SUBSTRING_INDEX(email, '@', 1);

-- Add indexes for better performance
ALTER TABLE users 
ADD INDEX idx_username (username),
ADD INDEX idx_type (type);
