<?php
$page_title = "Contact Us | Smart Rental";
include 'includes/header.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (empty($subject)) $errors[] = 'Subject is required';
    if (empty($message)) $errors[] = 'Message is required';
    
    $success = empty($errors);
    if ($success) {
    }
}
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12 text-center text-white">
                <h1 class="display-4 fw-bold mb-4">Get In Touch</h1>
                <p class="lead mb-5">We'd love to hear from you. Reach out to us for any inquiries or feedback.</p>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section class="py-5">
    <div class="container">
        <div class="row g-5">
            <!-- Contact Form -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="fw-bold mb-4">Send us a Message</h2>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php elseif (isset($success) && $success): ?>
                            <div class="alert alert-success">
                                Thank you for your message! We'll get back to you soon.
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="contact.php" class="needs-validation" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Your Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                    <div class="invalid-feedback">
                                        Please enter your name.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    <div class="invalid-feedback">
                                        Please enter a valid email address.
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="subject" name="subject" required
                                           value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                                    <div class="invalid-feedback">
                                        Please enter a subject.
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="message" class="form-label">Your Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="5" required><?php 
                                        echo htmlspecialchars($_POST['message'] ?? ''); 
                                    ?></textarea>
                                    <div class="invalid-feedback">
                                        Please enter your message.
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg px-4">
                                        <i class="fas fa-paper-plane me-2"></i> Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="fw-bold mb-4">Contact Information</h2>
                        <p class="text-muted mb-4">Feel free to reach out to us using any of the following methods. We're here to help!</p>
                        
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background-color: rgba(13, 110, 253, 0.1); border-radius: 50%;">
                                <i class="fas fa-map-marker-alt text-primary" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Our Location</h5>
                                <p class="mb-0 text-muted">Majesty House, Juja, Near Spearhead apartments, Kenya</p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background-color: rgba(13, 110, 253, 0.1); border-radius: 50%;">
                                <i class="fas fa-phone-alt text-primary" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Phone Number</h5>
                                <p class="mb-0 text-muted">
                                    <a href="tel:+254712345678" class="text-decoration-none text-muted">+2547125 12358</a>
                                </p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background-color: rgba(13, 110, 253, 0.1); border-radius: 50%;">
                                <i class="fas fa-envelope text-primary" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Email Address</h5>
                                <p class="mb-0 text-muted">
                                    <a href="mailto:info@smartrental.com" class="text-decoration-none text-muted">info@smartrental.com</a>
                                </p>
                            </div>
                        </div>
                        
                        <div class="d-flex">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background-color: rgba(13, 110, 253, 0.1); border-radius: 50%;">
                                <i class="fas fa-clock text-primary" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Working Hours</h5>
                                <p class="mb-0 text-muted">
                                    Monday - Friday: 8:00 AM - 6:00 PM<br>
                                    Saturday: 9:00 AM - :00 PM<br>
                                    Sunday: Closed
                                </p>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Follow Us</h5>
                        <div class="d-flex gap-3">
                            <a href="#" class="text-primary fs-4"><i class="fab fa-facebook"></i></a>
                            <a href="#" class="text-primary fs-4"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="text-primary fs-4"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="text-primary fs-4"><i class="fab fa-linkedin"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map Section -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="ratio ratio-16x9">
                            <iframe 
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3988.8215099994556!2d36.82162331533092!3d-1.286441535979795!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x182f10d2f8a8c1a9%3A0x8f0f7e7e7e7e7e7e7!2sNairobi%2C%20Kenya!5e0!3m2!1sen!2ske!4v1620000000000!5m2!1sen!2ske" 
                                style="border:0;" 
                                allowfullscreen="" 
                                loading="lazy"
                                class="rounded">
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="bg-light py-5">
    <div class="container text-center">
        <h2 class="fw-bold mb-4">Need Help Finding a Property?</h2>
        <p class="lead text-muted mb-4">Our team is ready to help you find your dream home.</p>
        <a href="properties.php" class="btn btn-primary btn-lg px-4">Browse Properties</a>
    </div>
</section>

<style>
/* Reuse hero section styles from about.php */
.hero-section {
    position: relative;
    min-height: 400px;
    overflow: hidden;
    display: flex;
    align-items: center;
    margin-top: -76px;
    padding-top: 176px;
    z-index: 1;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('assets/images/hero-bg.png') center/cover no-repeat;
    z-index: -2;
}

.hero-section::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: -1;
}

.hero-section .container {
    position: relative;
    z-index: 2;
}

.hero-section h1 {
    color: white;
    font-weight: bold;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.hero-section .lead {
    font-size: 1.5rem;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

/* Contact form styles */
.form-control:focus, .form-select:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.was-validated .form-control:invalid, .was-validated .form-select:invalid {
    border-color: #dc3545;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.was-validated .form-control:valid, .was-validated .form-select:valid {
    border-color: #198754;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

/* Contact info cards */
.bg-primary-soft {
    background-color: rgba(13, 110, 253, 0.1) !important;
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .hero-section {
        min-height: 350px;
    }
    
    .hero-section h1 {
        font-size: 2.2rem;
    }
    
    .hero-section .lead {
        font-size: 1.2rem;
    }
}
</style>

<script>
// Enable form validation
(function () {
    'use strict'
    
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>

<?php include 'includes/footer.php'; ?>
