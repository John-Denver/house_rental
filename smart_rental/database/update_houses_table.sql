-- Add bedrooms column
ALTER TABLE houses ADD COLUMN bedrooms INT DEFAULT 0 AFTER price;

-- Add bathrooms column
ALTER TABLE houses ADD COLUMN bathrooms INT DEFAULT 0 AFTER bedrooms;

-- Add area column
ALTER TABLE houses ADD COLUMN area DECIMAL(10,2) AFTER bathrooms;

-- Add image column
ALTER TABLE houses ADD COLUMN image VARCHAR(255) AFTER area;

-- Add featured column
ALTER TABLE houses ADD COLUMN featured TINYINT(1) DEFAULT 0 AFTER image;

-- Add status column
ALTER TABLE houses ADD COLUMN status TINYINT(1) DEFAULT 1 AFTER featured;

-- Add created_at column
ALTER TABLE houses ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status;

-- Add updated_at column
ALTER TABLE houses ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add location columns
ALTER TABLE houses ADD COLUMN location VARCHAR(255) AFTER description;
ALTER TABLE houses ADD COLUMN city VARCHAR(100) AFTER location;
ALTER TABLE houses ADD COLUMN state VARCHAR(100) AFTER city;
ALTER TABLE houses ADD COLUMN country VARCHAR(100) AFTER state;
ALTER TABLE houses ADD COLUMN latitude DECIMAL(10,8) AFTER country;
ALTER TABLE houses ADD COLUMN longitude DECIMAL(11,8) AFTER latitude;

-- Add indexes for better performance
CREATE INDEX idx_category ON houses(category_id);
CREATE INDEX idx_status ON houses(status);
CREATE INDEX idx_featured ON houses(featured);
CREATE INDEX idx_price ON houses(price);
CREATE INDEX idx_bedrooms ON houses(bedrooms);
CREATE INDEX idx_bathrooms ON houses(bathrooms);
CREATE INDEX idx_location ON houses(location);
CREATE INDEX idx_city ON houses(city);
CREATE INDEX idx_state ON houses(state);
CREATE INDEX idx_country ON houses(country);
