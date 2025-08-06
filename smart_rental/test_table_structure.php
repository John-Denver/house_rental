<?php
/**
 * Test Table Structure
 * Check if all required columns exist in the monthly_rent_payments table
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
    echo "Database connection failed: " . $e->getMessage();
    exit;
}

echo "<h2>Monthly Rent Payments Table Structure</h2>";

// Check if table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'monthly_rent_payments'");
if ($tableExists->num_rows === 0) {
    echo "<p style='color: red;'>❌ monthly_rent_payments table does not exist!</p>";
    exit;
}

echo "<p style='color: green;'>✅ monthly_rent_payments table exists</p>";

// Get table structure
$result = $conn->query("DESCRIBE monthly_rent_payments");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[$row['Field']] = $row;
}

echo "<h3>Current Columns:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
echo "</tr>";

foreach ($columns as $columnName => $columnInfo) {
    echo "<tr>";
    echo "<td>$columnName</td>";
    echo "<td>" . $columnInfo['Type'] . "</td>";
    echo "<td>" . $columnInfo['Null'] . "</td>";
    echo "<td>" . $columnInfo['Key'] . "</td>";
    echo "<td>" . $columnInfo['Default'] . "</td>";
    echo "<td>" . $columnInfo['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check for required columns
$requiredColumns = [
    'id' => 'int(11)',
    'booking_id' => 'int(11)',
    'month' => 'date',
    'amount' => 'decimal(15,2)',
    'status' => 'enum',
    'payment_date' => 'datetime',
    'payment_method' => 'varchar(50)',
    'transaction_id' => 'varchar(255)',
    'mpesa_receipt_number' => 'varchar(50)',
    'notes' => 'text',
    'is_first_payment' => 'tinyint(1)',
    'payment_type' => 'varchar(50)',
    'security_deposit_amount' => 'decimal(15,2)',
    'monthly_rent_amount' => 'decimal(15,2)',
    'created_at' => 'timestamp',
    'updated_at' => 'timestamp'
];

echo "<h3>Required Columns Check:</h3>";
$missingColumns = [];
foreach ($requiredColumns as $columnName => $expectedType) {
    if (isset($columns[$columnName])) {
        echo "<p style='color: green;'>✅ $columnName exists</p>";
    } else {
        echo "<p style='color: red;'>❌ $columnName is missing</p>";
        $missingColumns[] = $columnName;
    }
}

if (!empty($missingColumns)) {
    echo "<h3>Missing Columns:</h3>";
    echo "<ul>";
    foreach ($missingColumns as $column) {
        echo "<li>$column</li>";
    }
    echo "</ul>";
    
    echo "<h3>SQL to Add Missing Columns:</h3>";
    echo "<pre>";
    foreach ($missingColumns as $column) {
        switch ($column) {
            case 'is_first_payment':
                echo "ALTER TABLE monthly_rent_payments ADD COLUMN is_first_payment tinyint(1) DEFAULT 0;\n";
                break;
            case 'payment_type':
                echo "ALTER TABLE monthly_rent_payments ADD COLUMN payment_type varchar(50) DEFAULT 'monthly_rent';\n";
                break;
            case 'security_deposit_amount':
                echo "ALTER TABLE monthly_rent_payments ADD COLUMN security_deposit_amount decimal(15,2) DEFAULT 0;\n";
                break;
            case 'monthly_rent_amount':
                echo "ALTER TABLE monthly_rent_payments ADD COLUMN monthly_rent_amount decimal(15,2) DEFAULT 0;\n";
                break;
        }
    }
    echo "</pre>";
    
    echo "<form method='POST'>";
    echo "<button type='submit' name='fix_columns' class='btn btn-warning'>Fix Missing Columns</button>";
    echo "</form>";
    
    if (isset($_POST['fix_columns'])) {
        echo "<h3>Fixing Columns...</h3>";
        foreach ($missingColumns as $column) {
            $sql = "";
            switch ($column) {
                case 'is_first_payment':
                    $sql = "ALTER TABLE monthly_rent_payments ADD COLUMN is_first_payment tinyint(1) DEFAULT 0";
                    break;
                case 'payment_type':
                    $sql = "ALTER TABLE monthly_rent_payments ADD COLUMN payment_type varchar(50) DEFAULT 'monthly_rent'";
                    break;
                case 'security_deposit_amount':
                    $sql = "ALTER TABLE monthly_rent_payments ADD COLUMN security_deposit_amount decimal(15,2) DEFAULT 0";
                    break;
                case 'monthly_rent_amount':
                    $sql = "ALTER TABLE monthly_rent_payments ADD COLUMN monthly_rent_amount decimal(15,2) DEFAULT 0";
                    break;
            }
            
            if ($sql && $conn->query($sql)) {
                echo "<p style='color: green;'>✅ Added column: $column</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to add column: $column - " . $conn->error . "</p>";
            }
        }
        
        echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
    }
} else {
    echo "<p style='color: green;'>✅ All required columns exist!</p>";
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='debug_monthly_payments.php'>Debug Monthly Payments</a></li>";
echo "<li><a href='my_bookings.php'>My Bookings</a></li>";
echo "</ul>";
?> 