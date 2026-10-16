<?php
session_start();
include './admin/db_connect.php';

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($username === '' || $password === '') {
        $login_error = 'Please enter both username and password.';
    } else {
        $query = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = 'Admin' AND account_status = 'Approved'");
        $query->bind_param("s", $username);
        $query->execute();
        $result = $query->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['users_id'];
                $_SESSION['login_id'] = $user['users_id'];
                $_SESSION['login_name'] = $user['firstname'] . ' ' . $user['lastname'];
                $_SESSION['login_username'] = $user['username'];
                $_SESSION['login_role'] = 'Admin';
                session_regenerate_id(true);
                header("Location: admin/home.php");
                exit();
            } else {
                $login_error = 'Incorrect password';
            }
        } else {
            $login_error = 'Admin account not found or not approved';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Login - UNREConnect</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --unre-green: #004d25;
            --unre-light-green: #e9f5ec;
            --unre-gold: #ffd700;
            --unre-dark-green: #003818;
        }
        
        body {
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navigation Bar */
        .navbar {
            background-color: var(--unre-dark-green);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            padding: 0.8rem 0;
        }
        
        .navbar-brand,
        .navbar-nav .nav-link {
            color: white !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-link {
            padding: 0.5rem 1rem !important;
            margin: 0 0.2rem;
            border-radius: 4px;
        }
        
        .nav-link:hover,
        .nav-link:focus {
            color: var(--unre-gold) !important;
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }
        
        .nav-item .nav-link.active {
            color: var(--unre-gold) !important;
            font-weight: 600;
        }

        /* Login Container */
        .login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
        }

        .login-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 380px;
            padding: 1.75rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .login-header img {
            max-width: 70px;
            margin-bottom: 0.75rem;
        }

        .login-header h2 {
            color: var(--unre-green);
            margin: 0.5rem 0;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .login-header p {
            color: #666;
            margin: 0;
            font-size: 0.95rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            font-weight: 600;
            color: #444;
            margin-bottom: 0.4rem;
            display: block;
            font-size: 0.95rem;
        }

        .form-control {
            height: 44px;
            border: 2px solid #e1e5ee;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .form-control:focus {
            border-color: var(--unre-green);
            box-shadow: 0 0 0 3px rgba(0, 77, 37, 0.2);
            background-color: white;
        }

        .btn-login {
            background: var(--unre-green);
            color: white;
            border: none;
            padding: 0.65rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-login:hover {
            background: var(--unre-dark-green);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 77, 37, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .forgot-password {
            display: block;
            text-align: right;
            margin-top: 0.5rem;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .forgot-password:hover {
            color: var(--unre-green);
            text-decoration: underline;
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 576px) {
            .login-card {
                padding: 1.5rem;
                margin: 0 1rem;
            }
            
            .login-header img {
                max-width: 70px;
            }
            
            .login-header h2 {
                font-size: 1.5rem;
            }
            
            .navbar-brand {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <?php 
                $logoFound = false;
                $possiblePaths = [
                    'admin/assets/img/logo.png',
                    'assets/img/logo.png',
                    '../admin/assets/img/logo.png'
                ];
                
                foreach ($possiblePaths as $path) {
                    if (file_exists($path)) {
                        echo '<img src="' . $path . '" alt="UNRE Logo" style="width: 40px; height: 40px;" class="me-2">';
                        $logoFound = true;
                        break;
                    }
                }
                
                if (!$logoFound) {
                    echo '<span class="me-2">UNRE</span>';
                }
                ?>
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
                        <a class="nav-link" href="alumni_login.php"><i class="fas fa-sign-in-alt me-1"></i> Alumni Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_login.php"><i class="fas fa-user-shield me-1"></i> Admin</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Form -->
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <?php 
                $logoFound = false;
                $possiblePaths = [
                    'admin/assets/img/logo.png',
                    'assets/img/logo.png',
                    '../admin/assets/img/logo.png'
                ];
                
                foreach ($possiblePaths as $path) {
                    if (file_exists($path)) {
                        echo '<img src="' . $path . '" alt="UNRE Logo" width="80" height="80" class="login-logo">';
                        $logoFound = true;
                        break;
                    }
                }
                
                if (!$logoFound) {
                    echo '<h2>UNREConnect</h2>';
                }
                ?>
                <p>Sign in to access the admin dashboard</p>
            </div>
            
            <?php if (!empty($login_error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($username); ?>" 
                           placeholder="Enter your username" required>
                </div>
                
                <div class="form-group">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="password" class="form-label">Password</label>
                        <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                    </div>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn btn-login">Sign In</button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-3">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> UNREConnect. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
