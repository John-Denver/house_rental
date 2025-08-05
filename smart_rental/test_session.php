<?php
// Ensure session is started with proper configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

echo "<h1>Session Test</h1>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'not set') . "</p>";
echo "<p><strong>User Name:</strong> " . ($_SESSION['user_name'] ?? 'not set') . "</p>";
echo "<p><strong>User Type:</strong> " . ($_SESSION['user_type'] ?? 'not set') . "</p>";
echo "<p><strong>All Session Data:</strong></p>";
echo "<pre>" . json_encode($_SESSION, JSON_PRETTY_PRINT) . "</pre>";

echo "<h2>Test Links</h2>";
echo "<p><a href='booking_details.php?id=6&debug_session=1'>Test Booking Details Session</a></p>";
echo "<p><a href='booking_payment.php?id=6&debug=1'>Test Payment Page Session</a></p>";
echo "<p><a href='login.php'>Go to Login</a></p>";

echo "<h2>Session Configuration</h2>";
echo "<p><strong>session.cookie_httponly:</strong> " . ini_get('session.cookie_httponly') . "</p>";
echo "<p><strong>session.use_only_cookies:</strong> " . ini_get('session.use_only_cookies') . "</p>";
echo "<p><strong>session.cookie_path:</strong> " . ini_get('session.cookie_path') . "</p>";
echo "<p><strong>session.cookie_domain:</strong> " . ini_get('session.cookie_domain') . "</p>";
echo "<p><strong>session.cookie_secure:</strong> " . ini_get('session.cookie_secure') . "</p>";

echo "<h2>Cookies</h2>";
echo "<pre>" . print_r($_COOKIE, true) . "</pre>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style> 