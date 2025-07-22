-- Add phone number column to users table
ALTER TABLE users 
ADD COLUMN phone_number VARCHAR(20) NULL 
COMMENT 'User\'s contact phone number' 
AFTER email;
