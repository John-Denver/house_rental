<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'house_rental');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application paths
define('APP_ROOT', dirname(dirname(__FILE__)));
define('URL_ROOT', 'http://' . $_SERVER['HTTP_HOST'] . '/rental_system_bse');

// Session configuration - only set if session not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("We're experiencing technical difficulties. Please try again later.");
}

// Include authentication functions
require_once __DIR__ . '/../../config/auth.php';
?>
