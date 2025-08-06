<?php
/**
 * Debug Monthly Payments
 * Check why monthly_rent_payments table is empty and "All Paid" badge is showing incorrectly
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once __DIR__ . '/config/config.php';

echo "<h2>Debug Monthly Payments</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in. Please log in first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";

// Test with a booking ID
$testBookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 6;

echo "<h3>Testing with Booking ID: $testBookingId</h3>";

// Check if booking exists
$stmt = $conn->prepare("SELECT * FROM rental_bookings WHERE id = ?");
$stmt->bind_param('i', $testBookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<p style='color: red;'>❌ Booking not found!</p>";
    exit;
}

echo "<h4>Booking Details:</h4>";
echo "<ul>";
echo "<li>ID: " . $booking['id'] . "</li>";
echo "<li>User ID: " . $booking['user_id'] . "</li>";
echo "<li>Start Date: " . $booking['start_date'] . "</li>";
echo "<li>End Date: " . $booking['end_date'] . "</li>";
echo "<li>Monthly Rent: KSh " . number_format($booking['monthly_rent'], 2) . "</li>";
echo "<li>Security Deposit: KSh " . number_format($booking['security_deposit'], 2) . "</li>";
echo "<li>Status: " . $booking['status'] . "</li>";
echo "</ul>";

// Check monthly_rent_payments table
echo "<h4>Monthly Rent Payments Table Status:</h4>";

$result = $conn->query("SELECT COUNT(*) as count FROM monthly_rent_payments");
$totalRecords = $result->fetch_assoc()['count'];
echo "<p>Total records in monthly_rent_payments: $totalRecords</p>";

if ($totalRecords > 0) {
    echo "<h5>Sample Records:</h5>";
    $sampleRecords = $conn->query("SELECT * FROM monthly_rent_payments ORDER BY id DESC LIMIT 5");
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Booking ID</th><th>Month</th><th>Amount</th><th>Status</th><th>Type</th>";
    echo "</tr>";
    while ($row = $sampleRecords->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['booking_id'] . "</td>";
        echo "<td>" . date('F Y', strtotime($row['month'])) . "</td>";
        echo "<td>KSh " . number_format($row['amount'], 2) . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['payment_type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ No records found in monthly_rent_payments table!</p>";
}

// Check records for this specific booking
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM monthly_rent_payments WHERE booking_id = ?");
$stmt->bind_param('i', $testBookingId);
$stmt->execute();
$bookingRecords = $stmt->get_result()->fetch_assoc()['count'];

echo "<h4>Records for Booking $testBookingId:</h4>";
echo "<p>Records found: $bookingRecords</p>";

if ($bookingRecords > 0) {
    $stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? ORDER BY month ASC");
    $stmt->bind_param('i', $testBookingId);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Month</th><th>Amount</th><th>Status</th><th>Type</th><th>Payment Date</th>";
    echo "</tr>";
    foreach ($records as $record) {
        $statusColor = $record['status'] === 'paid' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . date('F Y', strtotime($record['month'])) . "</td>";
        echo "<td>KSh " . number_format($record['amount'], 2) . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $record['status'] . "</td>";
        echo "<td>" . $record['payment_type'] . "</td>";
        echo "<td>" . ($record['payment_date'] ? date('M d, Y', strtotime($record['payment_date'])) : '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test the monthly payment tracker
echo "<h4>Testing Monthly Payment Tracker:</h4>";

try {
    require_once __DIR__ . '/monthly_payment_tracker.php';
    $tracker = new MonthlyPaymentTracker($conn);
    
    // Test getMonthlyPayments
    echo "<h5>1. Testing getMonthlyPayments():</h5>";
    $payments = $tracker->getMonthlyPayments($testBookingId);
    echo "<p>Payments returned: " . count($payments) . "</p>";
    
    // Test getNextPaymentDue
    echo "<h5>2. Testing getNextPaymentDue():</h5>";
    $nextPaymentDue = $tracker->getNextPaymentDue($testBookingId);
    if ($nextPaymentDue) {
        echo "<p>Next payment due: " . date('F Y', strtotime($nextPaymentDue['month'])) . " - KSh " . number_format($nextPaymentDue['amount'], 2) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ No next payment due found (this causes 'All Paid' badge)</p>";
    }
    
    // Test getPaymentSummary
    echo "<h5>3. Testing getPaymentSummary():</h5>";
    $summary = $tracker->getPaymentSummary($testBookingId);
    echo "<ul>";
    echo "<li>Total Months: " . $summary['total_months'] . "</li>";
    echo "<li>Paid Months: " . $summary['paid_months'] . "</li>";
    echo "<li>Unpaid Months: " . $summary['unpaid_months'] . "</li>";
    echo "<li>Total Paid: KSh " . number_format($summary['total_paid'], 2) . "</li>";
    echo "<li>Total Unpaid: KSh " . number_format($summary['total_unpaid'], 2) . "</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test creating monthly records manually
echo "<h4>Test Creating Monthly Records:</h4>";
echo "<form method='POST'>";
echo "<button type='submit' name='create_records'>Create Monthly Records for Booking $testBookingId</button>";
echo "</form>";

if (isset($_POST['create_records'])) {
    try {
        require_once __DIR__ . '/monthly_payment_tracker.php';
        $tracker = new MonthlyPaymentTracker($conn);
        
        // Force create monthly records
        $payments = $tracker->getMonthlyPayments($testBookingId);
        
        echo "<div style='color: green; margin: 10px 0;'>";
        echo "✅ Monthly records created successfully!";
        echo "</div>";
        
        // Show the created records
        echo "<h5>Created Records:</h5>";
        $stmt = $conn->prepare("SELECT * FROM monthly_rent_payments WHERE booking_id = ? ORDER BY month ASC");
        $stmt->bind_param('i', $testBookingId);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Month</th><th>Amount</th><th>Status</th><th>Type</th><th>First Payment</th>";
        echo "</tr>";
        foreach ($records as $record) {
            echo "<tr>";
            echo "<td>" . date('F Y', strtotime($record['month'])) . "</td>";
            echo "<td>KSh " . number_format($record['amount'], 2) . "</td>";
            echo "<td>" . $record['status'] . "</td>";
            echo "<td>" . $record['payment_type'] . "</td>";
            echo "<td>" . ($record['is_first_payment'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<div style='color: red; margin: 10px 0;'>";
        echo "❌ Error creating records: " . $e->getMessage();
        echo "</div>";
    }
}

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='my_bookings.php'>Go to My Bookings</a></li>";
echo "<li><a href='test_payment_allocation.php?booking_id=$testBookingId'>Test Payment Allocation</a></li>";
echo "<li><a href='test_new_system.php?booking_id=$testBookingId'>Test New System</a></li>";
echo "</ul>";
?>

<style>
table {
    margin: 10px 0;
}
th, td {
    padding: 8px;
    text-align: left;
}
</style> 