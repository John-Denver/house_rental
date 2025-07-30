<?php
// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
            $securityDeposit = $property['security_deposit'] ?? ($property['price'] * 2); // 2 months rent as deposit
            
            // Insert booking with monthly rent only
            $stmt = $this->conn->prepare("
                INSERT INTO rental_bookings (
                    house_id, user_id, start_date, 
                    monthly_rent, status, created_at
                ) VALUES (?, ?, ?, ?, 'approved', NOW())
            ");
            
            // Get user details
            $userStmt = $this->conn->prepare("SELECT id FROM users WHERE id = ?");
            $userStmt->bind_param('i', $_SESSION['user_id']);
            $userStmt->execute();
            $user = $userStmt->get_result()->fetch_assoc();
            
            if (!$user) {
                throw new Exception("User not found");
            }
            
            $stmt->bind_param(
                'iisd',
                $data['house_id'],
                $_SESSION['user_id'],
                $data['start_date'],
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
            
            // Create initial payment record for security deposit
            $this->recordPayment($bookingId, $securityDeposit, 'deposit');
            
            $this->conn->commit();
            
            // Send notifications
            $this->sendBookingConfirmation($bookingId);
            
            return [
                'success' => true,
                'booking_id' => $bookingId,
                'message' => 'Booking created successfully!'
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
        $required = ['house_id', 'start_date', 'rental_period'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required");
            }
        }
        
        // Check property availability
        if (!$this->isPropertyAvailable($data['house_id'], $data['start_date'], $data['end_date'] ?? null)) {
            throw new Exception("The property is not available for the selected dates");
        }
    }
    
    private function isPropertyAvailable($propertyId, $startDate, $endDate = null) {
        $endDate = $endDate ?: date('Y-m-d', strtotime($startDate . ' + 1 year'));
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM bookings 
            WHERE property_id = ? 
            AND status NOT IN ('cancelled', 'rejected', 'completed')
            AND (
                (move_in_date BETWEEN ? AND ?) 
                OR (move_out_date BETWEEN ? AND ?)
                OR (? BETWEEN move_in_date AND move_out_date)
            )
        ");
        
        $stmt->bind_param('isssss', $propertyId, $startDate, $endDate, $startDate, $endDate, $startDate);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['count'] == 0;
    }
    
    private function calculateTotalAmount($property, $data) {
        $basePrice = $property['price'] * $data['rental_period'];
        // Add any additional fees here (cleaning, service, etc.)
        return $basePrice;
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
            INSERT INTO payment_history (
                booking_id, amount, payment_method, status
            ) VALUES (?, ?, ?, 'completed')
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
        $message .= "Total Amount: " . format_currency($booking['total_amount']) . "\n\n";
        $message .= "Please login to your account to complete the payment and upload any required documents.\n";
        
        // In production, use a proper email library
        mail($to, $subject, $message);
        
        // Send notification to landlord
        $landlordSubject = "New Booking for " . $booking['property_name'];
        $landlordMessage = "You have a new booking request. Please review it in your dashboard.";
        mail($booking['landlord_email'], $landlordSubject, $landlordMessage);
    }
    
    public function getBookingDetails($bookingId) {
        $stmt = $this->conn->prepare("
            SELECT 
                b.*, 
                h.house_no as property_name,
                h.price as property_price,
                t.name as tenant_name,
                t.email as tenant_email,
                l.name as landlord_name,
                l.email as landlord_phone,
                l.email as landlord_email
            FROM rental_bookings b
            JOIN houses h ON b.house_id = h.id
            JOIN users t ON b.user_id = t.id
            JOIN users l ON b.landlord_id = l.id
            WHERE b.id = ?
        ");
        
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            throw new Exception("Booking not found");
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
                $this->sendBookingConfirmation($bookingId);
                break;
                
            case 'cancelled':
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
}

// Helper function for currency formatting
if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return 'KSh ' . number_format($amount, 2);
    }
}
