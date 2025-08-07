-- Add utilities columns to houses table
ALTER TABLE houses 
ADD COLUMN utilities TEXT NULL COMMENT 'JSON array of included utilities',
ADD COLUMN utilities_notes TEXT NULL COMMENT 'Additional notes about utilities';

-- Update existing records to have empty utilities
UPDATE houses SET utilities = '[]' WHERE utilities IS NULL;
