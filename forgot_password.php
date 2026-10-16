<?php
session_start();
include './admin/db_connect.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT users_id, firstname, lastname FROM users WHERE email = ? AND role = 'alumnus'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE users_id = ?");
            $update->bind_param("ssi", $token, $expires, $user['users_id']);
            
            if ($update->execute()) {
                // Send reset email (in a real application)
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/unreconnect/reset_password.php?token=" . $token;
                
                // In a real application, you would send an email here
                // mail($email, "Password Reset Request", "Click here to reset your password: " . $reset_link);
                
                $message = 'If an account exists with that email, you will receive a password reset link shortly.';
            } else {
                $error = 'An error occurred. Please try again.';
            }
        } else {
            // Don't reveal if the email exists or not (security best practice)
            $message = 'If an account exists with that email, you will receive a password reset link shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Forgot Password | unreconnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <style>
        body {
            background-color: #e9f5ec;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        .card {
            max-width: 500px;
            margin: 2rem auto;
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #004d25;
            color: white;
            text-align: center;
            padding: 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        .card-body {
            padding: 2rem;
        }
        .btn-unre-green {
            background-color: #004d25;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .btn-unre-green:hover {
            background-color: #003d1e;
            color: white;
        }
        .form-control:focus {
            border-color: #004d25;
            box-shadow: 0 0 0 0.25rem rgba(0, 77, 37, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-key me-2"></i>Reset Your Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <p>Enter your email address and we'll send you a link to reset your password.</p>
                        
                        <form method="POST" action="forgot_password.php">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Enter your email address" required 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-unre-green">
                                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                                </button>
                                <a href="alumni_login.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
