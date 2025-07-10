-- Drop existing table if it exists
DROP TABLE IF EXISTS houses;

-- Create houses table
CREATE TABLE houses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    house_no VARCHAR(50) NOT NULL UNIQUE,
    category_id INT NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    bedrooms INT DEFAULT 0,
    bathrooms INT DEFAULT 0,
    area DECIMAL(10,2),
    image VARCHAR(255),
    featured TINYINT(1) DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Example data
INSERT INTO houses (house_no, category_id, description, price, bedrooms, bathrooms, area, image, featured, status) VALUES
('H001', 1, 'Spacious 3-bedroom apartment in central location', 150000, 3, 2, 1200, 'images/house1.jpg', 1, 1),
('H002', 2, 'Modern 2-bedroom house with garden', 120000, 2, 1, 850, 'images/house2.jpg', 0, 1),
('H003', 3, 'Luxury penthouse with amazing views', 250000, 4, 3, 1800, 'images/house3.jpg', 1, 1),
('H004', 1, 'Cozy studio apartment', 80000, 1, 1, 450, 'images/house4.jpg', 0, 1),
('H005', 2, 'Family-friendly 4-bedroom house', 180000, 4, 2, 1500, 'images/house5.jpg', 0, 1),
('H006', 3, 'Modern townhouse with garage', 140000, 3, 2, 1000, 'images/house6.jpg', 1, 1);

-- Create indexes for better performance
CREATE INDEX idx_category ON houses(category_id);
CREATE INDEX idx_status ON houses(status);
CREATE INDEX idx_featured ON houses(featured);
CREATE INDEX idx_price ON houses(price);
