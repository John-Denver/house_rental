<?php
/**
 * Test Parameter Count
 * Verify the correct parameter count for bind_param
 */

echo "<h2>Parameter Count Test</h2>";

// SQL query with 9 placeholders
$sql = "
    INSERT INTO monthly_rent_payments 
    (booking_id, month, amount, status, payment_type, is_first_payment, 
     security_deposit_amount, monthly_rent_amount, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
";

echo "<h3>SQL Query:</h3>";
echo "<pre>$sql</pre>";

// Count the placeholders
$placeholderCount = substr_count($sql, '?');
echo "<p>Number of placeholders: $placeholderCount</p>";

// Parameters we want to bind
$parameters = [
    'bookingId' => 'integer',
    'month' => 'string', 
    'amount' => 'decimal',
    'paymentStatus' => 'string',
    'paymentType' => 'string',
    'isFirstPaymentValue' => 'integer',
    'securityDepositValue' => 'decimal',
    'monthlyRent' => 'decimal',
    'notes' => 'string'
];

echo "<h3>Parameters:</h3>";
echo "<ul>";
foreach ($parameters as $name => $type) {
    echo "<li>$name: $type</li>";
}
echo "</ul>";

echo "<p>Number of parameters: " . count($parameters) . "</p>";

// Type string for bind_param
$typeString = 'isdsisddss'; // 10 characters
echo "<p>Type string: '$typeString' (length: " . strlen($typeString) . ")</p>";

echo "<h3>Analysis:</h3>";
if (strlen($typeString) === count($parameters)) {
    echo "<p style='color: green;'>✅ Type string length matches parameter count</p>";
} else {
    echo "<p style='color: red;'>❌ Type string length (" . strlen($typeString) . ") does not match parameter count (" . count($parameters) . ")</p>";
    echo "<p>We need to remove " . (strlen($typeString) - count($parameters)) . " character(s) from the type string.</p>";
}

echo "<h3>Corrected Type String:</h3>";
$correctedTypeString = 'isdsisddss'; // Remove one 's' to make it 9 characters
echo "<p>Corrected type string: '$correctedTypeString' (length: " . strlen($correctedTypeString) . ")</p>";

echo "<h3>Usage:</h3>";
echo "<pre>";
echo "\$insertStmt->bind_param('$correctedTypeString', \n";
echo "    \$bookingId,\n";
echo "    \$month,\n";
echo "    \$amount,\n";
echo "    \$paymentStatus,\n";
echo "    \$paymentType,\n";
echo "    \$isFirstPaymentValue,\n";
echo "    \$securityDepositValue,\n";
echo "    \$monthlyRent,\n";
echo "    \$notes\n";
echo ");";
echo "</pre>";
?> 