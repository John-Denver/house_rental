<?php
session_start();
require_once '../config/db.php';
require_once 'mpesa_config.php';

echo "<h1>Simple STK Push Test</h1>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
    exit();
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test data
$booking_id = 6; // Use booking ID 6
$phone_number = "254700000000"; // Test phone number
$amount = 1; // Test amount

echo "<h2>Test Data:</h2>";
echo "<ul>";
echo "<li>Booking ID: $booking_id</li>";
echo "<li>Phone Number: $phone_number</li>";
echo "<li>Amount: $amount</li>";
echo "</ul>";

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
        exit();
    }

    echo "<h2>Booking Details:</h2>";
    echo "<pre>" . json_encode($booking, JSON_PRETTY_PRINT) . "</pre>";

    // Get M-Pesa access token
    echo "<h2>Getting Access Token...</h2>";
    $access_token = getMpesaAccessToken();
    if (!$access_token) {
        echo "<p style='color: red;'>❌ Failed to get M-Pesa access token</p>";
        exit();
    }
    echo "<p style='color: green;'>✅ Access token obtained</p>";

    // Generate M-Pesa password
    $password_data = generateMpesaPassword();
    
    // Format phone number
    $formatted_phone = formatPhoneNumber($phone_number);
    
    // Generate unique reference
    $reference = 'TEST_' . $booking_id . '_' . time();
    
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
        'TransactionDesc' => 'Test Payment - ' . $booking['house_no']
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
            
            // Store in database
            $stmt = $conn->prepare("
                INSERT INTO mpesa_payment_requests (
                    booking_id, checkout_request_id, phone_number, amount, 
                    reference, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->bind_param('issds', 
                $booking_id, 
                $result['CheckoutRequestID'], 
                $phone_number, 
                $amount, 
                $reference
            );
            $stmt->execute();
            echo "<p style='color: green;'>✅ Payment request stored in database</p>";
            
        } else {
            echo "<p style='color: red;'>❌ STK Push failed - No CheckoutRequestID</p>";
            
            if (isset($result['errorCode'])) {
                echo "<p>Error Code: " . $result['errorCode'] . "</p>";
                echo "<p>Error Message: " . getMpesaErrorMessage($result['errorCode']) . "</p>";
            }
            
            if (isset($result['errorMessage'])) {
                echo "<p>Error Message: " . $result['errorMessage'] . "</p>";
            }
            
            if (isset($result['requestId'])) {
                echo "<p>Request ID: " . $result['requestId'] . "</p>";
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

echo "<h2>Quick Actions:</h2>";
echo "<p><a href='manual_payment_status.php' class='btn btn-primary'>Manual Payment Status</a></p>";
echo "<p><a href='test_mpesa_status.php' class='btn btn-secondary'>Test Payment Status</a></p>";
echo "<p><a href='booking_payment.php?id=6' class='btn btn-info'>Test Payment Page</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
.btn { display: inline-block; padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; }
.btn-primary { background: #007bff; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-info { background: #17a2b8; color: white; }
</style> 