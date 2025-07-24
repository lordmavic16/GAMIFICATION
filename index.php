<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to the Gamification Learning System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1523240795612-9a054b0db644?ixlib=rb-1.2.1&auto=format&fit=crop&w=1500&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 8rem 0;
            text-align: center;
        }
        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
        }
        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
        }
        .btn-lg {
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
        }
        .feature-icon {
            font-size: 3rem;
            color: #0d6efd;
        }
        .footer {
            background-color: #343a40;
            color: white;
            padding: 2rem 0;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-joystick me-2"></i>Gamified Learning
            </a>
            <div class="ms-auto">
                <a href="user/login.php" class="btn btn-outline-primary me-2">Login</a>
                <a href="user/register.php" class="btn btn-primary">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <div class="container">
            <h1 class="display-4">Engage, Learn, and Achieve</h1>
            <p class="lead">Welcome to a new way of learning. Our platform uses gamification to make education more interactive and fun.</p>
            <a href="user/register.php" class="btn btn-primary btn-lg">Get Started</a>
        </div>
    </header>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <i class="bi bi-book-half feature-icon mb-3"></i>
                            <h3>Interactive Courses</h3>
                            <p>Dive into courses designed to be engaging and hands-on.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <i class="bi bi-trophy feature-icon mb-3"></i>
                            <h3>Earn Achievements</h3>
                            <p>Unlock badges and points as you master new skills.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                     <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <i class="bi bi-bar-chart-line feature-icon mb-3"></i>
                            <h3>Track Your Progress</h3>
                            <p>See how you stack up against others on the leaderboard.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container text-center">
            <p class="mb-1">&copy; 2025 Gamification Learning System. All Rights Reserved.</p>
            <a href="admin/login.php" class="text-white-50">Admin Login</a>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
