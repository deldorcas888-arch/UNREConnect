<?php
// Debug notification creation
require_once 'admin/db_connect.php';

echo "=== DEBUGGING NOTIFICATION CREATION ===\n\n";

// Check alumni count
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'alumnus' AND account_status = 'Approved'");
$alumniCount = $result->fetch_assoc()['count'];
echo "Approved alumni count: $alumniCount\n";

// Check recent notifications
$result = $conn->query("SELECT user_id, type, title, created_at FROM notifications ORDER BY created_at DESC LIMIT 20");
echo "\nRecent notifications:\n";
while($row = $result->fetch_assoc()) {
    echo "- User: {$row['user_id']}, Type: {$row['type']}, Title: {$row['title']}, Date: {$row['created_at']}\n";
}

// Check if there are duplicate notifications for the same job
$result = $conn->query("
    SELECT title, COUNT(*) as count 
    FROM notifications 
    WHERE type = 'job' 
    GROUP BY title 
    HAVING COUNT(*) > 1 
    ORDER BY count DESC
");
echo "\nDuplicate job notifications:\n";
while($row = $result->fetch_assoc()) {
    echo "- Title: {$row['title']}, Count: {$row['count']}\n";
}

// Check if notifications are being created for the same user multiple times
$result = $conn->query("
    SELECT user_id, title, COUNT(*) as count 
    FROM notifications 
    WHERE type = 'job' 
    GROUP BY user_id, title 
    HAVING COUNT(*) > 1 
    ORDER BY count DESC
");
echo "\nDuplicate notifications per user:\n";
while($row = $result->fetch_assoc()) {
    echo "- User: {$row['user_id']}, Title: {$row['title']}, Count: {$row['count']}\n";
}

$conn->close();
?>
