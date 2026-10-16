<?php
// Start session
session_start();

// Redirect if not coming from registration
if (!isset($_SESSION['registration_success'])) {
    header('Location: index.php');
    exit();
}

// Store the success message and clear the session flag
$status = $_SESSION['registration_success']['status'] ?? 'pending';
$message = $_SESSION['registration_success']['message'] ?? 'Thank you for registering! Your account is pending approval.';
unset($_SESSION['registration_success']);

// Set appropriate title, icon, and icon color based on status
switch ($status) {
    case 'updated':
        $title = 'Account Updated Successfully';
        $icon = 'fa-check-circle';
        $iconColor = '#17a2b8'; // Info color for updates
        break;
    case 'approved':
        $title = 'Registration Successful';
        $icon = 'fa-check-circle';
        $iconColor = '#28a745'; // Success green for approved
        break;
    case 'pending':
    default:
        $title = 'Registration Submitted';
        $icon = 'fa-hourglass-half';
        $iconColor = '#ffc107'; // Warning yellow for pending
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($title); ?> | UNREConnect</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --unre-green: #004d25;
            --unre-light-green: #e9f5ec;
            --unre-gold: #ffd700;
            --unre-dark: #212529;
            --unre-light: #f8f9fa;
            --unre-gray: #6c757d;
            --unre-border: #dee2e6;
            --unre-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            --unre-transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f5f5;
            color: var(--unre-dark);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .success-container {
            max-width: 800px;
            margin: 4rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: var(--unre-shadow);
            overflow: hidden;
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .success-icon {
            font-size: 5rem;
            color: <?php echo $iconColor; ?>;
            margin-bottom: 1.5rem;
            animation: bounce 1s ease infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        h1 {
            color: var(--unre-green);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .lead {
            font-size: 1.25rem;
            color: var(--unre-gray);
            margin-bottom: 2rem;
        }
        
        .btn-primary {
            background-color: var(--unre-green);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--unre-transition);
            margin: 0.5rem;
        }
        
        .btn-primary:hover {
            background-color: #003d1e;
            transform: translateY(-2px);
        }
        
        .btn-outline-secondary {
            border-color: var(--unre-green);
            color: var(--unre-green);
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--unre-transition);
            margin: 0.5rem;
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--unre-light-green);
            color: var(--unre-green);
        }
        
        .whats-next {
            background-color: var(--unre-light-green);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: left;
        }
        
        .whats-next h3 {
            color: var(--unre-green);
            font-size: 1.25rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        
        .whats-next h3 i {
            margin-right: 0.75rem;
            color: var(--unre-green);
        }
        
        .steps {
            list-style: none;
            padding-left: 0;
        }
        
        .steps li {
            position: relative;
            padding-left: 2.5rem;
            margin-bottom: 1.5rem;
        }
        
        .steps li:last-child {
            margin-bottom: 0;
        }
        
        .steps li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background-color: var(--unre-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .steps li:nth-child(1)::before { content: '1'; }
        .steps li:nth-child(2)::before { content: '2'; }
        .steps li:nth-child(3)::before { content: '3'; }
        
        @media (max-width: 768px) {
            .success-container {
                margin: 2rem 1rem;
                padding: 2rem 1rem;
            }
            
            .success-icon {
                font-size: 4rem;
            }
            
            h1 {
                font-size: 1.75rem;
            }
            
            .lead {
                font-size: 1.1rem;
            }
            
            .btn {
                width: 100%;
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--unre-green);">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="/unreconnect/admin/assets/img/logo.png" alt="UNREConnect Logo" height="40" class="me-2">
                <span>UNREConnect</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="alumni_login.php"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- Success Message -->
    <div class="container py-5 flex-grow-1">
        <div class="success-container fade-in">
            <div class="success-icon">
                <i class="fas <?php echo $icon; ?>"></i>
            </div>
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p class="lead"><?php echo htmlspecialchars($message); ?></p>
            
            <div class="d-flex flex-column flex-md-row justify-content-center">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i> Return to Homepage
                </a>
                <?php if ($status === 'updated' || $status === 'approved') { ?>
                    <a href="alumni_login.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sign-in-alt me-2"></i> Go to Login
                    </a>
                <?php } ?>
            </div>
            
            <div class="whats-next mt-5">
                <h3><i class="fas fa-info-circle"></i> What's Next?</h3>
                <ul class="steps">
                    <?php if ($status === 'pending') { ?>
                        <li>
                            <h5>Account Verification</h5>
                            <p>Our team will review your registration. You'll receive an email once your account is approved.</p>
                        </li>
                        <li>
                            <h5>Log In</h5>
                            <p>Once approved, log in to access your account and complete your profile.</p>
                        </li>
                        <li>
                            <h5>Start Networking</h5>
                            <p>Explore job opportunities, events, and connect with fellow UNRE alumni.</p>
                        </li>
                    <?php } else { ?>
                        <li>
                            <h5>Log In</h5>
                            <p>Use your credentials to log in and access your account.</p>
                        </li>
                        <li>
                            <h5>Complete Your Profile</h5>
                            <p>Add more details to your profile to connect with other alumni.</p>
                        </li>
                        <li>
                            <h5>Start Networking</h5>
                            <p>Explore job opportunities, events, and connect with fellow UNRE alumni.</p>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>UNREConnect</h5>
                    <p>Connecting alumni, students, and faculty of the University of Natural Resources and Environment.</p>
                </div>
                <div class="col-md-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="alumni_login.php" class="text-white">Login</a></li>
                        <li><a href="#" class="text-white">About Us</a></li>
                        <li><a href="#" class="text-white">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Connect With Us</h5>
                    <div class="social-links">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">&copy; <?= date('Y') ?> UNREConnect. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Add fade-in animation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.fade-in').style.opacity = '1';
        });
    </script>
</body>
</html>
```