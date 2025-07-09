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

            try {
                const response = await fetch('/smart_rental/api/book.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(Object.fromEntries(formData))
                });

                const result = await response.json();

                if (result.success) {
                    alert(`Booking request submitted successfully! Total rent: ${result.total_rent}`);
                    window.location.href = '/smart_rental/bookings.php';
                } else {
                    alert(result.error || 'Failed to book property');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while processing your booking');
            }
        });
    }
});
