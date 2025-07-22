document.addEventListener('DOMContentLoaded', function() {
    // Initialize date picker for move-in date
    const moveInDate = document.getElementById('moveInDate');
    if (moveInDate) {
        // Set minimum date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        moveInDate.min = tomorrow.toISOString().split('T')[0];
        
        // Set default move-in date to 30 days from now if not already set
        if (!moveInDate.value) {
            const defaultDate = new Date();
            defaultDate.setDate(defaultDate.getDate() + 30);
            moveInDate.value = defaultDate.toISOString().split('T')[0];
        }
    }

    // Initialize tooltips and popovers
    initTooltips();
    initPopovers();
    
    // Initialize Google Maps if function exists
    if (typeof initPropertyMap === 'function') {
        initPropertyMap();
    }
});

// Function to change the main image when clicking on thumbnails
function changeImage(thumbnail, imageUrl) {
    // Update main image
    const mainImage = document.getElementById('mainImage');
    if (mainImage) {
        // Add fade out effect
        mainImage.style.opacity = '0';
        
        // Wait for fade out to complete
        setTimeout(() => {
            mainImage.src = imageUrl;
            mainImage.alt = thumbnail.alt || 'Property Image';
            
            // Fade in the new image
            setTimeout(() => {
                mainImage.style.opacity = '1';
            }, 50);
        }, 200);
    }
    
    // Update active thumbnail
    const thumbnails = document.querySelectorAll('.thumbnail');
    thumbnails.forEach(thumb => {
        thumb.classList.remove('active');
    });
    thumbnail.classList.add('active');
}

// Function to copy the current URL to clipboard
function copyToClipboard() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        showAlert('Link copied to clipboard!', 'success');
    }).catch(err => {
        console.error('Failed to copy: ', err);
        showAlert('Failed to copy link. Please try again.', 'danger');
    });
}

// Function to show alert messages
function showAlert(message, type = 'info') {
    // Remove any existing alerts
    const existingAlert = document.querySelector('.alert-dismissible');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.role = 'alert';
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to body
    document.body.appendChild(alertDiv);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, 5000);
}

// Initialize tooltips
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Initialize popovers
function initPopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// Handle booking form submission
const bookingForm = document.getElementById('bookingForm');
if (bookingForm) {
    bookingForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
        
        const formData = new FormData(this);
        const propertyId = new URLSearchParams(window.location.search).get('id');
        formData.append('property_id', propertyId);

        // Convert FormData to JSON object
        const formDataObject = {};
        formData.forEach((value, key) => {
            formDataObject[key] = value;
        });

        try {
            const response = await fetch('http://127.0.0.1/rental_system_bse/smart_rental/api/book.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formDataObject)
            });

            if (!response.ok) {
                const errorText = await response.text();
                // Try to parse as JSON first
                try {
                    const errorJson = JSON.parse(errorText);
                    throw new Error(errorJson.message || errorText);
                } catch {
                    throw new Error(`API error: ${errorText}`);
                }
            }

            const result = await response.json();

            if (result.success) {
                showAlert('Booking request submitted successfully! Please wait for landlord approval.', 'success');
                // Reset form
                this.reset();
                // Redirect after a short delay
                setTimeout(() => {
                    window.location.href = '/smart_rental/bookings.php';
                }, 2000);
            } else {
                throw new Error(result.message || 'Failed to book property');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert(error.message || 'Failed to book property. Please try again.', 'danger');
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
}
