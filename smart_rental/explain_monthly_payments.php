<?php
/**
 * Explain Monthly Rent Payments
 * Shows how the monthly_rent_payments table works with multiple users
 */

session_start();
require_once '../config/db.php';

echo "<h2>How Monthly Rent Payments Work</h2>";

echo "<h3>📊 Table Structure</h3>";
echo "<p>The <code>monthly_rent_payments</code> table has these key columns:</p>";
echo "<ul>";
echo "<li><strong>id</strong> - Unique record ID</li>";
echo "<li><strong>booking_id</strong> - Links to specific booking (THIS IS THE KEY!)</li>";
echo "<li><strong>month</strong> - The month (YYYY-MM-01 format)</li>";
echo "<li><strong>amount</strong> - Rent amount for that month</li>";
echo "<li><strong>status</strong> - paid/unpaid/overdue</li>";
echo "<li><strong>payment_type</strong> - initial_payment/monthly_rent</li>";
echo "<li><strong>is_first_payment</strong> - 1 for first payment, 0 for others</li>";
echo "</ul>";

echo "<h3>🔑 How Multiple Users Work</h3>";
echo "<p><strong>The key is the <code>booking_id</code> column!</strong></p>";

echo "<h4>Example with Multiple Users:</h4>";

// Get sample data to show how it works
$stmt = $conn->prepare("
    SELECT 
        mrp.id,
        mrp.booking_id,
        mrp.month,
        mrp.amount,
        mrp.status,
        mrp.payment_type,
        mrp.is_first_payment,
        rb.user_id,
        u.name as user_name,
        h.title as house_title
    FROM monthly_rent_payments mrp
    JOIN rental_bookings rb ON mrp.booking_id = rb.id
    JOIN users u ON rb.user_id = u.id
    JOIN houses h ON rb.house_id = h.id
    ORDER BY mrp.booking_id, mrp.month
    LIMIT 20
");
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($payments) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Booking ID</th><th>User</th><th>House</th><th>Month</th><th>Amount</th><th>Status</th><th>Type</th><th>First Payment</th>";
    echo "</tr>";
    
    $currentBooking = null;
    foreach ($payments as $payment) {
        $rowColor = ($currentBooking != $payment['booking_id']) ? 'background-color: #e8f4f8;' : '';
        $currentBooking = $payment['booking_id'];
        
        echo "<tr style='$rowColor'>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td><strong>" . $payment['booking_id'] . "</strong></td>";
        echo "<td>" . $payment['user_name'] . "</td>";
        echo "<td>" . $payment['house_title'] . "</td>";
        echo "<td>" . date('F Y', strtotime($payment['month'])) . "</td>";
        echo "<td>KSh " . number_format($payment['amount'], 2) . "</td>";
        echo "<td style='color: " . ($payment['status'] === 'paid' ? 'green' : 'red') . "; font-weight: bold;'>" . $payment['status'] . "</td>";
        echo "<td>" . $payment['payment_type'] . "</td>";
        echo "<td>" . ($payment['is_first_payment'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment records found.</p>";
}

echo "<h3>🎯 How It Works with Multiple Users</h3>";

echo "<h4>1. Each Booking Gets Its Own Records</h4>";
echo "<p>When a user books a property:</p>";
echo "<ul>";
echo "<li>✅ A new <code>rental_bookings</code> record is created</li>";
echo "<li>✅ Multiple <code>monthly_rent_payments</code> records are created for that booking</li>";
echo "<li>✅ Each record has the same <code>booking_id</code> but different <code>month</code></li>";
echo "</ul>";

echo "<h4>2. Data Separation by Booking ID</h4>";
echo "<p>The <code>booking_id</code> ensures that:</p>";
echo "<ul>";
echo "<li>✅ User A's payments don't mix with User B's payments</li>";
echo "<li>✅ Each booking has its own set of monthly records</li>";
echo "<li>✅ Queries can filter by <code>booking_id</code> to get specific user's payments</li>";
echo "</ul>";

echo "<h4>3. Example Queries</h4>";
echo "<p><strong>Get all payments for a specific booking:</strong></p>";
echo "<pre><code>SELECT * FROM monthly_rent_payments WHERE booking_id = 29;</code></pre>";

echo "<p><strong>Get all unpaid months for a booking:</strong></p>";
echo "<pre><code>SELECT * FROM monthly_rent_payments WHERE booking_id = 29 AND status = 'unpaid';</code></pre>";

echo "<p><strong>Get next unpaid month for a booking:</strong></p>";
echo "<pre><code>SELECT month FROM monthly_rent_payments WHERE booking_id = 29 AND status = 'unpaid' ORDER BY month ASC LIMIT 1;</code></pre>";

echo "<h3>🔍 Sample Data Structure</h3>";
echo "<p>Here's how the data looks for multiple users:</p>";

echo "<h4>User A (Booking ID: 29):</h4>";
echo "<ul>";
echo "<li>August 2025: paid (initial payment)</li>";
echo "<li>September 2025: unpaid</li>";
echo "<li>October 2025: unpaid</li>";
echo "<li>... (12 months total)</li>";
echo "</ul>";

echo "<h4>User B (Booking ID: 30):</h4>";
echo "<ul>";
echo "<li>September 2025: paid (initial payment)</li>";
echo "<li>October 2025: unpaid</li>";
echo "<li>November 2025: unpaid</li>";
echo "<li>... (12 months total)</li>";
echo "</ul>";

echo "<h4>User C (Booking ID: 31):</h4>";
echo "<ul>";
echo "<li>October 2025: unpaid</li>";
echo "<li>November 2025: unpaid</li>";
echo "<li>December 2025: unpaid</li>";
echo "<li>... (12 months total)</li>";
echo "</ul>";

echo "<h3>✅ Key Points</h3>";
echo "<ul>";
echo "<li><strong>Multiple users can coexist</strong> - Each booking has its own set of monthly records</li>";
echo "<li><strong>No data mixing</strong> - The <code>booking_id</code> keeps everything separate</li>";
echo "<li><strong>Scalable</strong> - Can handle thousands of users and bookings</li>";
echo "<li><strong>Efficient queries</strong> - Can quickly find specific user's payment status</li>";
echo "</ul>";

echo "<h3>🧪 Test Queries</h3>";
echo "<p>Try these queries to see how it works:</p>";

echo "<h4>1. Show all bookings with their payment counts:</h4>";
$stmt = $conn->prepare("
    SELECT 
        rb.id as booking_id,
        u.name as user_name,
        h.title as house_title,
        COUNT(mrp.id) as total_months,
        SUM(CASE WHEN mrp.status = 'paid' THEN 1 ELSE 0 END) as paid_months,
        SUM(CASE WHEN mrp.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_months
    FROM rental_bookings rb
    JOIN users u ON rb.user_id = u.id
    JOIN houses h ON rb.house_id = h.id
    LEFT JOIN monthly_rent_payments mrp ON rb.id = mrp.booking_id
    GROUP BY rb.id
    ORDER BY rb.id
    LIMIT 10
");
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($bookings) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Booking ID</th><th>User</th><th>House</th><th>Total Months</th><th>Paid</th><th>Unpaid</th>";
    echo "</tr>";
    foreach ($bookings as $booking) {
        echo "<tr>";
        echo "<td>" . $booking['booking_id'] . "</td>";
        echo "<td>" . $booking['user_name'] . "</td>";
        echo "<td>" . $booking['house_title'] . "</td>";
        echo "<td>" . $booking['total_months'] . "</td>";
        echo "<td style='color: green;'>" . $booking['paid_months'] . "</td>";
        echo "<td style='color: red;'>" . $booking['unpaid_months'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h4>2. Show next unpaid month for each booking:</h4>";
$stmt = $conn->prepare("
    SELECT 
        rb.id as booking_id,
        u.name as user_name,
        MIN(mrp.month) as next_unpaid_month
    FROM rental_bookings rb
    JOIN users u ON rb.user_id = u.id
    JOIN monthly_rent_payments mrp ON rb.id = mrp.booking_id
    WHERE mrp.status = 'unpaid'
    GROUP BY rb.id
    ORDER BY rb.id
    LIMIT 10
");
$stmt->execute();
$nextUnpaid = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($nextUnpaid) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Booking ID</th><th>User</th><th>Next Unpaid Month</th>";
    echo "</tr>";
    foreach ($nextUnpaid as $unpaid) {
        echo "<tr>";
        echo "<td>" . $unpaid['booking_id'] . "</td>";
        echo "<td>" . $unpaid['user_name'] . "</td>";
        echo "<td>" . date('F Y', strtotime($unpaid['next_unpaid_month'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>🎉 Summary</h3>";
echo "<p>The <code>monthly_rent_payments</code> table works perfectly with multiple users because:</p>";
echo "<ul>";
echo "<li>✅ <strong>Each booking gets its own set of monthly records</strong></li>";
echo "<li>✅ <strong>The <code>booking_id</code> keeps everything separate</strong></li>";
echo "<li>✅ <strong>Queries can filter by <code>booking_id</code> to get specific user data</strong></li>";
echo "<li>✅ <strong>No data conflicts between different users</strong></li>";
echo "<li>✅ <strong>Scalable to handle thousands of users</strong></li>";
echo "</ul>";

?> 