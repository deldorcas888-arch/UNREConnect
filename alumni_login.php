<?php
// Include common session initialization
require_once __DIR__ . '/includes/init_session.php';

// Enable debug output for this page
$_GET['debug_session'] = true;

// Include database connection
require_once __DIR__ . '/admin/db_connect.php';

// Only redirect to dashboard if already logged in and not on the login page
if (isset($_SESSION['alumni_id']) && verifySession() && !isset($_POST['username'])) {
    $current_page = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // If not on the login page, redirect to dashboard
    if (strpos($current_page, 'alumni_login.php') === false) {
        $redirect = 'home';
        if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
            $requested = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
            $valid_pages = ['home', 'my_account', 'events', 'jobs', 'forum', 'alumni_list', 'gallery', 'about'];
            if (in_array($requested, $valid_pages)) {
                $redirect = $requested;
            }
        }
        header("Location: /unreconnect/alumni/homepage.php?page=" . urlencode($redirect));
        exit();
    }
    // If on login page, continue to show the login form
}

$login_error = '';

// Check for remember me cookie - only process if not already showing the login form
if (isset($_COOKIE['remember_me']) && !isset($_SESSION['alumni_id']) && !isset($_POST['username'])) {
    $token = $_COOKIE['remember_me'];
    
    // Verify token format before database query
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $query = $conn->prepare("SELECT u.*, ab.alumnus_bio_id FROM users u 
                               INNER JOIN user_tokens ut ON u.users_id = ut.user_id 
                               INNER JOIN alumnus_bio ab ON u.users_id = ab.users_id
                               WHERE ut.token = ? AND ut.expires > NOW()
                               AND u.role = 'alumnus' AND u.account_status = 'Approved' 
                               AND ab.status = 1 LIMIT 1");
        $query->bind_param("s", $token);
        $query->execute();
        $result = $query->get_result();
        
        if ($result->num_rows === 1) {
            $alumni = $result->fetch_assoc();
            
            // Regenerate session ID on authentication
            session_regenerate_id(true);
            
            // Clear existing session data
            $_SESSION = [];
            
            // Set user session data
            $_SESSION['users_id']        = $alumni['users_id'];
            $_SESSION['alumni_id']       = $alumni['users_id']; // For backward compatibility
            $_SESSION['login_id']        = $alumni['users_id']; // For backward compatibility with some legacy code
            $_SESSION['alumni_bio_id']   = $alumni['alumnus_bio_id'];
            $_SESSION['alumni_name']     = $alumni['firstname'] . ' ' . $alumni['lastname'];
            $_SESSION['alumni_email']    = $alumni['email'];
            $_SESSION['alumni_role']     = $alumni['role'];
            $_SESSION['role']            = $alumni['role']; // For backward compatibility
            $_SESSION['last_activity']   = time();
            $_SESSION['user_agent']      = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['ip_address']      = $_SERVER['REMOTE_ADDR'];
            
            // For debugging
            error_log('User logged in successfully. User ID: ' . $alumni['users_id'] . ', Role: ' . $alumni['role']);
            
            // Refresh remember me cookie
            setcookie(
                'remember_me',
                $token,
                [
                    'expires' => time() + (86400 * 30), // 30 days
                    'path' => '/',
                    'domain' => '',
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );
            
            // Log successful login
            logLoginAttempt($alumni['email'], true, 'Remember me token', $conn);
            
            // Redirect to the alumni dashboard after successful remember me login
            $redirect = 'home';
            if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                $requested = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
                $valid_pages = ['home', 'my_account', 'events', 'jobs', 'forum', 'alumni_list', 'gallery', 'about'];
                if (in_array($requested, $valid_pages)) {
                    $redirect = $requested;
                }
            }
            header("Location: /unreconnect/alumni/homepage.php?page=" . urlencode($redirect));
            exit();
        } else {
            clearRememberMe();
        }
    } else {
        clearRememberMe();
    }
}

// This section intentionally left blank - duplicate check removed

// Brute force protection
if (checkBruteForce($_SERVER['REMOTE_ADDR'], $conn)) {
    $login_error = 'Too many failed login attempts. Please try again later.';
    http_response_code(429);
}

