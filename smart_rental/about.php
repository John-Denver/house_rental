<?php
$page_title = "About Us | Smart Rental";
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12 text-center text-white zindex-2">
                <h1 class="display-4 fw-bold mb-4">Your Journey to the Perfect Home Starts Here</h1>
                <p class="lead mb-5">Discover the story behind Smart Rental and our commitment to making your rental experience seamless and enjoyable.</p>
                <a href="#our-story" class="btn btn-primary btn-lg px-4 me-2">Our Story</a>
                <a href="properties.php" class="btn btn-outline-light btn-lg px-4">Browse Properties</a>
            </div>
        </div>
    </div>
</section>

<style>
.hero-section {
    position: relative;
    min-height: 500px;
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

.btn-outline-light:hover {
    color: var(--bs-primary);
    background-color: #fff;
    border-color: #fff;
}
</style>

<!-- Our Story -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 order-lg-2">
                <h2 class="fw-bold mb-4">Our Story</h2>
                <p class="lead text-muted">Founded in 2020, Smart Rental began with a simple mission: to transform the way people find and rent properties.</p>
                <p>What started as a small team of real estate enthusiasts has grown into a trusted platform connecting thousands of tenants with their dream homes. We understand that finding the perfect rental isn't just about four walls - it's about finding a place where life happens.</p>
                <p>Today, we're proud to serve communities across the country, helping people find not just houses, but homes where they can build their futures.</p>
            </div>
            <div class="col-lg-6 order-lg-1">
                <div class="position-relative">
                    <div class="rounded overflow-hidden shadow-lg">
                        <img src="assets/img/our-story.jpg" alt="Our team working together" class="img-fluid">
                    </div>
                    <div class="position-absolute top-0 start-0 w-100 h-100 border border-4 border-white rounded" style="margin: 20px -20px -20px 20px; z-index: -1;"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mission Statement -->
<section class="bg-light py-5">
    <div class="container text-center">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="position-relative mb-4">
                    <span class="quote-mark">"</span>
                    <h2 class="fw-bold mb-4">Our Mission</h2>
                    <p class="lead">To make the rental process simple, transparent, and stress-free for everyone involved - tenants, landlords, and property managers alike.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Core Values -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center fw-bold mb-5">Our Core Values</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm hover-shadow">
                    <div class="card-body text-center p-4">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary rounded-circle mx-auto mb-4" style="width: 80px; height: 80px; line-height: 80px;">
                            <i class="fas fa-shield-alt fa-2x"></i>
                        </div>
                        <h4 class="h5">Trust & Transparency</h4>
                        <p class="text-muted mb-0">We believe in honest communication and transparent processes in all our dealings.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm hover-shadow">
                    <div class="card-body text-center p-4">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary rounded-circle mx-auto mb-4" style="width: 80px; height: 80px; line-height: 80px;">
                            <i class="fas fa-heart fa-2x"></i>
                        </div>
                        <h4 class="h5">Customer-Centric</h4>
                        <p class="text-muted mb-0">Your satisfaction is our top priority. We listen, we care, and we deliver.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm hover-shadow">
                    <div class="card-body text-center p-4">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary rounded-circle mx-auto mb-4" style="width: 80px; height: 80px; line-height: 80px;">
                            <i class="fas fa-lightbulb fa-2x"></i>
                        </div>
                        <h4 class="h5">Innovation</h4>
                        <p class="text-muted mb-0">We continuously improve our platform to make renting smarter and easier.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="bg-light py-5">
    <div class="container">
        <h2 class="text-center fw-bold mb-5">What Our Clients Say</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://randomuser.me/api/portraits/women/32.jpg" alt="Artieno Genevieve" class="rounded-circle me-3" width="60">
                            <div>
                                <h5 class="mb-0">Artieno Genevieve</h5>
                                <small class="text-muted">Tenant, 2 years</small>
                            </div>
                        </div>
                        <p class="mb-0">"Finding my current apartment through Smart Rental was a breeze. The platform is so easy to use, and their customer service team was incredibly helpful throughout the entire process. Highly recommended!"</p>
                        <div class="mt-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://randomuser.me/api/portraits/men/45.jpg" alt="Pittah Kipruto" class="rounded-circle me-3" width="60">
                            <div>
                                <h5 class="mb-0">Pittah Kipruto</h5>
                                <small class="text-muted">Landlord, 3 years</small>
                            </div>
                        </div>
                        <p class="mb-0">"As a property owner, I appreciate how Smart Rental handles everything from tenant screening to rent collection. It's taken so much stress out of property management. The platform is intuitive and their support team is always responsive."</p>
                        <div class="mt-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://randomuser.me/api/portraits/women/68.jpg" alt="Habakuk Tenges" class="rounded-circle me-3" width="60">
                            <div>
                                <h5 class="mb-0">Habakuk Tenges</h5>
                                <small class="text-muted">Tenant, 1 year</small>
                            </div>
                        </div>
                        <p class="mb-0">"I was new to the city and needed to find a place quickly. Smart Rental's advanced search filters helped me find exactly what I was looking for in my budget. The virtual tour feature was a game-changer!"</p>
                        <div class="mt-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="far fa-star text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center fw-bold mb-5">Meet Our Team</h2>
        <div class="row g-4">
            <div class="col-md-3 col-6">
                <div class="text-center">
                    <div class="rounded-circle overflow-hidden mx-auto mb-3" style="width: 150px; height: 150px;">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="David Kim" class="img-fluid">
                    </div>
                    <h5 class="mb-1">David Kim</h5>
                    <p class="text-muted mb-0">CEO & Founder</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="text-center">
                    <div class="rounded-circle overflow-hidden mx-auto mb-3" style="width: 150px; height: 150px;">
                        <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Jennifer Lee" class="img-fluid">
                    </div>
                    <h5 class="mb-1">Jennifer Lee</h5>
                    <p class="text-muted mb-0">Head of Operations</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="text-center">
                    <div class="rounded-circle overflow-hidden mx-auto mb-3" style="width: 150px; height: 150px;">
                        <img src="https://randomuser.me/api/portraits/men/67.jpg" alt="Robert Johnson" class="img-fluid">
                    </div>
                    <h5 class="mb-1">Robert Johnson</h5>
                    <p class="text-muted mb-0">Lead Developer</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="text-center">
                    <div class="rounded-circle overflow-hidden mx-auto mb-3" style="width: 150px; height: 150px;">
                        <img src="https://randomuser.me/api/portraits/women/28.jpg" alt="Maria Garcia" class="img-fluid">
                    </div>
                    <h5 class="mb-1">Maria Garcia</h5>
                    <p class="text-muted mb-0">Customer Success</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="bg-primary text-white py-5">
    <div class="container text-center">
        <h2 class="fw-bold mb-4">Ready to Find Your Dream Rental?</h2>
        <p class="lead mb-4">Join thousands of satisfied tenants and landlords who trust Smart Rental.</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="properties.php" class="btn btn-light btn-lg px-4">Browse Properties</a>
            <a href="contact.php" class="btn btn-outline-light btn-lg px-4">Contact Us</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<style>
.quote-mark {
    font-size: 8rem;
    line-height: 1;
    color: rgba(13, 110, 253, 0.1);
    position: absolute;
    top: -40px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 0;
}
.hover-shadow {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
.icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
