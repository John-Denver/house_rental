<?php
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
    
    // Check if phone column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
    
    if ($result->num_rows == 0) {
        // Add phone column if it doesn't exist
        $sql = "ALTER TABLE users 
                ADD COLUMN phone VARCHAR(20) NULL 
                COMMENT 'User\'s contact phone number' 
                AFTER email";
                
        if ($conn->query($sql) === TRUE) {
            echo "Successfully added phone column to users table.\n";
        } else {
            echo "Error adding phone column: " . $conn->error . "\n";
        }
    } else {
        echo "Phone column already exists in users table.\n";
    }
    
    // Show current table structure
    echo "\nCurrent users table structure:\n";
    $result = $conn->query("DESCRIBE users");
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
