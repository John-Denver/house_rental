-- Create rent_payments table to track monthly rent payments
CREATE TABLE IF NOT EXISTS rent_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    payment_date DATE,
    due_date DATE NOT NULL,
    status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',
    balance_forwarded DECIMAL(10,2) DEFAULT 0.00,
    late_fee DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_status (booking_id, status),
    INDEX idx_due_date (due_date)
);

-- Add balance column to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS current_balance DECIMAL(10,2) DEFAULT 0.00;

-- Add last_payment_date to bookings table
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS last_payment_date DATE DEFAULT NULL;

-- Add monthly_rent to bookings table
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS monthly_rent DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- Add late_fee_percentage to bookings table
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS late_fee_percentage DECIMAL(5,2) DEFAULT 5.00;
