<?php
/**
 * Test Button Navigation
 * Simple test to isolate the button navigation issue
 */

session_start();

echo "<h2>Test Button Navigation</h2>";
echo "<p>This page tests different button implementations to see which one works.</p>";

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 29;
$monthName = "September 2025";

echo "<h3>Testing Different Button Implementations</h3>";

// Test 1: Simple button with onclick
echo "<h4>Test 1: Simple button with onclick</h4>";
echo "<button type='button' class='btn btn-primary' onclick='window.location.href=\"booking_payment.php?id=$bookingId&type=prepayment\"'>";
echo "Test 1: Simple onclick";
echo "</button>";
echo "<br><br>";

// Test 2: Button with console.log
echo "<h4>Test 2: Button with console.log</h4>";
echo "<button type='button' class='btn btn-success' onclick='console.log(\"Test 2 clicked\"); window.location.href=\"booking_payment.php?id=$bookingId&type=prepayment\"'>";
echo "Test 2: With console.log";
echo "</button>";
echo "<br><br>";

// Test 3: Button with function call
echo "<h4>Test 3: Button with function call</h4>";
echo "<button type='button' class='btn btn-warning' onclick='testNavigation()'>";
echo "Test 3: Function call";
echo "</button>";
echo "<br><br>";

// Test 4: Simple anchor tag
echo "<h4>Test 4: Simple anchor tag</h4>";
echo "<a href='booking_payment.php?id=$bookingId&type=prepayment' class='btn btn-info'>";
echo "Test 4: Anchor tag";
echo "</a>";
echo "<br><br>";

// Test 5: Button with preventDefault
echo "<h4>Test 5: Button with preventDefault</h4>";
echo "<button type='button' class='btn btn-dark' onclick='testNavigationWithPrevent()'>";
echo "Test 5: With preventDefault";
echo "</button>";
echo "<br><br>";

// Test 6: Form button (this might be the issue)
echo "<h4>Test 6: Form button (potential issue)</h4>";
echo "<form method='POST' action='#'>";
echo "<button type='button' class='btn btn-secondary' onclick='window.location.href=\"booking_payment.php?id=$bookingId&type=prepayment\"'>";
echo "Test 6: Form button";
echo "</button>";
echo "</form>";
echo "<br><br>";

echo "<h4>Debug Information:</h4>";
echo "<div id='debug-info' style='padding: 10px; border: 1px solid #ccc; background: #f9f9f9;'>";
echo "Click any button above and check the console for errors.<br>";
echo "Expected URL: booking_payment.php?id=$bookingId&type=prepayment<br>";
echo "Current page: " . $_SERVER['PHP_SELF'] . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "</div>";

echo "<h4>Direct Links:</h4>";
echo "<ul>";
echo "<li><a href='booking_payment.php?id=$bookingId&type=prepayment'>Direct Link to Payment Page</a></li>";
echo "<li><a href='booking_details.php?id=$bookingId'>Back to Booking Details</a></li>";
echo "</ul>";
?>

<script>
function testNavigation() {
    console.log('Test 3: Function called');
    console.log('Navigating to: booking_payment.php?id=<?php echo $bookingId; ?>&type=prepayment');
    window.location.href = 'booking_payment.php?id=<?php echo $bookingId; ?>&type=prepayment';
}

function testNavigationWithPrevent() {
    console.log('Test 5: Function with preventDefault called');
    event.preventDefault();
    console.log('Prevented default, now navigating...');
    window.location.href = 'booking_payment.php?id=<?php echo $bookingId; ?>&type=prepayment';
}

// Add error handler
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    document.getElementById('debug-info').innerHTML += '<br><strong style="color: red;">JavaScript Error: ' + e.error + '</strong>';
});

// Log when page loads
console.log('Test page loaded');
console.log('Expected navigation URL: booking_payment.php?id=<?php echo $bookingId; ?>&type=prepayment');
</script> 