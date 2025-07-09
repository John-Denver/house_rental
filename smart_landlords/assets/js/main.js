document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    // Handle property booking
    function handleBooking(propertyId) {
        // Show booking modal
        const modal = new bootstrap.Modal(document.getElementById('bookingModal'))
        modal.show()

        // Set property ID in modal form
        document.getElementById('propertyId').value = propertyId
    }

    // Add booking handlers to all booking buttons
    document.querySelectorAll('.btn-book').forEach(button => {
        button.addEventListener('click', function() {
            handleBooking(this.dataset.propertyId)
        })
    })

    // Handle booking form submission
    const bookingForm = document.getElementById('bookingForm')
    if (bookingForm) {
        bookingForm.addEventListener('submit', async function(e) {
            e.preventDefault()
            
            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]')
            const originalText = submitButton.innerHTML
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...'
            submitButton.disabled = true

            try {
                const formData = new FormData(this)
                const response = await fetch('/smart_rental/api/book.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(Object.fromEntries(formData))
                })

                const result = await response.json()

                if (result.success) {
                    alert(`Booking request submitted successfully! Total rent: ${result.total_rent}`)
                    window.location.href = '/smart_rental/bookings.php'
                } else {
                    alert(result.error || 'Failed to book property')
                }
            } catch (error) {
                console.error('Error:', error)
                alert('An error occurred while processing your booking')
            } finally {
                // Reset button
                submitButton.innerHTML = originalText
                submitButton.disabled = false
            }
        })
    }

    // Handle property image carousel
    document.querySelectorAll('.property-carousel').forEach(carousel => {
        new bootstrap.Carousel(carousel, {
            interval: 5000
        })
    })

    // Handle property filters
    const filters = {
        location: '',
        propertyType: '',
        priceRange: ''
    }

    // Update filters
    function updateFilters() {
        const params = new URLSearchParams(window.location.search)
        for (const [key, value] of Object.entries(filters)) {
            if (value) params.set(key, value)
            else params.delete(key)
        }
        window.history.replaceState({}, '', `?${params.toString()}`)
    }

    // Add filter change handlers
    document.querySelectorAll('.filter').forEach(filter => {
        filter.addEventListener('change', function() {
            const [key, value] = this.name.split('_')
            filters[key] = value
            updateFilters()
        })
    })

    // Load initial filters from URL
    const urlParams = new URLSearchParams(window.location.search)
    filters.location = urlParams.get('location') || ''
    filters.propertyType = urlParams.get('propertyType') || ''
    filters.priceRange = urlParams.get('priceRange') || ''

    // Update filter UI
    Object.entries(filters).forEach(([key, value]) => {
        const element = document.querySelector(`[name="${key}_filter"]`)
        if (element) element.value = value
    })
})
