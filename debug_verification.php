<?php
require_once 'admin/db_connect.php';
require_once 'includes/mail_config.php';
require_once 'includes/mail_handler.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to create a test verification record
function createTestVerification($email, $userId) {
    global $conn;
    
    try {
        // Generate a new token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Insert test verification record
        $stmt = $conn->prepare("
            INSERT INTO email_verifications (users_id, email, verification_token, status, expires_at, created_at)
            VALUES (?, ?, ?, 'pending', ?, NOW())
        ");
        
        $stmt->bind_param("isss", $userId, $email, $token, $expires);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create test verification: " . $stmt->error);
        }
        
        $verificationId = $conn->insert_id;
        
        return [
            'id' => $verificationId,
            'token' => $token,
            'link' => 'http://' . $_SERVER['HTTP_HOST'] . '/unreconnect/verify_email.php?token=' . urlencode($token)
        ];
        
    } catch (Exception $e) {
        die("Error creating test verification: " . $e->getMessage());
    }
}

// Function to test the verification link
function testVerificationLink($token) {
    global $conn;
    
    try {
        // First, decode the token in case it's URL-encoded
        $decodedToken = urldecode($token);
        
        // Check if token exists in database
        $stmt = $conn->prepare("SELECT * FROM email_verifications WHERE verification_token = ?");
        $stmt->bind_param("s", $decodedToken);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => 'Token not found in database',
                'token_used' => $decodedToken
            ];
        }
        
        $verification = $result->fetch_assoc();
        
        // Check if token is expired
        $now = new DateTime();
        $expires = new DateTime($verification['expires_at']);
        
        if ($now > $expires) {
            return [
                'status' => 'error',
                'message' => 'Token has expired',
                'expires_at' => $verification['expires_at']
            ];
        }
        
        return [
            'status' => 'success',
            'verification' => $verification
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Error testing verification: ' . $e->getMessage()
        ];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $testEmail = 'test_' . time() . '@example.com';
    $testUserId = 1; // Use a valid user ID
    
    // Create test verification
    $verification = createTestVerification($testEmail, $testUserId);
    
    // Test the verification link
    $testResult = testVerificationLink($verification['token']);
    
    // Output results
    echo "<h3>Test Results</h3>";
    echo "<pre>" . print_r([
        'verification_created' => $verification,
        'verification_test' => $testResult
    ], true) . "</pre>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Verification Debugger</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .panel { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #dff0d8; border-color: #d6e9c6; }
        .error { background-color: #f2dede; border-color: #ebccd1; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Email Verification Debugger</h1>
        
        <div class="panel">
            <h2>Test Verification Flow</h2>
            <form method="post">
                <button type="submit" name="test_email">Run Verification Test</button>
            </form>
        </div>
        
        <div class="panel">
            <h2>Test Existing Token</h2>
            <form method="get">
                <div>
                    <label>Token to test: 
                        <input type="text" name="token" style="width: 500px;" 
                               value="<?php echo isset($_GET['token']) ? htmlspecialchars($_GET['token']) : ''; ?>">
                    </label>
                    <button type="submit">Test Token</button>
                </div>
            </form>
            
            <?php if (isset($_GET['token'])): ?>
                <h3>Test Results</h3>
                <?php 
                $tokenTest = testVerificationLink($_GET['token']);
                echo "<pre>" . print_r($tokenTest, true) . "</pre>";
                ?>
            <?php endif; ?>
        </div>
        
        <div class="panel">
            <h2>Recent Verification Records</h2>
            <?php
            $recent = $conn->query("
                SELECT id, users_id, email, status, created_at, expires_at, 
                       LEFT(verification_token, 10) as token_preview 
                FROM email_verifications 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            
            if ($recent->num_rows > 0): ?>
                <table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <th>ID</th>
                        <th>User ID</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Expires</th>
                        <th>Token Preview</th>
                    </tr>
                    <?php while ($row = $recent->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['users_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo $row['status']; ?></td>
                            <td><?php echo $row['created_at']; ?></td>
                            <td><?php echo $row['expires_at']; ?></td>
                            <td><?php echo $row['token_preview']; ?>...</td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            <?php else: ?>
                <p>No verification records found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
