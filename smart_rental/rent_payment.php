<?php
// Include config file
require_once __DIR__ . '/config/config.php';

// Include RentPaymentController with error handling
$controllerPath = __DIR__ . '/controllers/RentPaymentController.php';
if (!file_exists($controllerPath)) {
    die('Error: RentPaymentController.php not found at ' . $controllerPath);
}

require_once $controllerPath;

if (!class_exists('RentPaymentController')) {
    die('Error: RentPaymentController class is not defined in ' . $controllerPath);
}

// Initialize RentPaymentController
try {
    $rentPaymentController = new RentPaymentController($conn);
} catch (Exception $e) {
    die('Error initializing RentPaymentController: ' . $e->getMessage());
}

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Debug: Check RentPaymentController
if (!class_exists('RentPaymentController')) {
    $error = 'RentPaymentController class not found. Check the file path.';
}

// Debug: Check database connection
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $error = 'Database connection error: ' . ($conn->connect_error ?? 'Connection not established');
}

// Get user's active booking
$stmt = $conn->prepare("
    SELECT b.*, h.house_no as property_name
    FROM rental_bookings b
    JOIN houses h ON b.house_id = h.id
    WHERE b.user_id = ? AND b.status = 'approved'
    ORDER BY b.id DESC
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    $error = 'No active booking found.';
} else {
    // Get payment history
    $paymentHistory = $rentPaymentController->getPaymentHistory($booking['id']);
    
    // Get current balance
    $outstandingBalance = $rentPaymentController->getOutstandingBalance($booking['id']);
    
    // Process payment form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_rent'])) {
        $amount = (float)$_POST['amount'];
        $paymentMethod = 'mpesa'; // Default to M-Pesa for now
        
        try {
            if ($amount <= 0) {
                throw new Exception('Please enter a valid amount');
            }
            
            if ($amount > $outstandingBalance) {
                throw new Exception('Payment amount cannot exceed outstanding balance');
            }
            
            if ($rentPaymentController->processPayment($booking['id'], $amount, $paymentMethod)) {
                $success = 'Payment processed successfully!';
                // Refresh the page to show updated balance
                header('Location: rent_payment.php');
                exit();
            } else {
                throw new Exception('Failed to process payment');
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Rent Payment</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($booking)): ?>
                        <div class="mb-4">
                            <h5>Property: <?php echo htmlspecialchars($booking['property_name']); ?></h5>
                            <p>Monthly Rent: KES <?php echo number_format($booking['monthly_rent'], 2); ?></p>
                            <div class="alert <?php echo $outstandingBalance > 0 ? 'alert-warning' : 'alert-success'; ?> mb-4">
                                <h5 class="alert-heading">
                                    <?php echo $outstandingBalance > 0 ? 'Outstanding Balance' : 'All Paid Up!'; ?>
                                </h5>
                                <h3 class="mb-0">
                                    KES <?php echo number_format($outstandingBalance, 2); ?>
                                </h3>
                                <?php if ($outstandingBalance > 0): ?>
                                    <p class="mb-0 mt-2">
                                        <small>Includes any unpaid balances from previous months</small>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($outstandingBalance > 0): ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Make a Payment</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" onsubmit="return confirm('Are you sure you want to process this payment?');">
                                            <div class="form-group">
                                                <label for="amount">Amount (KES)</label>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="amount" 
                                                       name="amount" 
                                                       min="1" 
                                                       max="<?php echo $outstandingBalance; ?>"
                                                       step="0.01"
                                                       value="<?php echo $outstandingBalance; ?>"
                                                       required>
                                                <small class="form-text text-muted">
                                                    Maximum amount: KES <?php echo number_format($outstandingBalance, 2); ?>
                                                </small>
                                            </div>
                                            <div class="form-group">
                                                <label>Payment Method</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" id="mpesa" value="mpesa" checked>
                                                    <label class="form-check-label" for="mpesa">
                                                        M-Pesa
                                                    </label>
                                                </div>
                                            </div>
                                            <button type="submit" name="pay_rent" class="btn btn-primary mt-3">
                                                Pay Now
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <h5>Payment History</h5>
                                <?php if (empty($paymentHistory)): ?>
                                    <p>No payment history found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Description</th>
                                                    <th>Due Date</th>
                                                    <th>Amount Due</th>
                                                    <th>Amount Paid</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($paymentHistory as $payment): ?>
                                                    <tr>
                                                        <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                                        <td>
                                                            Rent for <?php echo date('F Y', strtotime($payment['due_date'])); ?>
                                                            <?php if ($payment['balance_forwarded'] > 0): ?>
                                                                <br><small class="text-muted">+ KES <?php echo number_format($payment['balance_forwarded'], 2); ?> from previous</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('M j, Y', strtotime($payment['due_date'])); ?></td>
                                                        <td>KES <?php echo number_format($payment['amount_due'], 2); ?></td>
                                                        <td>KES <?php echo number_format($payment['amount_paid'], 2); ?></td>
                                                        <td>
                                                            <span class="badge 
                                                                <?php 
                                                                    echo $payment['status'] === 'paid' ? 'bg-success' : 
                                                                        ($payment['status'] === 'partial' ? 'bg-warning' : 'bg-danger'); 
                                                                ?>">
                                                                <?php echo ucfirst($payment['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No active booking found. Please contact the landlord if you believe this is an error.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
