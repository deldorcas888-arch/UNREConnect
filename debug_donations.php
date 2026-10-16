<?php
// Debug script to check donations
require_once 'admin/db_connect.php';

echo "<h2>🔍 Debug Donations</h2>";

try {
    // Check if donations table exists
    $result = $conn->query("SHOW TABLES LIKE 'donations'");
    if ($result->num_rows > 0) {
        echo "✅ Donations table exists<br>";
        
        // Check table structure
        echo "<h3>📋 Donations Table Structure:</h3>";
        $result = $conn->query("DESCRIBE donations");
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if there are any donations
        $result = $conn->query("SELECT COUNT(*) as count FROM donations");
        $row = $result->fetch_assoc();
        echo "<h3>📊 Donation Count: " . $row['count'] . "</h3>";
        
        if ($row['count'] > 0) {
            // Show recent donations
            echo "<h3>📝 Recent Donations:</h3>";
            $result = $conn->query("SELECT * FROM donations ORDER BY created_at DESC LIMIT 5");
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Donor ID</th><th>Amount</th><th>Status</th><th>Created</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['donor_id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['amount']) . "</td>";
                echo "<td>" . htmlspecialchars($row['payment_status']) . "</td>";
                echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Check users table
        echo "<h3>👥 Users in System:</h3>";
        $result = $conn->query("SELECT users_id, firstname, lastname, email FROM users LIMIT 5");
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>User ID</th><th>Name</th><th>Email</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['users_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "❌ Donations table does not exist<br>";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ Error Occurred</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
    background: #f5f5f5;
}

h2, h3 {
    color: #004d25;
}

table {
    background: white;
    border-radius: 5px;
    overflow: hidden;
    width: 100%;
}

th {
    background: #004d25;
    color: white;
    padding: 8px;
}

td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
</style>
