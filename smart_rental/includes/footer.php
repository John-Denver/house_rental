</main>

<footer class="footer bg-dark text-white py-5 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <h5>About Smart Rental</h5>
                <p>Find your perfect home with ease. Smart Rental connects renters with amazing properties across the city.</p>
                <div class="social-links mt-3">
                    <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
            <div class="col-md-4">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="about.php" class="text-white">About Us</a></li>
                    <li><a href="contact.php" class="text-white">Contact</a></li>
                    <li><a href="terms.php" class="text-white">Terms & Conditions</a></li>
                    <li><a href="privacy.php" class="text-white">Privacy Policy</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Contact Us</h5>
                <p><i class="fas fa-map-marker-alt me-2"></i> Nairobi, Kenya</p>
                <p><i class="fas fa-phone me-2"></i> +254 700 000 000</p>
                <p><i class="fas fa-envelope me-2"></i> info@smartrental.com</p>
            </div>
        </div>
        <hr class="mt-4 mb-3">
        <div class="row">
            <div class="col-12 text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Smart Rental. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script>
    // Initialize dropdowns when DOM is fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Function to initialize dropdowns
        function initializeDropdowns() {
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            dropdownElementList.forEach(function(dropdownToggleEl) {
                // Only initialize if not already initialized
                if (!bootstrap.Dropdown.getInstance(dropdownToggleEl)) {
                    new bootstrap.Dropdown(dropdownToggleEl, {
                        autoClose: true
                    });
                }
            });
        }

        // Initialize dropdowns on page load
        initializeDropdowns();
        
        // Re-initialize dropdowns after AJAX content loads (if any)
        document.body.addEventListener('ajaxComplete', initializeDropdowns);
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            var isDropdownToggle = e.target.matches('.dropdown-toggle') || 
                                 e.target.closest('.dropdown-toggle');
            var isInDropdown = e.target.closest('.dropdown-menu');
            
            if (!isDropdownToggle && !isInDropdown) {
                var dropdowns = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
                dropdowns.forEach(function(dropdownToggle) {
                    var dropdown = bootstrap.Dropdown.getInstance(dropdownToggle);
                    if (dropdown && dropdown._menu.classList.contains('show')) {
                        dropdown.hide();
                    }
                });
            }
        });
        
        // Enable tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // Enable popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Add smooth scrolling to all links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add active class to current nav item
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
                link.setAttribute('aria-current', 'page');
            }
        });
    });
</script>

</body>
</html>
