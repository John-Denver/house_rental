<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>House Rental Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --gold-primary: #D4AF37;
            --gold-secondary: #B8860B;
            --gold-light: #F7E98E;
            --gold-dark: #9B7A00;
            --dark-bg: #1a1a1a;
            --dark-card: #2d2d2d;
            --text-light: #ffffff;
            --text-muted: #cccccc;
            --shadow: 0 10px 30px rgba(212, 175, 55, 0.1);
            --shadow-hover: 0 20px 40px rgba(212, 175, 55, 0.2);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--dark-bg) 0%, #2c2c2c 100%);
            color: var(--text-light);
            min-height: 100vh;
            padding: 0;
            overflow-x: hidden;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .welcome-section {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 20px;
            background: linear-gradient(135deg, var(--dark-card) 0%, rgba(212, 175, 55, 0.1) 100%);
            border-radius: 20px;
            border: 1px solid rgba(212, 175, 55, 0.2);
            animation: fadeInUp 0.8s ease-out;
        }

        .welcome-title {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 700;
            background: linear-gradient(45deg, var(--gold-primary), var(--gold-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            animation: shimmer 3s ease-in-out infinite alternate;
        }

        .developer-credits {
            font-size: 1.1rem;
            background: linear-gradient(45deg, var(--gold-primary), var(--gold-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 600;
            opacity: 0;
            animation: slideInUp 1s ease-out 0.5s forwards;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .stat-card {
            background: var(--dark-card);
            border-radius: 20px;
            padding: 30px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(212, 175, 55, 0.2);
            box-shadow: var(--shadow);
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            animation: fadeInUp 0.8s ease-out forwards;
            opacity: 0;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.6s; }

        .stat-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-hover);
            border-color: var(--gold-primary);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.1), transparent);
            transition: left 0.8s;
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-icon {
            font-size: 3rem;
            color: var(--gold-primary);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            display: block;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.2) rotate(10deg);
            color: var(--gold-light);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-light);
            margin-bottom: 10px;
            transition: color 0.3s ease;
        }

        .stat-card:hover .stat-number {
            color: var(--gold-primary);
        }

        .stat-label {
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card.primary { border-left: 4px solid #007bff; }
        .stat-card.warning { border-left: 4px solid #ffc107; }
        .stat-card.success { border-left: 4px solid #28a745; }
        .stat-card.danger { border-left: 4px solid #dc3545; }
        .stat-card.info { border-left: 4px solid #17a2b8; }
        .stat-card.secondary { border-left: 4px solid #6c757d; }

        .chart-section {
            background: var(--dark-card);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 40px;
            border: 1px solid rgba(212, 175, 55, 0.2);
            box-shadow: var(--shadow);
            animation: fadeInUp 1s ease-out 0.7s forwards;
            opacity: 0;
        }

        .chart-title {
            font-size: 1.8rem;
            color: var(--gold-primary);
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
        }

        .footer {
            background: var(--dark-card);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            border: 1px solid rgba(212, 175, 55, 0.2);
            margin-top: 50px;
            animation: fadeInUp 1s ease-out 0.9s forwards;
            opacity: 0;
        }

        .footer p {
            color: var(--text-muted);
            margin: 0;
            font-size: 0.95rem;
        }

        .footer a {
            color: var(--gold-primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: var(--gold-light);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 15px;
            }

            .welcome-section {
                padding: 30px 15px;
                margin-bottom: 30px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .stat-card {
                padding: 25px 20px;
            }

            .stat-icon {
                font-size: 2.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .chart-section {
                padding: 25px 20px;
            }
        }

        @media (max-width: 480px) {
            .welcome-section {
                padding: 25px 10px;
            }

            .stat-card {
                padding: 20px 15px;
            }

            .stat-icon {
                font-size: 2rem;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            .chart-section {
                padding: 20px 15px;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -200% center;
            }
            100% {
                background-position: 200% center;
            }
        }

        /* Loading Animation */
        .stat-number {
            position: relative;
        }

        .stat-number::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gold-primary);
            transition: width 0.8s ease 1.2s;
        }

        .stat-card:hover .stat-number::after {
            width: 100%;
        }

        /* Pulse effect for important stats */
        .stat-card.success:hover,
        .stat-card.warning:hover {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(212, 175, 55, 0.4);
            }
            70% {
                box-shadow: 0 0 0 20px rgba(212, 175, 55, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(212, 175, 55, 0);
            }
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--dark-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gold-primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gold-light);
        }
    </style>
</head>
<body>
    <?php include 'db_connect.php' ?>
    <div class="container-fluid">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1 class="welcome-title"><?php echo "Welcome back ". $_SESSION['user_name']."!" ?></h1>
            <div class="developer-credits">
                Developed by John Denver, Tallam Maureen and Thiira Elizabeth
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <i class="fas fa-home stat-icon"></i>
                <div class="stat-number" id="totalHouses"><?php echo $conn->query("SELECT * FROM houses")->num_rows ?></div>
                <div class="stat-label">Total Houses</div>
            </div>

            <div class="stat-card warning">
                <i class="fas fa-user-friends stat-icon"></i>
                <div class="stat-number" id="totalTenants"><?php echo $conn->query("SELECT * FROM tenants WHERE status = 1")->num_rows ?></div>
                <div class="stat-label">Active Tenants</div>
            </div>

            <div class="stat-card success">
                <i class="fas fa-file-invoice-dollar stat-icon"></i>
                <div class="stat-number" id="monthlyPayments">
                    <?php 
                        $payment = $conn->query("SELECT SUM(amount) as paid FROM payments WHERE MONTH(date_created) = MONTH(CURRENT_DATE()) AND YEAR(date_created) = YEAR(CURRENT_DATE())"); 
                        echo $payment->num_rows > 0 ? number_format($payment->fetch_array()['paid'],2) : '0.00';
                    ?>
                </div>
                <div class="stat-label">Payments This Month</div>
            </div>

            <div class="stat-card danger">
                <i class="fas fa-chart-bar stat-icon"></i>
                <div class="stat-number" id="totalReports">
                    <?php 
                        // Assuming you have a reports table, if not using tenants count as placeholder
                        $reports = $conn->query("SELECT * FROM tenants WHERE status = 0"); // inactive tenants as reports
                        echo $reports->num_rows;
                    ?>
                </div>
                <div class="stat-label">Pending Issues</div>
            </div>

            <div class="stat-card info">
                <i class="fas fa-building stat-icon"></i>
                <div class="stat-number" id="houseTypes"><?php echo $conn->query("SELECT * FROM categories")->num_rows ?></div>
                <div class="stat-label">House Types</div>
            </div>

            <div class="stat-card secondary">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-number" id="totalUsers"><?php echo $conn->query("SELECT * FROM users")->num_rows ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>Copyright © 2025 <a href="https://github.com/John-Denver/" target="_blank">House Rental</a> - Design Thiira and Tallam, Backend - Denver</p>
    </footer>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    
    <script>
        // Initialize Feather Icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // Animate numbers on load
        function animateNumber(element, finalNumber) {
            const startNumber = 0;
            const duration = 2000;
            const startTime = Date.now();
            
            function updateNumber() {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const currentNumber = Math.floor(startNumber + (finalNumber - startNumber) * easeOutQuart);
                
                if (element.id === 'monthlyPayments') {
                    element.textContent = '$' + currentNumber.toLocaleString();
                } else {
                    element.textContent = currentNumber;
                }
                
                if (progress < 1) {
                    requestAnimationFrame(updateNumber);
                }
            }
            
            requestAnimationFrame(updateNumber);
        }

        // Initialize number animations with real data
        window.addEventListener('load', () => {
            setTimeout(() => {
                const totalHouses = parseInt(document.getElementById('totalHouses').textContent);
                const totalTenants = parseInt(document.getElementById('totalTenants').textContent);
                const monthlyPayments = parseFloat(document.getElementById('monthlyPayments').textContent.replace(/[,$]/g, ''));
                const totalReports = parseInt(document.getElementById('totalReports').textContent);
                const houseTypes = parseInt(document.getElementById('houseTypes').textContent);
                const totalUsers = parseInt(document.getElementById('totalUsers').textContent);

                document.getElementById('totalHouses').textContent = '0';
                document.getElementById('totalTenants').textContent = '0';
                document.getElementById('monthlyPayments').textContent = '0.00';
                document.getElementById('totalReports').textContent = '0';
                document.getElementById('houseTypes').textContent = '0';
                document.getElementById('totalUsers').textContent = '0';

                animateNumber(document.getElementById('totalHouses'), totalHouses);
                animateNumber(document.getElementById('totalTenants'), totalTenants);
                animateNumberWithCurrency(document.getElementById('monthlyPayments'), monthlyPayments);
                animateNumber(document.getElementById('totalReports'), totalReports);
                animateNumber(document.getElementById('houseTypes'), houseTypes);
                animateNumber(document.getElementById('totalUsers'), totalUsers);
            }, 800);
        });

        function animateNumberWithCurrency(element, finalNumber) {
            const startNumber = 0;
            const duration = 2000;
            const startTime = Date.now();
            
            function updateNumber() {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const currentNumber = startNumber + (finalNumber - startNumber) * easeOutQuart;
                
                element.textContent = currentNumber.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                
                if (progress < 1) {
                    requestAnimationFrame(updateNumber);
                }
            }
            
            requestAnimationFrame(updateNumber);
        }

        document.documentElement.style.scrollBehavior = 'smooth';

        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach(card => observer.observe(card));
        });
    </script>
</body>
</html>