-- Add location columns to houses table
ALTER TABLE houses 
ADD COLUMN latitude DECIMAL(10,8) NOT NULL,
ADD COLUMN longitude DECIMAL(11,8) NOT NULL,
ADD COLUMN address TEXT,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Create index for better performance
CREATE INDEX idx_houses_location ON houses(latitude, longitude);

-- Update existing properties with default Nairobi coordinates
UPDATE houses SET latitude = -1.2833, longitude = 36.8167 WHERE latitude = 0 AND longitude = 0;
