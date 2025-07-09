document.addEventListener('DOMContentLoaded', function() {
    // Initialize filters
    const filterLocation = document.getElementById('filterLocation');
    const minPrice = document.getElementById('minPrice');
    const maxPrice = document.getElementById('maxPrice');
    const filterPropertyType = document.getElementById('filterPropertyType');

    // Apply filters function
    function applyFilters() {
        const location = filterLocation.value;
        const min = minPrice.value;
        const max = maxPrice.value;
        const type = filterPropertyType.value;

        // Update URL with filter parameters
        const params = new URLSearchParams(window.location.search);
        if (location) params.set('location', location);
        if (min) params.set('minPrice', min);
        if (max) params.set('maxPrice', max);
        if (type) params.set('propertyType', type);

        window.location.search = params.toString();
    }

    // Add event listeners to filter inputs
    filterLocation.addEventListener('change', applyFilters);
    minPrice.addEventListener('change', applyFilters);
    maxPrice.addEventListener('change', applyFilters);
    filterPropertyType.addEventListener('change', applyFilters);

    // Load properties with filters
    function loadProperties() {
        const params = new URLSearchParams(window.location.search);
        const location = params.get('location') || '';
        const minPrice = params.get('minPrice') || '';
        const maxPrice = params.get('maxPrice') || '';
        const propertyType = params.get('propertyType') || '';

        // Update filter values from URL
        if (location) filterLocation.value = location;
        if (minPrice) minPrice.value = minPrice;
        if (maxPrice) maxPrice.value = maxPrice;
        if (propertyType) filterPropertyType.value = propertyType;
    }

    // Load properties when page loads
    loadProperties();
});
