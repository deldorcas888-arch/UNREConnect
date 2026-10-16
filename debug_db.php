<?php
// Debug script to check database structure
include 'admin/db_connect.php';

echo "<h2>Database Structure Check</h2>";

// Check if connection is working
if ($conn->connect_error) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $conn->connect_error . "</p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ Database connection successful</p>";
}

// Check if users table exists and show structure
echo "<h3>Users Table Structure:</h3>";
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Users table not found or error: " . $conn->error . "</p>";
}

// Check if alumnus_bio table exists and show structure
echo "<h3>Alumnus_bio Table Structure:</h3>";
$result = $conn->query("DESCRIBE alumnus_bio");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Alumnus_bio table not found or error: " . $conn->error . "</p>";
}

// Check if courses table exists
echo "<h3>Courses Table Structure:</h3>";
$result = $conn->query("DESCRIBE courses");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show available courses
    echo "<h4>Available Courses:</h4>";
    $coursesResult = $conn->query("SELECT * FROM courses");
    if ($coursesResult && $coursesResult->num_rows > 0) {
        echo "<ul>";
        while ($course = $coursesResult->fetch_assoc()) {
            echo "<li>ID: " . $course['courses_id'] . " - " . $course['course'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠️ No courses found in database</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Courses table not found or error: " . $conn->error . "</p>";
}

// Test a sample insert (without actually inserting)
echo "<h3>SQL Query Test:</h3>";
$testUserQuery = "INSERT INTO users (firstname, lastname, email, username, password, role, account_status) VALUES ('Test', 'User', 'test@example.com', 'testuser', 'hashedpass', 'alumnus', 'Pending')";
$testBioQuery = "INSERT INTO alumnus_bio (users_id, middlename, gender, dob, phone, address, batch, courses_id, status) VALUES (1, 'Middle', 'Male', '1990-01-01', '+63123456789', 'Test Address', 2020, 1, 0)";

echo "<p><strong>Users table query:</strong><br><code>" . htmlspecialchars($testUserQuery) . "</code></p>";
echo "<p><strong>Alumnus_bio table query:</strong><br><code>" . htmlspecialchars($testBioQuery) . "</code></p>";

// Check recent error logs
echo "<h3>Recent PHP Errors:</h3>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $errors = file_get_contents($errorLog);
    $recentErrors = array_slice(explode("\n", $errors), -20); // Last 20 lines
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: scroll;'>";
    echo htmlspecialchars(implode("\n", $recentErrors));
    echo "</pre>";
} else {
    echo "<p>Error log not found or not accessible</p>";
}

$conn->close();
?>
