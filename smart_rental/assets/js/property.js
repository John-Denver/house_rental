document.addEventListener('DOMContentLoaded', function() {
    // Initialize carousel
    const carousel = new bootstrap.Carousel(document.getElementById('propertyCarousel'), {
        interval: 5000
    });

    // Handle booking form submission
    const bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        bookingForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(bookingForm);
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
                    alert('Booking request submitted successfully! Please wait for landlord approval.');
                    window.location.href = '/smart_rental/bookings.php';
                } else {
                    throw new Error(result.message || 'Failed to book property');
                }
            } catch (error) {
                console.error('Error:', error);
                alert(error.message || 'Failed to book property');
            }
        });
    }
});
