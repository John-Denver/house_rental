<?php
/**
 * Debug Callback Issue
 * Check why callback isn't updating the database
 */

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
} catch (Exception $e) {
    echo "<h2>Database Connection: ❌ Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Debug Callback Issue</h2>";

// Get the latest callback log entry
$logFile = __DIR__ . '/logs/mpesa_callback.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    
    // Find the latest callback entry
    $latestCallback = '';
    $inCallback = false;
    
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (strpos($lines[$i], 'Callback received:') !== false) {
            $inCallback = true;
            $latestCallback = $lines[$i] . "\n";
        } elseif ($inCallback && strpos($lines[$i], '----------------------------------------') !== false) {
            break;
        } elseif ($inCallback) {
            $latestCallback = $lines[$i] . "\n" . $latestCallback;
        }
    }
    
    if ($latestCallback) {
        echo "<h3>Latest Callback Data:</h3>";
        echo "<pre>" . htmlspecialchars($latestCallback) . "</pre>";
        
        // Parse the JSON to extract CheckoutRequestID
        $jsonStart = strpos($latestCallback, '{');
        if ($jsonStart !== false) {
            $jsonPart = substr($latestCallback, $jsonStart);
            $callbackData = json_decode($jsonPart, true);
            
            if ($callbackData) {
                $checkoutRequestId = null;
                
                // Extract CheckoutRequestID from nested structure
                if (isset($callbackData['Body']['stkCallback']['CheckoutRequestID'])) {
                    $checkoutRequestId = $callbackData['Body']['stkCallback']['CheckoutRequestID'];
                } elseif (isset($callbackData['CheckoutRequestID'])) {
                    $checkoutRequestId = $callbackData['CheckoutRequestID'];
                }
                
                if ($checkoutRequestId) {
                    echo "<h3>Extracted CheckoutRequestID: $checkoutRequestId</h3>";
                    
                    // Check if this payment request exists in database
                    $stmt = $conn->prepare("SELECT * FROM mpesa_payment_requests WHERE checkout_request_id = ?");
                    $stmt->bind_param('s', $checkoutRequestId);
                    $stmt->execute();
                    $payment = $stmt->get_result()->fetch_assoc();
                    
                    if ($payment) {
                        echo "<h3>Database Payment Record:</h3>";
                        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                        echo "<tr style='background-color: #f0f0f0;'>";
                        echo "<th>Field</th><th>Value</th>";
                        echo "</tr>";
                        
                        foreach ($payment as $field => $value) {
                            echo "<tr>";
                            echo "<td><strong>$field</strong></td>";
                            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                        
                        // Check if the status should be updated
                        $resultCode = null;
                        if (isset($callbackData['Body']['stkCallback']['ResultCode'])) {
                            $resultCode = $callbackData['Body']['stkCallback']['ResultCode'];
                        } elseif (isset($callbackData['ResultCode'])) {
                            $resultCode = $callbackData['ResultCode'];
                        }
                        
                        if ($resultCode !== null) {
                            echo "<h3>Callback ResultCode: $resultCode</h3>";
                            
                            if ($resultCode == 0) {
                                echo "<p style='color: green;'>✅ This should be marked as 'completed'</p>";
                                
                                if ($payment['status'] !== 'completed') {
                                    echo "<p style='color: red;'>❌ Database status is '{$payment['status']}' but should be 'completed'</p>";
                                    
                                    // Try to manually update it
                                    echo "<h3>Manual Update Test:</h3>";
                                    echo "<button onclick='manualUpdate(\"$checkoutRequestId\")' style='padding: 10px 20px; background-color: green; color: white; border: none; cursor: pointer;'>Manually Update to Completed</button>";
                                } else {
                                    echo "<p style='color: green;'>✅ Database status is already 'completed'</p>";
                                }
                            } else {
                                echo "<p>ResultCode $resultCode - status should be determined based on code</p>";
                            }
                        }
                        
                    } else {
                        echo "<p style='color: red;'>❌ Payment request not found in database for CheckoutRequestID: $checkoutRequestId</p>";
                    }
                } else {
                    echo "<p style='color: red;'>❌ Could not extract CheckoutRequestID from callback data</p>";
                }
            }
        }
    }
} else {
    echo "<p>No callback log file found.</p>";
}

// Show recent payment requests
echo "<h3>Recent Payment Requests:</h3>";
$query = "SELECT * FROM mpesa_payment_requests ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Checkout Request ID</th><th>Status</th><th>Result Code</th><th>Result Desc</th><th>Created</th><th>Updated</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $statusColor = $row['status'] === 'completed' ? 'green' : ($row['status'] === 'processing' ? 'orange' : 'red');
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['checkout_request_id'] . "</td>";
        echo "<td style='font-weight: bold; color: $statusColor;'>" . $row['status'] . "</td>";
        echo "<td>" . ($row['result_code'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['result_desc'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>" . $row['updated_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment requests found.</p>";
}

?>

<script>
async function manualUpdate(checkoutId) {
    try {
        const response = await fetch('force_complete_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                checkout_request_id: checkoutId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Payment manually updated to completed!');
            location.reload();
        } else {
            alert('❌ Failed to update payment: ' + result.message);
        }
        
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}
</script> 