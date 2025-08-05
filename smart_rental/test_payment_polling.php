<?php
/**
 * Test Payment Polling
 * Debug the payment polling issue
 */

session_start();

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

echo "<h2>Test Payment Polling</h2>";

// Check session
echo "<h3>Session Check:</h3>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";

// Get recent payment requests
echo "<h3>Recent Payment Requests:</h3>";
$query = "SELECT * FROM mpesa_payment_requests ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Checkout Request ID</th><th>Status</th><th>Result Code</th><th>Result Desc</th><th>Created</th><th>Updated</th><th>Test</th>";
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
        echo "<td>";
        echo "<button onclick='testPaymentStatus(\"" . $row['checkout_request_id'] . "\")' style='padding: 5px 10px; margin: 2px;'>Test Status</button>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment requests found.</p>";
}

echo "<h3>Test Results:</h3>";
echo "<div id='testResults'></div>";

?>

<script>
async function testPaymentStatus(checkoutId) {
    const resultsDiv = document.getElementById('testResults');
    resultsDiv.innerHTML = '<p>Testing payment status for: ' + checkoutId + '</p>';
    
    try {
        const response = await fetch('mpesa_payment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                checkout_request_id: checkoutId
            })
        });
        
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            resultsDiv.innerHTML += '<p style="color: red;">❌ Failed to parse JSON: ' + parseError.message + '</p>';
            resultsDiv.innerHTML += '<pre>' + responseText + '</pre>';
            return;
        }
        
        resultsDiv.innerHTML += '<h4>Response:</h4>';
        resultsDiv.innerHTML += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
        
        if (result.success) {
            resultsDiv.innerHTML += '<p style="color: green;">✅ Status check successful</p>';
            resultsDiv.innerHTML += '<p><strong>Status:</strong> ' + result.data.status + '</p>';
            resultsDiv.innerHTML += '<p><strong>Message:</strong> ' + result.data.message + '</p>';
            
            if (result.data.status === 'completed') {
                resultsDiv.innerHTML += '<p style="color: green; font-weight: bold;">🎉 PAYMENT COMPLETED!</p>';
            }
        } else {
            resultsDiv.innerHTML += '<p style="color: red;">❌ Status check failed: ' + result.message + '</p>';
        }
        
    } catch (error) {
        resultsDiv.innerHTML += '<p style="color: red;">❌ Error: ' + error.message + '</p>';
    }
}

// Auto-test the first payment if available
document.addEventListener('DOMContentLoaded', function() {
    const firstButton = document.querySelector('button[onclick^="testPaymentStatus"]');
    if (firstButton) {
        const checkoutId = firstButton.getAttribute('onclick').match(/"([^"]+)"/)[1];
        setTimeout(() => testPaymentStatus(checkoutId), 1000);
    }
});
</script> 