<?php
/**
 * Test M-Pesa Callback
 * Test the callback functionality and simulate a successful payment
 */

// Start output buffering
ob_start();

// Database connection
$host = "localhost";
$dbname = "house_rental";
$username = "root";
$password = "";

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>M-Pesa Callback Test</h2>";
    
    // Test 1: Check if callback file exists
    echo "<h3>✅ Test 1: Callback File Check</h3>";
    if (file_exists('mpesa_callback.php')) {
        echo "<p>✅ mpesa_callback.php exists</p>";
    } else {
        echo "<p>❌ mpesa_callback.php does not exist</p>";
    }
    
    // Test 2: Check recent payment requests
    echo "<h3>✅ Test 2: Recent Payment Requests</h3>";
    $stmt = $conn->prepare("
        SELECT 
            id,
            checkout_request_id,
            booking_id,
            amount,
            status,
            result_code,
            result_desc,
            mpesa_receipt_number,
            created_at,
            updated_at
        FROM mpesa_payment_requests 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $payment_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($payment_requests)) {
        echo "<p>No payment requests found in database.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Checkout Request ID</th>";
        echo "<th>Booking ID</th>";
        echo "<th>Amount</th>";
        echo "<th>Status</th>";
        echo "<th>Result Code</th>";
        echo "<th>Result Desc</th>";
        echo "<th>Receipt Number</th>";
        echo "<th>Created</th>";
        echo "<th>Updated</th>";
        echo "</tr>";
        
        foreach ($payment_requests as $request) {
            echo "<tr>";
            echo "<td>" . $request['id'] . "</td>";
            echo "<td>" . $request['checkout_request_id'] . "</td>";
            echo "<td>" . $request['booking_id'] . "</td>";
            echo "<td>KSh " . number_format($request['amount'], 2) . "</td>";
            echo "<td style='color: " . ($request['status'] === 'completed' ? 'green' : ($request['status'] === 'failed' ? 'red' : 'orange')) . ";'>" . $request['status'] . "</td>";
            echo "<td>" . ($request['result_code'] ?? 'N/A') . "</td>";
            echo "<td>" . ($request['result_desc'] ?? 'N/A') . "</td>";
            echo "<td>" . ($request['mpesa_receipt_number'] ?? 'N/A') . "</td>";
            echo "<td>" . $request['created_at'] . "</td>";
            echo "<td>" . $request['updated_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 3: Simulate a successful callback
    echo "<h3>✅ Test 3: Simulate Successful Callback</h3>";
    
    if (!empty($payment_requests)) {
        $latest_request = $payment_requests[0];
        $checkout_request_id = $latest_request['checkout_request_id'];
        
        echo "<p>Latest payment request: <strong>$checkout_request_id</strong></p>";
        echo "<p>Current status: <strong>" . $latest_request['status'] . "</strong></p>";
        
        // Simulate a successful callback
        $simulated_callback = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'test_merchant_request_id',
                    'CheckoutRequestID' => $checkout_request_id,
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            [
                                'Name' => 'Amount',
                                'Value' => $latest_request['amount']
                            ],
                            [
                                'Name' => 'MpesaReceiptNumber',
                                'Value' => 'TEST' . time()
                            ],
                            [
                                'Name' => 'TransactionDate',
                                'Value' => date('YmdHis')
                            ],
                            [
                                'Name' => 'PhoneNumber',
                                'Value' => '254700000000'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        echo "<p>Simulating successful callback for: <strong>$checkout_request_id</strong></p>";
        
        // Call the callback function
        $_POST = $simulated_callback;
        
        // Include the callback file
        ob_start();
        include 'mpesa_callback.php';
        $callback_output = ob_get_clean();
        
        echo "<p>Callback executed. Output: <pre>" . htmlspecialchars($callback_output) . "</pre></p>";
        
        // Check if the status was updated
        $stmt = $conn->prepare("
            SELECT status, result_code, result_desc, mpesa_receipt_number, updated_at
            FROM mpesa_payment_requests 
            WHERE checkout_request_id = ?
        ");
        $stmt->bind_param('s', $checkout_request_id);
        $stmt->execute();
        $updated_request = $stmt->get_result()->fetch_assoc();
        
        if ($updated_request) {
            echo "<p>Updated status: <strong>" . $updated_request['status'] . "</strong></p>";
            echo "<p>Result code: <strong>" . ($updated_request['result_code'] ?? 'N/A') . "</strong></p>";
            echo "<p>Receipt number: <strong>" . ($updated_request['mpesa_receipt_number'] ?? 'N/A') . "</strong></p>";
        }
        
    } else {
        echo "<p>No payment requests found to test with.</p>";
    }
    
    // Test 4: Check callback URL accessibility
    echo "<h3>✅ Test 4: Callback URL Test</h3>";
    $callback_url = "http://localhost/rental_system_bse/smart_rental/mpesa_callback.php";
    echo "<p>Callback URL: <a href='$callback_url' target='_blank'>$callback_url</a></p>";
    echo "<p>Try accessing the callback URL directly to see if it's accessible.</p>";
    
    // Test 5: Check ngrok status
    echo "<h3>✅ Test 5: Ngrok Status</h3>";
    if (file_exists('ngrok.exe')) {
        echo "<p>✅ ngrok.exe exists</p>";
        
        // Check if ngrok is running
        $ngrok_output = shell_exec('tasklist /FI "IMAGENAME eq ngrok.exe" 2>NUL');
        if (strpos($ngrok_output, 'ngrok.exe') !== false) {
            echo "<p>✅ ngrok is running</p>";
        } else {
            echo "<p>❌ ngrok is not running</p>";
            echo "<p>You may need to start ngrok to receive callbacks from M-Pesa.</p>";
        }
    } else {
        echo "<p>❌ ngrok.exe not found</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 