<?php
require_once '../config/config.php';
require_once '../controllers/RentPaymentController.php';

// Check if user is logged in and is a landlord
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'landlord') {
    header('Location: ../login.php');
    exit();
}

$landlordId = $_SESSION['user_id'];
$rentPaymentController = new RentPaymentController($conn);

// Get all properties owned by this landlord with payment summary
$stmt = $conn->prepare("
    SELECT h.id, h.name, h.monthly_rent, 
           COUNT(DISTINCT b.id) as total_tenants,
           SUM(CASE WHEN rp.status = 'paid' THEN 1 ELSE 0 END) as paid_tenants
    FROM houses h
    LEFT JOIN bookings b ON h.id = b.property_id AND b.status = 'approved'
    LEFT JOIN rent_payments rp ON b.id = rp.booking_id 
        AND YEAR(rp.due_date) = YEAR(CURRENT_DATE) 
        AND MONTH(rp.due_date) = MONTH(CURRENT_DATE)
    WHERE h.landlord_id = ?
    GROUP BY h.id, h.name, h.monthly_rent
");
$stmt->bind_param('i', $landlordId);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php include '../includes/header.php'; ?>

<div class="container mt-4">
    <h2>Rental Income Overview</h2>
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Expected</h5>
                    <h3>KES <?php 
                        $total = array_sum(array_map(fn($p) => $p['total_tenants'] * $p['monthly_rent'], $properties));
                        echo number_format($total, 2);
                    ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Received</h5>
                    <h3>KES <?php 
                        $received = array_sum(array_map(fn($p) => $p['paid_tenants'] * $p['monthly_rent'], $properties));
                        echo number_format($received, 2);
                    ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Outstanding</h5>
                    <h3>KES <?php 
                        echo number_format($total - $received, 2);
                    ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Properties</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th>Monthly Rent</th>
                            <th>Tenants</th>
                            <th>Paid</th>
                            <th>Pending</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($properties as $property): 
                            $expected = $property['total_tenants'] * $property['monthly_rent'];
                            $received = $property['paid_tenants'] * $property['monthly_rent'];
                            $pending = ($property['total_tenants'] - $property['paid_tenants']) * $property['monthly_rent'];
                            $status = $property['paid_tenants'] == $property['total_tenants'] ? 'Paid' : 'Pending';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($property['name']); ?></td>
                                <td>KES <?php echo number_format($property['monthly_rent'], 2); ?></td>
                                <td><?php echo $property['total_tenants']; ?></td>
                                <td>KES <?php echo number_format($received, 2); ?></td>
                                <td>KES <?php echo number_format($pending, 2); ?></td>
                                <td>KES <?php echo number_format($expected, 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $status === 'Paid' ? 'success' : 'warning'; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
