<?php
/**
 * Test Processing to Completed Transition
 * Simulate the issue where payments stay in processing state
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

echo "<h2>Test Processing to Completed Transition</h2>";

// Get recent processing payments
$query = "SELECT * FROM mpesa_payment_requests WHERE status = 'processing' ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<h3>Recent Processing Payments</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Checkout Request ID</th><th>Status</th><th>Result Code</th><th>Result Desc</th><th>Created</th><th>Updated</th><th>Action</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['checkout_request_id'] . "</td>";
        echo "<td style='font-weight: bold; color: orange;'>" . $row['status'] . "</td>";
        echo "<td>" . $row['result_code'] . "</td>";
        echo "<td>" . $row['result_desc'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>" . $row['updated_at'] . "</td>";
        echo "<td>";
        echo "<button onclick='testPaymentStatus(\"" . $row['checkout_request_id'] . "\")' style='padding: 5px 10px; margin: 2px;'>Test Status</button>";
        echo "<button onclick='forceComplete(\"" . $row['checkout_request_id'] . "\")' style='padding: 5px 10px; margin: 2px; background-color: green; color: white;'>Force Complete</button>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No processing payments found.</p>";
}

// Get recent completed payments
$query = "SELECT * FROM mpesa_payment_requests WHERE status = 'completed' ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<h3>Recent Completed Payments</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Checkout Request ID</th><th>Status</th><th>Result Code</th><th>Result Desc</th><th>Receipt</th><th>Created</th><th>Updated</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['checkout_request_id'] . "</td>";
        echo "<td style='font-weight: bold; color: green;'>" . $row['status'] . "</td>";
        echo "<td>" . $row['result_code'] . "</td>";
        echo "<td>" . $row['result_desc'] . "</td>";
        echo "<td>" . $row['mpesa_receipt_number'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>" . $row['updated_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No completed payments found.</p>";
}

echo "<h3>Test Payment Status Check</h3>";
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
        
        const result = await response.json();
        
        resultsDiv.innerHTML += '<h4>Response:</h4>';
        resultsDiv.innerHTML += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
        
        if (result.success) {
            resultsDiv.innerHTML += '<p style="color: green;">✅ Status check successful</p>';
            resultsDiv.innerHTML += '<p><strong>Status:</strong> ' + result.data.status + '</p>';
            resultsDiv.innerHTML += '<p><strong>Message:</strong> ' + result.data.message + '</p>';
        } else {
            resultsDiv.innerHTML += '<p style="color: red;">❌ Status check failed</p>';
        }
        
    } catch (error) {
        resultsDiv.innerHTML += '<p style="color: red;">❌ Error: ' + error.message + '</p>';
    }
}

async function forceComplete(checkoutId) {
    const resultsDiv = document.getElementById('testResults');
    resultsDiv.innerHTML = '<p>Force completing payment: ' + checkoutId + '</p>';
    
    try {
        // Update the payment status to completed
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
        
        resultsDiv.innerHTML += '<h4>Force Complete Response:</h4>';
        resultsDiv.innerHTML += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
        
        if (result.success) {
            resultsDiv.innerHTML += '<p style="color: green;">✅ Payment force completed</p>';
            // Reload the page to see updated status
            setTimeout(() => location.reload(), 2000);
        } else {
            resultsDiv.innerHTML += '<p style="color: red;">❌ Force complete failed</p>';
        }
        
    } catch (error) {
        resultsDiv.innerHTML += '<p style="color: red;">❌ Error: ' + error.message + '</p>';
    }
}
</script> 