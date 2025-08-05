<?php
/**
 * Debug STK Push - See exactly what M-Pesa returns
 */

require_once '../config/db.php';
require_once 'mpesa_config.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

echo "<h1>Debug STK Push</h1>";
echo "<h2>Input Data:</h2>";
echo "<pre>" . json_encode($input, JSON_PRETTY_PRINT) . "</pre>";

if (!$input) {
    echo "<p style='color: red;'>❌ Invalid JSON data</p>";
    echo "<p>Raw input: " . htmlspecialchars($raw_input) . "</p>";
    exit;
}

// Validate required fields
$required_fields = ['booking_id', 'phone_number', 'amount'];
$missing_fields = array_diff($required_fields, array_keys($input));

if (!empty($missing_fields)) {
    echo "<p style='color: red;'>❌ Missing required fields: " . implode(', ', $missing_fields) . "</p>";
    exit;
}

$booking_id = intval($input['booking_id']);
$phone_number = $input['phone_number'];
$amount = floatval($input['amount']);

echo "<h2>Processed Data:</h2>";
echo "<ul>";
echo "<li>Booking ID: $booking_id</li>";
echo "<li>Phone Number: $phone_number</li>";
echo "<li>Amount: $amount</li>";
echo "</ul>";

// Validate amount
if ($amount <= 0) {
    echo "<p style='color: red;'>❌ Invalid amount</p>";
    exit;
}

try {
    // Get booking details
    $stmt = $conn->prepare("
        SELECT rb.*, h.house_no, h.price as property_price, u.username as tenant_name
        FROM rental_bookings rb
        JOIN houses h ON rb.house_id = h.id
        JOIN users u ON rb.user_id = u.id
        WHERE rb.id = ? AND rb.user_id = ?
    ");
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        echo "<p style='color: red;'>❌ Booking not found</p>";
        exit;
    }

    echo "<h2>Booking Details:</h2>";
    echo "<pre>" . json_encode($booking, JSON_PRETTY_PRINT) . "</pre>";

    // Check if booking is already paid
    if ($booking['status'] === 'paid') {
        echo "<p style='color: red;'>❌ Booking is already paid</p>";
        exit;
    }

    // Get M-Pesa access token
    echo "<h2>Getting Access Token...</h2>";
    $access_token = getMpesaAccessToken();
    if (!$access_token) {
        echo "<p style='color: red;'>❌ Failed to get M-Pesa access token</p>";
        exit;
    }
    echo "<p style='color: green;'>✅ Access token obtained</p>";

    // Generate M-Pesa password
    $password_data = generateMpesaPassword();
    
    // Format phone number
    $formatted_phone = formatPhoneNumber($phone_number);
    
    // Generate unique reference
    $reference = 'RENTAL_' . $booking_id . '_' . time();
    
    // Prepare STK Push request
    $stk_push_data = [
        'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
        'Password' => $password_data['password'],
        'Timestamp' => $password_data['timestamp'],
        'TransactionType' => MPESA_TRANSACTION_TYPE,
        'Amount' => intval($amount),
        'PartyA' => $formatted_phone,
        'PartyB' => MPESA_PARTYB,
        'PhoneNumber' => $formatted_phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $reference,
        'TransactionDesc' => 'Rental Payment - ' . $booking['house_no']
    ];

    echo "<h2>STK Push Data:</h2>";
    echo "<pre>" . json_encode($stk_push_data, JSON_PRETTY_PRINT) . "</pre>";

    // Make STK Push request
    echo "<h2>Making STK Push Request...</h2>";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, MPESA_STK_PUSH_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_push_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "<h2>M-Pesa Response:</h2>";
    echo "<ul>";
    echo "<li>HTTP Code: $httpCode</li>";
    echo "<li>cURL Error: " . ($curlError ?: 'None') . "</li>";
    echo "</ul>";

    echo "<h3>Raw Response:</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        
        echo "<h3>Parsed Response:</h3>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($result['CheckoutRequestID'])) {
            echo "<p style='color: green;'>✅ STK Push successful!</p>";
            echo "<ul>";
            echo "<li>CheckoutRequestID: " . $result['CheckoutRequestID'] . "</li>";
            echo "<li>MerchantRequestID: " . $result['MerchantRequestID'] . "</li>";
            echo "<li>ResponseCode: " . ($result['ResponseCode'] ?? 'N/A') . "</li>";
            echo "<li>ResponseDescription: " . ($result['ResponseDescription'] ?? 'N/A') . "</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>❌ STK Push failed - No CheckoutRequestID</p>";
            
            if (isset($result['errorCode'])) {
                echo "<p>Error Code: " . $result['errorCode'] . "</p>";
                echo "<p>Error Message: " . getMpesaErrorMessage($result['errorCode']) . "</p>";
            }
            
            if (isset($result['errorMessage'])) {
                echo "<p>Error Message: " . $result['errorMessage'] . "</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ HTTP Error: $httpCode</p>";
        echo "<p>Response: " . htmlspecialchars($response) . "</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style> 