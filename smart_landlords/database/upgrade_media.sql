-- Add updated_at field to house_media table
ALTER TABLE house_media 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add index for better performance
CREATE INDEX idx_house_media_updated ON house_media(updated_at);
