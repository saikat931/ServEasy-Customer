<?php
session_start();

// Session detection for "Go to Dashboard" links
$is_sa = isset($_SESSION['super_admin']);
$is_admin = isset($_SESSION['standard_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- ServEasy Favicon -->
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link rel="alternate icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>ServEasy Admin Portal – Excellence in Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        :root {
            --emerald: #05c279;
            --emerald-dark: #038a56;
            --emerald-light: #f0fdf8;
            --glass: rgba(255, 255, 255, 0.9);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            scroll-behavior: smooth;
        }

        /* ── Navbar ── */
        .navbar-custom {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--emerald) !important;
            font-size: 1.5rem;
        }
        .nav-link {
            font-weight: 500;
            color: #475569 !important;
            margin: 0 10px;
        }
        .nav-link:hover {
            color: var(--emerald) !important;
        }

        /* ── Hero ── */
        .hero-section {
            padding: 120px 0 80px;
            background: linear-gradient(135deg, #f0fdf8 0%, #ffffff 100%);
            text-align: center;
        }
        .hero-badge {
            background: var(--emerald-light);
            color: var(--emerald);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
        }
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 25px;
            background: linear-gradient(to right, #0f172a, var(--emerald));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 700px;
            margin: 0 auto 40px;
            line-height: 1.7;
        }

        /* ── About Section ── */
        .section-padding {
            padding: 100px 0;
        }
        .section-title {
            font-weight: 700;
            margin-bottom: 50px;
            position: relative;
            display: inline-block;
        }
        .section-title:after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 4px;
            background: var(--emerald);
            border-radius: 2px;
        }

        /* ── Employee List ── */
        .emp-card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            text-align: center;
            padding: 30px;
            margin-bottom: 30px;
        }
        .emp-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(5, 194, 121, 0.1);
        }
        .emp-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 4px solid var(--emerald-light);
        }
        .emp-name {
            font-weight: 700;
            margin-bottom: 5px;
            color: #0f172a;
        }
        .emp-role {
            color: var(--emerald);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ── Project Details ── */
        .project-card {
            border: none;
            border-radius: 24px;
            background: #fff;
            padding: 40px;
            height: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }
        .project-card:hover {
            background: var(--emerald);
            color: #fff !important;
        }
        .project-card:hover .icon-box {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }
        .project-card:hover h4, .project-card:hover p {
            color: #fff;
        }
        .icon-box {
            width: 60px;
            height: 60px;
            background: var(--emerald-light);
            color: var(--emerald);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        /* ── Buttons ── */
        .btn-emerald {
            background: var(--emerald);
            color: #fff;
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-emerald:hover {
            background: var(--emerald-dark);
            color: #fff;
            transform: scale(1.05);
        }
        .btn-outline-emerald {
            border: 2px solid var(--emerald);
            color: var(--emerald);
            border-radius: 12px;
            padding: 10px 28px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-outline-emerald:hover {
            background: var(--emerald);
            color: #fff;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <?php
                $logoPath = __DIR__ . '/img/logo.png';
                if (file_exists($logoPath)) {
                    echo '<img src="img/logo.png?t=' . time() . '" alt="ServEasy Logo" style="max-height: 40px; width: auto; max-width: 100%;">';
                } else {
                    echo '<i class="fas fa-leaf mr-2"></i>ServEasy';
                }
                ?>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#employees">Team</a></li>
                    <li class="nav-item"><a class="nav-link" href="#projects">Projects</a></li>
                    <?php if ($is_sa): ?>
                        <li class="nav-item"><a class="btn btn-emerald ml-3" href="superadmin-dashboard.php">Super Dashboard</a></li>
                    <?php elseif ($is_admin): ?>
                        <li class="nav-item"><a class="btn btn-emerald ml-3" href="../frontend-admin/admin-dashboard.php">Admin Dashboard</a></li>
                    <?php else: ?>
                        <?php if (file_exists(__DIR__ . '/../frontend-admin/admin-login.php')): ?>
                            <li class="nav-item"><a class="nav-link" href="../frontend-admin/admin-login.php">Admin Login</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="btn btn-emerald ml-3" href="superadmin-login.php">Super Admin Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section">
        <div class="container">
            <span class="hero-badge">ServEasy Management Ecosystem</span>
            <h1 class="hero-title">Powerful Administration <br> Simplified for Excellence</h1>
            <p class="hero-desc">Experience the future of organizational management with our unified dashboard system. Secure, scalable, and designed for high-performance teams.</p>
            <div class="d-flex justify-content-center" style="gap: 15px;">
                <a href="#about" class="btn btn-emerald">Learn More</a>
                <a href="<?= file_exists(__DIR__ . '/../frontend-admin/admin-login.php') ? '../frontend-admin/admin-login.php' : 'superadmin-login.php' ?>" class="btn btn-outline-emerald">Get Started</a>
            </div>
        </div>
    </header>

    <!-- About Section -->
    <section id="about" class="section-padding">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="About" class="img-fluid rounded-30 shadow-lg" style="border-radius: 30px;">
                </div>
                <div class="col-lg-6 pl-lg-5 text-center text-lg-left">
                    <h2 class="section-title">About Our Mission</h2>
                    <p class="text-muted mb-4 lead">At ServEasy, we believe that data should empower, not overwhelm. Our admin portal is built to provide crystal-clear insights into your operations, from service provider management to user operations.</p>
                    <p class="text-muted mb-4">We leverage cutting-edge technology to ensure your data is secure and your workflows are optimized. Whether you're a Super Admin overseeing the entire ecosystem or an Administrator managing specific modules, ServEasy provides the tools you need to succeed.</p>
                    <div class="row mt-4">
                        <div class="col-6">
                            <h3 class="font-weight-bold text-emerald">100%</h3>
                            <p class="small text-muted font-weight-bold">Secure Infrastructure</p>
                        </div>
                        <div class="col-6">
                            <h3 class="font-weight-bold text-emerald">24/7</h3>
                            <p class="small text-muted font-weight-bold">System Reliability</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Employee List Section -->
    <section id="employees" class="section-padding bg-light">
        <div class="container text-center">
            <h2 class="section-title">Our Key Team Members</h2>
            <p class="text-muted mb-5">Meet the visionaries behind the ServEasy Admin Ecosystem.</p>
            <div class="row">
                <!-- Employee 1 -->
                <div class="col-md-4">
                    <div class="emp-card">
                        <img src="https://i.pravatar.cc/150?u=1" alt="John" class="emp-img">
                        <h4 class="emp-name">Alex Rivera</h4>
                        <span class="emp-role">Chief Architect</span>
                        <p class="text-muted mt-3 small">Leading the technical vision and infrastructure design of the ServEasy ecosystem.</p>
                        <div class="mt-3">
                            <a href="#" class="text-emerald mx-2"><i class="fab fa-linkedin"></i></a>
                            <a href="#" class="text-emerald mx-2"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
                <!-- Employee 2 -->
                <div class="col-md-4">
                    <div class="emp-card">
                        <img src="https://i.pravatar.cc/150?u=2" alt="Sarah" class="emp-img">
                        <h4 class="emp-name">Sarah Chen</h4>
                        <span class="emp-role">Operations Director</span>
                        <p class="text-muted mt-3 small">Optimizing workflows and ensuring seamless coordination across all modules.</p>
                        <div class="mt-3">
                            <a href="#" class="text-emerald mx-2"><i class="fab fa-linkedin"></i></a>
                            <a href="#" class="text-emerald mx-2"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
                <!-- Employee 3 -->
                <div class="col-md-4">
                    <div class="emp-card">
                        <img src="https://i.pravatar.cc/150?u=3" alt="Michael" class="emp-img">
                        <h4 class="emp-name">Marcus Thorne</h4>
                        <span class="emp-role">Lead Developer</span>
                        <p class="text-muted mt-3 small">Passionate about building responsive, secure, and user-centric web applications.</p>
                        <div class="mt-3">
                            <a href="#" class="text-emerald mx-2"><i class="fab fa-linkedin"></i></a>
                            <a href="#" class="text-emerald mx-2"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Project Details Section -->
    <section id="projects" class="section-padding">
        <div class="container text-center">
            <h2 class="section-title">System Modules & Projects</h2>
            <p class="text-muted mb-5">Explore the core capabilities of the ServEasy Admin portal.</p>
            <div class="row text-left">
                <!-- Module 1 -->
                <div class="col-lg-4 mb-4">
                    <div class="project-card">
                        <div class="icon-box"><i class="fas fa-user-shield"></i></div>
                        <h4 class="font-weight-bold">Auth Armor</h4>
                        <p class="text-muted">Multi-layered authentication system featuring role-based access control and security question verification.</p>
                        <ul class="list-unstyled small mt-3">
                            <li><i class="fas fa-check text-emerald mr-2"></i> Two-Step Login</li>
                            <li><i class="fas fa-check text-emerald mr-2"></i> Secure Session Lock</li>
                        </ul>
                    </div>
                </div>
                <!-- Module 2 -->
                <div class="col-lg-4 mb-4">
                    <div class="project-card">
                        <div class="icon-box"><i class="fas fa-chart-line"></i></div>
                        <h4 class="font-weight-bold">Insight Engine</h4>
                        <p class="text-muted">Real-time data processing and visualization for service providers and user engagement metrics.</p>
                        <ul class="list-unstyled small mt-3">
                            <li><i class="fas fa-check text-emerald mr-2"></i> Live Data Feeds</li>
                            <li><i class="fas fa-check text-emerald mr-2"></i> Interactive KPI Cards</li>
                        </ul>
                    </div>
                </div>
                <!-- Module 3 -->
                <div class="col-lg-4 mb-4">
                    <div class="project-card">
                        <div class="icon-box"><i class="fas fa-layer-group"></i></div>
                        <h4 class="font-weight-bold">Module Nexus</h4>
                        <p class="text-muted">A unified interface connecting all administrative branches into a single, cohesive single-page application.</p>
                        <ul class="list-unstyled small mt-3">
                            <li><i class="fas fa-check text-emerald mr-2"></i> AJAX Navigation</li>
                            <li><i class="fas fa-check text-emerald mr-2"></i> Permission Toggling</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-5 bg-white border-top">
        <div class="container text-center">
            <div class="navbar-brand mb-4">
                <a href="index.php" style="text-decoration:none; color:var(--emerald);">
                    <?php
                    $logoPath = __DIR__ . '/img/logo.png';
                    if (file_exists($logoPath)) {
                        echo '<img src="img/logo.png?t=' . time() . '" alt="ServEasy Logo" style="max-height: 40px; width: auto; max-width: 100%;">';
                    } else {
                        echo '<i class="fas fa-leaf mr-2"></i>ServEasy';
                    }
                    ?>
                </a>
            </div>
            <p class="text-muted small">&copy; 2026 ServEasy Management Systems. All rights reserved.</p>
            <div class="mt-4">
                <a href="#" class="text-muted mx-3 small">Privacy Policy</a>
                <a href="#" class="text-muted mx-3 small">Terms of Service</a>
                <a href="#" class="text-muted mx-3 small">Support Center</a>
            </div>
        </div>
    </footer>

    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
