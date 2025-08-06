<?php
// Define helper functions that would be in helpers.php
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

class BookingController {
    private $conn;
    private $uploadDir = __DIR__ . '/../uploads/documents/';
    private $allowedFileTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    private $maxFileSize = 5 * 1024 * 1024; // 5MB

    public function __construct($conn) {
        $this->conn = $conn;
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    public function createBooking($data, $files = []) {
        $this->conn->begin_transaction();
        
        try {
            // Validate input
            $this->validateBookingData($data);
            
            // Get property details and set up monthly rent
            $property = $this->getProperty($data['house_id']);
            $monthlyRent = $property['price'];
            
            // Validate security deposit is properly configured
            if (empty($property['security_deposit'])) {
                throw new Exception("Security deposit not configured for this property. Please contact the landlord.");
            }
            $securityDeposit = $property['security_deposit'];
            
            // Insert booking with monthly rent and landlord_id
            $stmt = $this->conn->prepare("
                INSERT INTO rental_bookings (
                    house_id, landlord_id, user_id, start_date, end_date,
                    special_requests, status, security_deposit, monthly_rent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");
            
            // Get user details
            $userStmt = $this->conn->prepare("SELECT id FROM users WHERE id = ?");
            $userStmt->bind_param('i', $_SESSION['user_id']);
            $userStmt->execute();
            $user = $userStmt->get_result()->fetch_assoc();
            
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Get landlord_id from the property
            $landlordId = $property['landlord_id'] ?? 1; // Default to 1 if not set
            
            // Calculate end date based on property settings
            $rentalPeriod = $property['max_rental_period'] ?? 12; // Default to 12 months
            $endDate = date('Y-m-d', strtotime($data['start_date'] . ' +' . $rentalPeriod . ' months'));
            
            // Prepare special_requests variable
            $specialRequests = $data['special_requests'] ?? null;
            
            $stmt->bind_param(
                'iiisssdd',
                $data['house_id'],
                $landlordId,
                $_SESSION['user_id'],
                $data['start_date'],
                $endDate,
                $specialRequests,
                $securityDeposit,
                $monthlyRent
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create booking: " . $stmt->error);
            }
            
            $bookingId = $this->conn->insert_id;
            
            // Handle document uploads
            if (!empty($files['documents'])) {
                $this->handleDocumentUploads($bookingId, $files['documents']);
            }
            
            // Create initial payment record for security deposit (status: pending)
            $this->recordPayment($bookingId, $securityDeposit, 'deposit');
            
            // Create monthly payment records immediately
            $this->createMonthlyPaymentRecords($bookingId);
            
            // Note: Units are NOT decremented here because booking is still 'pending'
            // Units will only be decremented when booking status changes to 'confirmed'
            
            $this->conn->commit();
            
            // Send notifications
            $this->sendBookingConfirmation($bookingId);
            
            return [
                'success' => true,
                'booking_id' => $bookingId,
                'message' => 'Booking created successfully! Please complete your payment to secure your booking.'
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function validateBookingData($data) {
        $required = ['house_id', 'start_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required");
            }
        }
        
        // Validate start date
        $startDate = strtotime($data['start_date']);
        $today = strtotime('today');
        
        if ($startDate < $today) {
            throw new Exception("Start date cannot be in the past");
        }
        
        if ($startDate > strtotime('+6 months')) {
            throw new Exception("Bookings cannot be made more than 6 months in advance");
        }
        
        // Check property availability (includes unit availability)
        if (!$this->isPropertyAvailable($data['house_id'], $data['start_date'])) {
            throw new Exception("The property is not available for the selected date");
        }
    }
    
    private function isPropertyAvailable($propertyId, $startDate) {
        // First check if property has available units
        if (!$this->hasAvailableUnits($propertyId)) {
            return false;
        }
        
        // Then check for existing bookings on the same date
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM rental_bookings 
            WHERE house_id = ? 
            AND status NOT IN ('cancelled', 'completed')
            AND start_date = ?
        ");
        
        $stmt->bind_param('is', $propertyId, $startDate);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['count'] == 0;
    }
    
    private function handleDocumentUploads($bookingId, $files) {
        foreach ($files['tmp_name'] as $key => $tmpName) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $fileType = mime_content_type($tmpName);
                
                if (!in_array($fileType, $this->allowedFileTypes)) {
                    throw new Exception("Invalid file type: " . $files['name'][$key]);
                }
                
                if ($files['size'][$key] > $this->maxFileSize) {
                    throw new Exception("File too large: " . $files['name'][$key]);
                }
                
                $fileName = uniqid('doc_') . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $files['name'][$key]);
                $filePath = $this->uploadDir . $fileName;
                
                if (move_uploaded_file($tmpName, $filePath)) {
                    $stmt = $this->conn->prepare("
                        INSERT INTO booking_documents 
                        (booking_id, document_type, file_path) 
                        VALUES (?, ?, ?)
                    ");
                    
                    $documentType = pathinfo($files['name'][$key], PATHINFO_EXTENSION);
                    $relativePath = 'uploads/documents/' . $fileName;
                    
                    $stmt->bind_param('iss', $bookingId, $documentType, $relativePath);
                    if (!$stmt->execute()) {
                        unlink($filePath); // Clean up file if DB insert fails
                        throw new Exception("Failed to save document record");
                    }
                } else {
                    throw new Exception("Failed to upload file: " . $files['name'][$key]);
                }
            }
        }
    }
    
    private function recordPayment($bookingId, $amount, $type = 'rent') {
        $stmt = $this->conn->prepare("
            INSERT INTO booking_payments (
                booking_id, amount, payment_method, status, payment_date
            ) VALUES (?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->bind_param('ids', $bookingId, $amount, $type);
        return $stmt->execute();
    }
    
    private function sendBookingConfirmation($bookingId) {
        // Get booking details with property and user information
        $booking = $this->getBookingDetails($bookingId);
        
        // Send email to tenant
        $to = $booking['tenant_email'];
        $subject = "Booking Confirmation #" . $bookingId;
        $message = "Your booking for " . $booking['property_name'] . " has been received.\n\n";
        $message .= "Check-in: " . $booking['start_date'] . "\n";
        $message .= "Check-out: " . $booking['end_date'] . "\n";
        $message .= "Monthly Rent: KSh " . number_format($booking['property_price']) . "\n\n";
        $message .= "Please login to your account to complete the payment and upload any required documents.\n";
        
        // In production, use a proper email library
        // mail($to, $subject, $message);
        
        // Note: Landlord email functionality commented out as we don't have landlord email in the current schema
        // $landlordSubject = "New Booking for " . $booking['property_name'];
        // $landlordMessage = "You have a new booking request. Please review it in your dashboard.";
        // mail($booking['landlord_email'], $landlordSubject, $landlordMessage);
    }
    
    public function getBookingDetails($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT 
                b.*, 
                h.house_no,
                h.house_no as property_name,
                h.price as property_price,
                h.location as property_location,
                h.main_image,
                h.bedrooms,
                h.bathrooms,
                h.description,
                u.name as tenant_name,
                u.username as tenant_email,
                u.phone_number as tenant_phone,
                l.name as landlord_name,
                l.username as landlord_email,
                l.phone_number as landlord_phone
            FROM rental_bookings b
            JOIN houses h ON b.house_id = h.id
            JOIN users u ON b.user_id = u.id
            LEFT JOIN users l ON h.landlord_id = l.id
            WHERE b.id = ?
        ");
        
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            throw new Exception("Booking not found");
        }
        
        // Add fallback values for missing fields
        $result['rental_period'] = 12; // Use numeric value for calculations
        $result['landlord_name'] = $result['landlord_name'] ?? 'Property Owner';
        $result['landlord_email'] = $result['landlord_email'] ?? 'contact@property.com';
        $result['landlord_phone'] = $result['landlord_phone'] ?? 'N/A';
        
        // Handle security deposit fallback for existing bookings
        if (empty($result['security_deposit']) || $result['security_deposit'] == 0) {
            $result['security_deposit'] = $result['property_price']; // Use property price as default
        }
        
        return $result;
    }
    
    public function getProperty($propertyId) {
        $stmt = $this->conn->prepare("SELECT * FROM houses WHERE id = ?");
        $stmt->bind_param('i', $propertyId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            throw new Exception("Property not found");
        }
        
        return $result;
    }
    
    public function getUserBookings($userId, $status = null) {
        $sql = "
            SELECT 
                b.*, 
                h.house_no, 
                h.location,
                h.main_image,
                (SELECT status FROM booking_payments WHERE booking_id = b.id ORDER BY payment_date DESC LIMIT 1) as payment_status
            FROM rental_bookings b
            JOIN houses h ON b.house_id = h.id
            WHERE b.user_id = ?
        ";
        
        $params = [$userId];
        $types = 'i';
        
        if ($status) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $sql .= " ORDER BY b.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function updateBookingStatus($bookingId, $status, $reason = null, $userId = null) {
        $this->conn->begin_transaction();
        
        try {
            $booking = $this->getBookingDetails($bookingId);
            
            // Validate status transition
            $this->validateStatusTransition($booking['status'], $status);
            
            $stmt = $this->conn->prepare("
                UPDATE rental_bookings 
                SET status = ?, 
                    cancellation_reason = ?,
                    cancelled_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $cancelledBy = null;
            if ($status === 'cancelled' && $userId) {
                $cancelledBy = ($userId === $booking['user_id']) ? 'tenant' : 'landlord';
            }
            
            $stmt->bind_param('sssi', $status, $reason, $cancelledBy, $bookingId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update booking status");
            }
            
            // Handle status-specific logic
            $this->handleStatusChange($bookingId, $status, $booking);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    private function validateStatusTransition($currentStatus, $newStatus) {
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled', 'rejected'],
            'confirmed' => ['completed', 'cancelled'],
            // Add other valid transitions
        ];
        
        if (isset($validTransitions[$currentStatus]) && 
            !in_array($newStatus, $validTransitions[$currentStatus])) {
            throw new Exception("Invalid status transition from $currentStatus to $newStatus");
        }
    }
    
    private function handleStatusChange($bookingId, $newStatus, $booking) {
        switch ($newStatus) {
            case 'confirmed':
                // Decrement available units when booking is confirmed
                $this->decrementPropertyUnits($booking['house_id']);
                $this->sendBookingConfirmation($bookingId);
                break;
                
            case 'cancelled':
                // Increment available units when booking is cancelled
                $this->incrementPropertyUnits($booking['house_id']);
                $this->processCancellation($booking);
                break;
                
            case 'completed':
                $this->processCompletion($booking);
                break;
        }
    }
    
    private function processCancellation($booking) {
        // Process refund if payment was made
        if ($booking['payment_status'] === 'paid') {
            $this->processRefund($booking['id'], $booking['total_amount']);
        }
        
        // Send cancellation notifications
        $this->sendCancellationNotification($booking);
    }
    
    private function processRefund($bookingId, $amount) {
        // In a real implementation, integrate with payment gateway
        // This is a placeholder for the refund logic
        $stmt = $this->conn->prepare("
            INSERT INTO booking_payments 
            (booking_id, amount, payment_date, payment_method, status, notes) 
            VALUES (?, ?, NOW(), 'refund', 'refunded', 'Refund for cancelled booking')
        ");
        
        $stmt->bind_param('id', $bookingId, $amount);
        $stmt->execute();
    }
    
    private function sendCancellationNotification($booking) {
        // Send email to tenant
        $to = $booking['tenant_email'];
        $subject = "Booking #" . $booking['id'] . " Cancelled";
        $message = "Your booking for " . $booking['property_name'] . " has been cancelled.\n";
        
        if (!empty($booking['cancellation_reason'])) {
            $message .= "Reason: " . $booking['cancellation_reason'] . "\n";
        }
        
        if ($booking['payment_status'] === 'paid') {
            $message .= "A refund of " . format_currency($booking['total_amount']) . " will be processed within 5-7 business days.\n";
        }
        
        mail($to, $subject, $message);
    }
    
    private function processCompletion($booking) {
        // Process security deposit return
        if ($booking['security_deposit'] > 0) {
            $this->processDepositReturn($booking);
        }
        
        // Request review
        $this->requestReview($booking);
    }
    
    private function processDepositReturn($booking) {
        // In a real implementation, process deposit return through payment gateway
        $stmt = $this->conn->prepare("
            INSERT INTO booking_payments 
            (booking_id, amount, payment_date, payment_method, status, notes) 
            VALUES (?, ?, NOW(), 'deposit_return', 'completed', 'Security deposit return')
        ");
        
        $stmt->bind_param('id', $booking['id'], $booking['security_deposit']);
        $stmt->execute();
    }
    
    private function requestReview($booking) {
        $reviewToken = bin2hex(random_bytes(32));
        $expiryDate = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $this->conn->prepare("
            INSERT INTO booking_review_tokens 
            (booking_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->bind_param('iss', $booking['id'], $reviewToken, $expiryDate);
        $stmt->execute();
        
        // Send review request email
        $reviewLink = "https://yourdomain.com/review/$reviewToken";
        $to = $booking['tenant_email'];
        $subject = "How was your stay at " . $booking['property_name'] . "?";
        $message = "Please take a moment to review your recent stay.\n\n";
        $message .= "Click here to leave a review: $reviewLink\n\n";
        $message .= "This link will expire in 30 days.\n";
        
        mail($to, $subject, $message);
    }

    /**
     * Get booking documents
     */
    public function getBookingDocuments($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM booking_documents 
            WHERE booking_id = ? 
            ORDER BY uploaded_at DESC
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get booking payments
     */
    public function getBookingPayments($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM booking_payments 
            WHERE booking_id = ? 
            ORDER BY payment_date DESC
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Check if booking has review
     */
    public function hasBookingReview($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM booking_reviews 
            WHERE booking_id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] > 0;
    }
    
    /**
     * Process payment for a booking
     */
    public function processPayment($paymentData) {
        // Start database transaction
        $this->conn->begin_transaction();
        
        try {
            // Validate payment data
            if (empty($paymentData['booking_id']) || empty($paymentData['amount'])) {
                throw new Exception("Missing required payment information");
            }
            
            $bookingId = $paymentData['booking_id'];
            $amount = $paymentData['amount'];
            $paymentMethod = $paymentData['payment_method'] ?? 'manual';
            $transactionId = $paymentData['transaction_id'] ?? null;
            $notes = $paymentData['notes'] ?? '';
            
            // Get booking details with lock to prevent race conditions
            $bookingStmt = $this->conn->prepare("
                SELECT * FROM rental_bookings 
                WHERE id = ? AND (status = 'pending' OR status = 'confirmed')
                FOR UPDATE
            ");
            $bookingStmt->bind_param('i', $bookingId);
            $bookingStmt->execute();
            $booking = $bookingStmt->get_result()->fetch_assoc();
            
            if (!$booking) {
                throw new Exception("Booking not found or not in valid state for payment");
            }
            
            // Check for duplicate payments
            $duplicateStmt = $this->conn->prepare("
                SELECT COUNT(*) as payment_count 
                FROM booking_payments 
                WHERE booking_id = ? AND status = 'completed'
            ");
            $duplicateStmt->bind_param('i', $bookingId);
            $duplicateStmt->execute();
            $duplicateResult = $duplicateStmt->get_result()->fetch_assoc();
            
            if ($duplicateResult['payment_count'] > 0) {
                throw new Exception("Payment already completed for this booking");
            }
            
            // Validate payment amount against booking requirements
            $expectedAmount = $this->calculateExpectedPaymentAmount($bookingId);
            if (abs($amount - $expectedAmount) > 0.01) { // Allow for small rounding differences
                throw new Exception("Payment amount does not match expected amount. Expected: KSh " . number_format($expectedAmount, 2));
            }
            
            // Record the payment
            $stmt = $this->conn->prepare("
                INSERT INTO booking_payments (
                    booking_id, amount, payment_method, transaction_id, 
                    status, payment_date, notes
                ) VALUES (?, ?, ?, ?, 'completed', NOW(), ?)
            ");
            
            $stmt->bind_param('idsss', 
                $bookingId, 
                $amount, 
                $paymentMethod, 
                $transactionId, 
                $notes
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to record payment: " . $stmt->error);
            }
            
            $paymentId = $this->conn->insert_id;
            
            // Update booking status to 'paid' with optimistic locking
            $updateStmt = $this->conn->prepare("
                UPDATE rental_bookings 
                SET status = 'paid', payment_status = 'paid', updated_at = NOW() 
                WHERE id = ? AND (status = 'pending' OR status = 'confirmed')
            ");
            
            $updateStmt->bind_param('i', $bookingId);
            $updateStmt->execute();
            
            if ($updateStmt->affected_rows === 0) {
                throw new Exception("Failed to update booking status - booking may have been modified");
            }
            
            // Commit transaction
            $this->conn->commit();
            
            // Send payment confirmation (outside transaction to avoid email delays)
            $this->sendPaymentConfirmation($bookingId, $amount, $paymentMethod);
            
            return [
                'success' => true,
                'message' => 'Payment processed successfully!',
                'payment_id' => $paymentId
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send payment confirmation
     */
    private function sendPaymentConfirmation($bookingId, $amount, $paymentMethod) {
        $booking = $this->getBookingDetails($bookingId);
        
        // Send email to tenant
        $to = $booking['tenant_email'];
        $subject = "Payment Confirmation - Booking #" . $bookingId;
        $message = "Your payment of KSh " . number_format($amount, 2) . " has been received.\n\n";
        $message .= "Payment Details:\n";
        $message .= "- Booking ID: #" . $bookingId . "\n";
        $message .= "- Property: " . $booking['house_no'] . "\n";
        $message .= "- Payment Method: " . ucfirst($paymentMethod) . "\n";
        $message .= "- Amount: KSh " . number_format($amount, 2) . "\n\n";
        $message .= "Your booking is now confirmed. You can access your booking details in your account.\n";
        
        // In production, use a proper email library
        // mail($to, $subject, $message);
        
        // Send notification to landlord
        $landlordSubject = "Payment Received - Booking #" . $bookingId;
        $landlordMessage = "Payment of KSh " . number_format($amount, 2) . " has been received for booking #" . $bookingId . ".\n";
        $landlordMessage .= "Property: " . $booking['house_no'] . "\n";
        $landlordMessage .= "Tenant: " . $booking['tenant_name'] . "\n";
        
        // mail($booking['landlord_email'], $landlordSubject, $landlordMessage);
    }
    
    /**
     * Decrement available units for a property
     */
    private function decrementPropertyUnits($propertyId) {
        $stmt = $this->conn->prepare("
            UPDATE houses 
            SET available_units = GREATEST(available_units - 1, 0) 
            WHERE id = ? AND available_units > 0
        ");
        $stmt->bind_param('i', $propertyId);
        
        if (!$stmt->execute()) {
            error_log("Failed to decrement units for property ID: $propertyId");
            throw new Exception("Failed to update property availability");
        }
        
        // Log the unit change
        $affectedRows = $stmt->affected_rows;
        if ($affectedRows > 0) {
            error_log("Successfully decremented units for property ID: $propertyId");
        } else {
            error_log("No units available to decrement for property ID: $propertyId");
        }
    }
    
    /**
     * Increment available units for a property
     */
    private function incrementPropertyUnits($propertyId) {
        $stmt = $this->conn->prepare("
            UPDATE houses 
            SET available_units = LEAST(available_units + 1, total_units) 
            WHERE id = ? AND available_units < total_units
        ");
        $stmt->bind_param('i', $propertyId);
        
        if (!$stmt->execute()) {
            error_log("Failed to increment units for property ID: $propertyId");
            throw new Exception("Failed to update property availability");
        }
        
        // Log the unit change
        $affectedRows = $stmt->affected_rows;
        if ($affectedRows > 0) {
            error_log("Successfully incremented units for property ID: $propertyId");
        } else {
            error_log("No units can be incremented for property ID: $propertyId (may be at max capacity)");
        }
    }
    
    /**
     * Check if property has available units
     */
    private function hasAvailableUnits($propertyId) {
        $stmt = $this->conn->prepare("
            SELECT available_units, total_units 
            FROM houses 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $propertyId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result && $result['available_units'] > 0;
    }
    
    /**
     * Calculate expected payment amount for a booking
     */
    private function calculateExpectedPaymentAmount($bookingId) {
        // Get booking details
        $stmt = $this->conn->prepare("
            SELECT rb.*, h.price as property_price, h.security_deposit
            FROM rental_bookings rb
            JOIN houses h ON rb.house_id = h.id
            WHERE rb.id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        
        if (!$booking) {
            throw new Exception("Booking not found");
        }
        
        // Check if this is the first payment
        $paymentStmt = $this->conn->prepare("
            SELECT COUNT(*) as payment_count 
            FROM booking_payments 
            WHERE booking_id = ? AND status = 'completed'
        ");
        $paymentStmt->bind_param('i', $bookingId);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result()->fetch_assoc();
        
        if ($paymentResult['payment_count'] == 0) {
            // First payment: Security deposit + first month rent
            return floatval($booking['security_deposit']) + floatval($booking['property_price']);
        } else {
            // Subsequent payment: Monthly rent only
            return floatval($booking['property_price']);
        }
    }
    
    /**
     * Synchronize payment status to prevent inconsistencies
     */
    public function synchronizePaymentStatus($bookingId) {
        $this->conn->begin_transaction();
        
        try {
            // Get booking and payment status
            $bookingStmt = $this->conn->prepare("
                SELECT rb.*, 
                       COUNT(bp.id) as payment_count,
                       SUM(CASE WHEN bp.status = 'completed' THEN 1 ELSE 0 END) as completed_payments
                FROM rental_bookings rb
                LEFT JOIN booking_payments bp ON rb.id = bp.booking_id
                WHERE rb.id = ?
                GROUP BY rb.id
            ");
            $bookingStmt->bind_param('i', $bookingId);
            $bookingStmt->execute();
            $booking = $bookingStmt->get_result()->fetch_assoc();
            
            if (!$booking) {
                throw new Exception("Booking not found");
            }
            
            $hasCompletedPayments = $booking['completed_payments'] > 0;
            $currentStatus = $booking['status'];
            $currentPaymentStatus = $booking['payment_status'] ?? 'pending';
            
            // Determine correct status
            $correctStatus = 'pending';
            $correctPaymentStatus = 'pending';
            
            if ($hasCompletedPayments) {
                $correctStatus = 'confirmed';
                $correctPaymentStatus = 'paid';
            }
            
            // Update if status is inconsistent
            if ($currentStatus !== $correctStatus || $currentPaymentStatus !== $correctPaymentStatus) {
                $updateStmt = $this->conn->prepare("
                    UPDATE rental_bookings 
                    SET status = ?, payment_status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->bind_param('ssi', $correctStatus, $correctPaymentStatus, $bookingId);
                $updateStmt->execute();
                
                error_log("Payment status synchronized for booking $bookingId: status=$correctStatus, payment_status=$correctPaymentStatus");
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Payment status synchronized',
                'old_status' => $currentStatus,
                'new_status' => $correctStatus,
                'old_payment_status' => $currentPaymentStatus,
                'new_payment_status' => $correctPaymentStatus
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create monthly payment records for a booking
     */
    private function createMonthlyPaymentRecords($bookingId) {
        try {
            // Include the monthly payment tracker
            require_once __DIR__ . '/../monthly_payment_tracker.php';
            $tracker = new MonthlyPaymentTracker($this->conn);
            
            // This will automatically create the monthly records
            $payments = $tracker->getMonthlyPayments($bookingId);
            
            error_log("Created " . count($payments) . " monthly payment records for booking $bookingId");
            
            return [
                'success' => true,
                'count' => count($payments)
            ];
            
        } catch (Exception $e) {
            error_log("Failed to create monthly payment records for booking $bookingId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Helper function for currency formatting
if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return 'KSh ' . number_format($amount, 2);
    }
}