// Handle login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $login_error = 'Please enter both username and password.';
    } else {
        $query = $conn->prepare("
            SELECT u.*, u.account_status, 
                   u.verification_status as email_verification_status
            FROM users u 
            WHERE u.username = ? AND u.role = 'alumnus' 
            LIMIT 1
        ");
        $query->bind_param("s", $username);
        $query->execute();
        $result = $query->get_result();

        if ($result->num_rows === 1) {
            $alumni = $result->fetch_assoc();

            // Normalize account status
            $status = strtolower(trim($alumni['account_status'] ?? 'pending'));
            $verification_status = strtolower(trim($alumni['verification_status'] ?? 'pending'));
            
            // Check if account is approved
            if ($status !== 'approved') {
                // Handle pending verification
                if ($status === 'pending' && $verification_status === 'pending') {
                    // Redirect to verification page with pre-filled email
                    $_SESSION['pending_verification_email'] = $alumni['email'];
                    header("Location: /unreconnect/alumni/verify_registration.php?email=" . urlencode($alumni['email']));
                    exit();
                }
                
                // Handle other non-approved statuses
                $status_display = ucfirst($status);
                $login_error = "Your account is currently $status_display. " . 
                             "Only approved accounts can log in. Please contact support if you believe this is an error.";
                logLoginAttempt($alumni['email'], false, "Login attempt - account $status", $conn);
                goto render_login_form;
            }

            // Check email verification status
            if (strtolower($alumni['email_verification_status']) !== 'verified') {
                // Store user info in session for resend verification
                $_SESSION['pending_verification_email'] = $alumni['email'];
                $_SESSION['pending_verification_user_id'] = $alumni['users_id'];
                
                // Redirect to verification pending page
                header('Location: /unreconnect/alumni/verification_pending.php');
                exit();
            }

            // Check if account is approved
            if (strtolower($alumni['account_status'] ?? '') !== 'approved') {
                $status = ucfirst(strtolower($alumni['account_status'] ?? 'pending'));
                $login_error = "Your account is currently $status. Please contact support if you believe this is an error.";
                logLoginAttempt($alumni['email'], false, "Login attempt - account $status", $conn);
                goto render_login_form;
            }

            // Verify password
            $password_verified = false;
            if (password_verify($password, $alumni['password'])) {
                $password_verified = true;
            } elseif (md5($password) === $alumni['password'] || $password === $alumni['password']) {
                $password_verified = true;
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE users_id = ?");
                $update->bind_param("si", $hashed_password, $alumni['users_id']);
                $update->execute();
            }

            if ($password_verified) {
                // Regenerate session ID on login
                session_regenerate_id(true);
                
                // Clear existing session data
                $_SESSION = [];
                
                // Generate and store CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                // Set user session data
                $_SESSION['users_id']      = $alumni['users_id'];
                $_SESSION['alumni_id']     = $alumni['users_id']; // For backward compatibility
                $_SESSION['login_id']      = $alumni['users_id']; // For backward compatibility with some legacy code
                $_SESSION['alumni_bio_id'] = $alumni['alumnus_bio_id'];
                $_SESSION['alumni_name']   = $alumni['firstname'] . ' ' . $alumni['lastname'];
                $_SESSION['alumni_email']  = $alumni['email'];
                $_SESSION['alumni_role']   = $alumni['role'];
                $_SESSION['role']          = $alumni['role']; // For backward compatibility
                $_SESSION['last_activity'] = time();
                $_SESSION['user_agent']    = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['ip_address']    = $_SERVER['REMOTE_ADDR'];
                
                // For debugging
                error_log('User logged in successfully. User ID: ' . $alumni['users_id'] . ', Role: ' . $alumni['role']);

                // Remember me
                if (isset($_POST['remember'])) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $delete = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ?");
                    $delete->bind_param("i", $alumni['users_id']);
                    $delete->execute();
                    $insert = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires) VALUES (?, ?, ?)");
                    $insert->bind_param("iss", $alumni['users_id'], $token, $expires);
                    $insert->execute();
                    setcookie('remember_me', $token, strtotime('+30 days'), '/', '', true, true);
                }

                // Get redirect URL from GET parameter or default to homepage
                $redirect_url = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : 'home';
                $valid_pages = ['home', 'my_account', 'events', 'jobs', 'forum', 'alumni_list', 'gallery', 'about'];
                
                // Validate the redirect page
                if (!in_array($redirect_url, $valid_pages)) {
                    $redirect_url = 'home';
                }
                
                // Redirect to the alumni homepage with the page parameter
                header("Location: /unreconnect/alumni/homepage.php?page=" . urlencode($redirect_url));
                exit;
            } else {
                $login_error = 'Invalid username or password.';
                logLoginAttempt($alumni['email'], false, 'Invalid password', $conn);
            }
        } else {
            $login_error = 'Invalid username or password.';
        }
    }
}

