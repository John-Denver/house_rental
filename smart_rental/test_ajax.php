<!DOCTYPE html>
<html>
<head>
    <title>Test AJAX Call</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h2>Test AJAX Call to get_monthly_payments.php</h2>
    
    <div id="result"></div>
    
    <button onclick="testAjax()">Test AJAX Call</button>
    
    <script>
        function testAjax() {
            $('#result').html('Testing...');
            
            $.ajax({
                url: 'get_monthly_payments.php',
                type: 'POST',
                data: { booking_id: 1 }, // Test with booking ID 1
                dataType: 'json',
                success: function(response) {
                    console.log('Success:', response);
                    $('#result').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    $('#result').html(`
                        <div style="color: red;">
                            <h3>Error Details:</h3>
                            <p><strong>Status:</strong> ${status}</p>
                            <p><strong>Error:</strong> ${error}</p>
                            <p><strong>Response:</strong></p>
                            <pre>${xhr.responseText}</pre>
                        </div>
                    `);
                }
            });
        }
    </script>
</body>
</html> 