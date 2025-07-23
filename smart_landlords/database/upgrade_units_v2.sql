-- Modify units columns to allow zero
ALTER TABLE houses 
MODIFY COLUMN total_units INT DEFAULT 0 NOT NULL,
MODIFY COLUMN available_units INT DEFAULT 0 NOT NULL;
