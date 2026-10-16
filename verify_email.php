<?php
require_once __DIR__ . '/admin/db_connect.php';
require_once __DIR__ . '/alumni/includes/verification.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if token is provided
if (!isset($_GET['token']) || empty(trim($_GET['token']))) {
    $_SESSION['verification_error'] = 'No verification token provided';
    header('Location: verification_success.php?error=no_token');
    exit();
}

$token = trim($_GET['token']);

// Process verification
try {
    $verification = new VerificationHandler($conn);
    $verification_result = $verification->verifyEmail($token);
    
    // Store result in session for the success page
    $_SESSION['verification_result'] = $verification_result;
    
    // Redirect to success page with token
    header("Location: verification_success.php?token=" . urlencode($token));
    exit();
    
} catch (Exception $e) {
    // Log the error
    error_log("Email verification failed: " . $e->getMessage());
    
    // Store error in session
    $_SESSION['verification_error'] = 'An error occurred during verification. Please try again or contact support.';
    
    // Redirect to error page or back to verification page
    header('Location: verification_success.php?error=verification_failed');
    exit();
}
