-- Add callback_data column to mpesa_payment_requests table
-- This column stores the full callback data from M-Pesa for debugging and verification

ALTER TABLE mpesa_payment_requests 
ADD COLUMN callback_data TEXT NULL COMMENT 'Full callback data from M-Pesa API' 
AFTER transaction_date;

-- Add index for better performance on status queries
CREATE INDEX idx_mpesa_payment_requests_status ON mpesa_payment_requests(status);
CREATE INDEX idx_mpesa_payment_requests_checkout_id ON mpesa_payment_requests(checkout_request_id);

-- Add payment_status column to rental_bookings if it doesn't exist
ALTER TABLE rental_bookings 
ADD COLUMN payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending' 
AFTER status;

-- Add index for payment status queries
CREATE INDEX idx_rental_bookings_payment_status ON rental_bookings(payment_status); 