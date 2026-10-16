<?php
// Start session with the same settings as login
session_name('ALUMNI_SESSID');
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_strict_mode' => true,
    'cookie_samesite' => 'Strict'
]);

// Get user ID before destroying session for token cleanup
$userId = $_SESSION['alumni_id'] ?? null;

// Delete the remember_me token from database if we have a user ID and token exists
if (isset($_COOKIE['remember_me']) && $userId) {
    require_once 'admin/db_connect.php';
    $token = $_COOKIE['remember_me'];
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ? AND token = ?");
        $stmt->bind_param("is", $userId, $token);
        $stmt->execute();
    }
}

// Clear all session data
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Strict'
        ]
    );
}

// Clear remember_me cookie if it exists
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    unset($_COOKIE['remember_me']);
}

// Prevent caching of the page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Start a new session just for the logout message
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_strict_mode' => true,
    'cookie_samesite' => 'Strict',
    'name' => 'ALUMNI_SESSID'
]);
$_SESSION['logout_message'] = 'You have been successfully logged out.';

// Redirect to home page - use absolute path to ensure it works from any directory
$redirect_url = 'http://' . $_SERVER['HTTP_HOST'] . '/unreconnect/index.php';
header("Location: " . $redirect_url);
exit();
