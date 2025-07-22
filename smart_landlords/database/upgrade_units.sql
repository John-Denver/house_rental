-- Add units columns to houses table
ALTER TABLE houses 
ADD COLUMN total_units INT DEFAULT 1 NOT NULL,
ADD COLUMN available_units INT DEFAULT 1 NOT NULL;

-- Add index for better performance
CREATE INDEX idx_houses_units ON houses(available_units);

-- Update existing properties with default values
UPDATE houses SET total_units = 1, available_units = 1 WHERE total_units IS NULL OR available_units IS NULL;
