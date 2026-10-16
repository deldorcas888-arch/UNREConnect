<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'admin/db_connect.php';

// Test user ID
$testUserId = 4;

// Check if user exists
$userStmt = $conn->prepare("SELECT users_id, email, firstname, lastname FROM users WHERE users_id = ?");
$userStmt->bind_param("i", $testUserId);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    die("User not found.");
}

$user = $userResult->fetch_assoc();

// Create verification record
$verificationToken = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+7 days')); // Token expires in 7 days

$insertStmt = $conn->prepare("
    INSERT INTO email_verifications 
    (users_id, email, verification_token, expires_at, status) 
    VALUES (?, ?, ?, ?, 'approved')
");

$insertStmt->bind_param("isss", 
    $user['users_id'], 
    $user['email'], 
    $verificationToken,
    $expiresAt
);

if ($insertStmt->execute()) {
    echo "Verification record created successfully for user ID: " . $user['users_id'] . "<br>";
    echo "Email: " . htmlspecialchars($user['email']) . "<br>";
    echo "Verification Token: " . $verificationToken . "<br>";
    echo "Expires At: " . $expiresAt . "<br>";
    echo "<p><a href='test_verification_flow.php'>Test Verification Email</a></p>";
} else {
    echo "Error creating verification record: " . $conn->error;
}

// Show current verification records
echo "<h3>Current Verification Records:</h3>";
$checkStmt = $conn->prepare("SELECT * FROM email_verifications WHERE users_id = ?");
$checkStmt->bind_param("i", $testUserId);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Status</th><th>Created At</th><th>Expires At</th><th>Token</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>" . $row['expires_at'] . "</td>";
        echo "<td style='max-width: 100px; overflow: hidden; text-overflow: ellipsis;'>" . 
             htmlspecialchars($row['verification_token']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No verification records found.";
}
?>
