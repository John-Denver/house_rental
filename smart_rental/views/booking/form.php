<?php
// Check if property ID is provided
if (!isset($property) || !$property) {
    echo '<div class="alert alert-danger">Property not found.</div>';
    return;
}

// Calculate default dates
$today = date('Y-m-d');
$defaultEndDate = date('Y-m-d', strtotime('+1 year'));
?>

<div class="booking-form-container">
    <h3 class="mb-4">Book This Property</h3>
    
    <form id="bookingForm" action="process_booking.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="house_id" value="<?php echo $property['id']; ?>">
        
        <div class="mb-3">
            <label for="start_date" class="form-label">Move-in Date</label>
            <input type="date" 
                   class="form-control" 
                   id="start_date" 
                   name="start_date" 
                   min="<?php echo $today; ?>" 
                   value="<?php echo $today; ?>" 
                   required>
            <div class="form-text">Earliest available date: <?php echo date('M d, Y'); ?></div>
            <input type="hidden" id="rental_period" name="rental_period" value="12">
        </div>
        
        <div class="mb-3">
            <label for="end_date" class="form-label">Lease End Date</label>
            <input type="text" 
                   class="form-control" 
                   id="end_date" 
                   value="<?php echo date('M d, Y', strtotime($defaultEndDate)); ?>" 
                   readonly>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Pricing Summary</label>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Monthly Rent:</span>
                        <span id="monthlyRent"><?php echo 'KSh ' . number_format($property['price'], 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Rental Period:</span>
                        <span><span id="periodDisplay">12</span> months</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span id="subtotal"><?php echo 'KSh ' . number_format($property['price'] * 12, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Security Deposit (2 months):</span>
                        <span id="deposit"><?php echo 'KSh ' . number_format($property['price'] * 2, 2); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total Due Now:</span>
                        <span id="totalDue"><?php echo 'KSh ' . number_format($property['price'] * 14, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="special_requests" class="form-label">Special Requests (Optional)</label>
            <textarea class="form-control" id="special_requests" name="special_requests" rows="3" 
                      placeholder="Any special requirements or questions?"></textarea>
        </div>
        
        <div class="mb-4">
            <label class="form-label">Required Documents</label>
            <p class="text-muted small mb-2">Please upload the following documents (PDF, JPG, or PNG):</p>
            
            <div class="document-upload mb-2">
                <label class="form-label">ID/Passport Copy</label>
                <input type="file" class="form-control" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            
            <div class="document-upload mb-2">
                <label class="form-label">Proof of Income (Payslips/Bank Statements)</label>
                <input type="file" class="form-control" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            
            <div class="document-upload">
                <label class="form-label">Previous Landlord Reference (If any)</label>
                <input type="file" class="form-control" name="documents[]" accept=".pdf,.jpg,.jpeg,.png">
            </div>
        </div>
        
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="terms" required>
            <label class="form-check-label" for="terms">
                I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a> and 
                <a href="privacy.php" target="_blank">Privacy Policy</a>
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary w-100 py-3">
            <i class="fas fa-calendar-check me-2"></i>Book Now
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const periodDisplay = document.getElementById('periodDisplay');
    const monthlyRent = parseFloat(<?php echo $property['price']; ?>);
    const subtotalElement = document.getElementById('subtotal');
    const depositElement = document.getElementById('deposit');
    const totalDueElement = document.getElementById('totalDue');
    const rentalPeriod = 12; // Default to 12 months
    
    // Update end date when start date changes
    function updateDates() {
        const startDate = new Date(startDateInput.value);
        
        if (isNaN(startDate.getTime())) return;
        
        // Calculate end date
        const endDate = new Date(startDate);
        endDate.setMonth(endDate.getMonth() + rentalPeriod);
        
        // Format and display end date
        endDateInput.value = endDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
        
        // Update pricing
        updatePricing(rentalPeriod);
    }
    
    // Update pricing based on rental period
    function updatePricing(months) {
        periodDisplay.textContent = months;
        const subtotal = monthlyRent * months;
        const deposit = monthlyRent * 2; // 2 months deposit
        const totalDue = subtotal + deposit;
        
        subtotalElement.textContent = 'KSh ' + subtotal.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        depositElement.textContent = 'KSh ' + deposit.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        totalDueElement.textContent = 'KSh ' + totalDue.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Add event listener for start date changes
    startDateInput.addEventListener('change', updateDates);
    
    // Initialize
    updateDates();
});
</script>