/**
 * Clear invalid remember me cookie
 */
function clearRememberMe() {
    setcookie('remember_me', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

/**
 * Check brute force attempts
 */
function checkBruteForce($ip, $conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS `login_attempts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `email` varchar(255) NOT NULL,
        `ip_address` varchar(45) NOT NULL,
        `user_agent` text NOT NULL,
        `success` tinyint(1) NOT NULL DEFAULT 0,
        `attempt_time` datetime NOT NULL,
        `method` varchar(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `ip_address` (`ip_address`),
        KEY `email` (`email`),
        KEY `attempt_time` (`attempt_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $timeLimit = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $query = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempt_time > ?");
    $query->bind_param("ss", $ip, $timeLimit);
    $query->execute();
    $result = $query->get_result()->fetch_assoc();
    return ($result['attempts'] >= 5);
}

/**
 * Log login attempts
 */
function logLoginAttempt($email, $success, $method, $conn) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $success = $success ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address, user_agent, success, attempt_time, method) VALUES (?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param("sssis", $email, $ip, $userAgent, $success, $method);
    $stmt->execute();
}

/**
 * Verify session validity
 */
function verifySession() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        return false;
    }
    if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        return false;
    }
    if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

?>

<?php render_login_form: ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Login | UNREConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        /* Debug info - will be removed later */
        body::before {
            content: '';
            position: fixed;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            font-family: monospace;
            font-size: 12px;
            z-index: 9999;
            display: none; /* Change to block to see debug info */
        }
        
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                        url('/unreconnect/assets/images/login-bg.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        :root {
            --unre-green: #004d25;
            --unre-light-green: #e9f5ec;
            --unre-gold: #ffd700;
            --unre-dark: #333;
            --unre-light: #f8f9fa;
            --unre-dark-green: #003818;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        /* Navbar */
        .navbar {
            background-color: var(--unre-dark-green);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            padding: 0.8rem 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
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
        
        /* Active State */
        .nav-item .nav-link.active {
            color: var(--unre-gold) !important;
            font-weight: 600;
        }

        /* Login Container */
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            margin: 2rem auto;
        }
        
        .login-card {
            width: 100%;
        }

        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .login-header img {
            max-width: 60px;
            margin-bottom: 0.75rem;
        }

        .login-header h2 {
            font-size: 1.5rem;
            margin: 0.25rem 0;
        }

        .login-header p {
            font-size: 0.9rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }

        .form-label {
            font-weight: 600;
            color: #444;
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.9rem;
        }

        .form-control {
            height: 45px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            transition: var(--transition);
            background-color: #fff;
            box-shadow: none;
        }

        .form-control:focus {
            border-color: var(--unre-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 77, 37, 0.15);
            background-color: #fff;
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-right: none;
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .btn-login {
            background: var(--unre-green);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.65rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 100%;
            transition: var(--transition);
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-login:hover {
            background: var(--unre-dark-green);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 77, 37, 0.2);
            color: white;
        }
        
        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .forgot-password,
        .signup-link {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .forgot-password a,
        .signup-link a {
            color: var(--unre-green);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .forgot-password a:hover,
        .signup-link a:hover {
            color: var(--unre-dark-green);
            text-decoration: underline;
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
            transition: var(--transition);
        }

        .forgot-password:hover {
            color: var(--unre-green);
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: #999;
            font-size: 0.9rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e1e5ee;
        }

        .divider::before {
            margin-right: 1rem;
        }

        .divider::after {
            margin-left: 1rem;
        }

        .signup-link {
            text-align: center;
            margin-top: 1rem;
            color: #666;
        }

        .signup-link a {
            color: var(--unre-green);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .signup-link a:hover {
            color: var(--unre-dark-green);
            text-decoration: underline;
        }

        /* Responsive Adjustments */
        @media (max-width: 576px) {
            .login-card {
                padding: 1.25rem;
                margin: 0.5rem;
            }
            
            .login-header img {
                max-width: 50px;
            }
            
            .login-header h2 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <?php 
                $logoFound = false;
                $possiblePaths = [
                    'admin/assets/img/logo.png',
                    'assets/img/logo.png',
                    '../admin/assets/img/logo.png',
                    'unreconnect/assets/images/logo.png'
                ];
                
                foreach ($possiblePaths as $path) {
                    if (file_exists($path)) {
                        echo '<img src="' . $path . '" alt="UNRE Logo" class="me-2" style="height: 40px;">';
                        $logoFound = true;
                        break;
                    }
                }
                
                if (!$logoFound) {
                    echo '<span class="me-2">UNRE</span>';
                }
                ?>
                <span class="d-none d-sm-inline">UNREConnect</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home d-lg-none d-xl-inline"></i>
                            <span class="ms-lg-1">Home</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_login.php">
                            <i class="fas fa-user-shield d-lg-none d-xl-inline"></i>
                            <span class="ms-lg-1">Admin Login</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center align-items-center min-vh-75">
            <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <?php 
                            $logoFound = false;
                            $possiblePaths = [
                                'admin/assets/img/logo.png',
                                'assets/img/logo.png',
                                '../admin/assets/img/logo.png',
                                'unreconnect/assets/images/logo.png'
                            ];
                            
                            foreach ($possiblePaths as $path) {
                                if (file_exists($path)) {
                                    echo '<img src="' . $path . '" alt="UNRE Logo" class="mb-3" style="max-height: 80px; width: auto;">';
                                    $logoFound = true;
                                    break;
                                }
                            }
                            
                            if (!$logoFound) {
                                echo '<h2 class="mb-3 text-primary">UNREConnect</h2>';
                            }
                            ?>
                            <h4 class="mb-4 text-primary">Alumni Login</h4>
                        </div>
                        
                        <?php if (!empty($login_error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($login_error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($_SESSION['pending_verification_email'])): ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-envelope me-2"></i>
                                    <div>
                                        <p class="mb-1">Please verify your email address to continue.</p>
                                        <form action="resend_verification.php" method="POST" class="d-inline">
                                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($_SESSION['pending_verification_email']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-dark">
                                                <i class="fas fa-redo me-1"></i> Resend Verification Email
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label fw-medium">Email or Username</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-user text-primary"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           placeholder="Enter your email or username" required
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                    <div class="invalid-feedback">
                                        <i class="fas fa-exclamation-circle me-1"></i> Please enter your username or email.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label for="password" class="form-label fw-medium">Password</label>
                                    <a href="forgot-password.php" class="text-decoration-none small">
                                        <i class="fas fa-question-circle me-1"></i> Forgot Password?
                                    </a>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-lock text-primary"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter your password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Toggle password visibility">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">
                                        <i class="fas fa-exclamation-circle me-1"></i> Please enter your password.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember"
                                        <?php echo (isset($_POST['remember']) && $_POST['remember'] == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="remember">
                                        Remember me
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-2 mb-3 fw-semibold">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                            
                            <div class="text-center mt-4">
                                <p class="mb-0">Don't have an account? 
                                    <a href="register.php" class="text-primary fw-semibold text-decoration-underline">
                                        Create an account
                                    </a>
                                </p>
                            </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict';
            
            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            var forms = document.querySelectorAll('.needs-validation');
            
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Toggle password visibility with better accessibility
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            if (togglePassword) {
                const passwordInput = document.getElementById('password');
                const icon = togglePassword.querySelector('i');
                
                togglePassword.addEventListener('click', function() {
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                        this.setAttribute('aria-label', 'Hide password');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                        this.setAttribute('aria-label', 'Show password');
                    }
                });
                
                // Add focus styles for better accessibility
                togglePassword.addEventListener('focus', function() {
                    this.classList.add('focus-visible');
                });
                
                togglePassword.addEventListener('blur', function() {
                    this.classList.remove('focus-visible');
                });
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
    <style>
        .focus-visible {
            outline: 2px solid var(--bs-primary);
            outline-offset: 2px;
        }
    </style>
</body>
</html>