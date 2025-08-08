<?php
require_once '../config/db.php';

// Read the SQL file
$sql_file = '../Database/maintenance_requests.sql';
$sql_content = file_get_contents($sql_file);

if ($sql_content === false) {
    die("Error: Could not read SQL file: $sql_file");
}

// Execute the SQL
$queries = explode(';', $sql_content);

foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        try {
            if ($conn->query($query)) {
                echo "Success: " . substr($query, 0, 50) . "...\n";
            } else {
                echo "Error: " . $conn->error . "\n";
            }
        } catch (Exception $e) {
            echo "Exception: " . $e->getMessage() . "\n";
        }
    }
}

echo "Maintenance table creation completed!\n";
?> 