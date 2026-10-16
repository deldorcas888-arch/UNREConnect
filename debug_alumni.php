<?php
// Database configuration
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'unreconnect'
];

// Create connection
$conn = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Connection</h2>";
echo "Connected to database: " . $config['database'] . "<br><br>";

// Check if users table exists
$tables = [];
$result = $conn->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
}

echo "<h3>Tables in database:</h3>";
echo "<pre>";
print_r($tables);
echo "</pre>";

// Check users table structure
if (in_array('users', $tables)) {
    echo "<h3>Users table structure:</h3>";
    $result = $conn->query("DESCRIBE users");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "</pre>";

    // Check users data
    echo "<h3>Users data (first 10 records):</h3>";
    $result = $conn->query("SELECT * FROM users LIMIT 10");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
}

// Check alumnus_bio table
if (in_array('alumnus_bio', $tables)) {
    echo "<h3>Alumnus Bio data (first 10 records):</h3>";
    $result = $conn->query("SELECT * FROM alumnus_bio LIMIT 10");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
}

// Test the alumni query
echo "<h3>Alumni Query Results:</h3>";
$sql = "
    SELECT 
        u.users_id, 
        u.username, 
        u.email, 
        u.firstname, 
        u.lastname, 
        c.course as program, 
        c.course as department, 
        ab.batch as year_graduated, 
        u.avatar,
        u.account_status
    FROM users u
    LEFT JOIN alumnus_bio ab ON u.users_id = ab.users_id
    LEFT JOIN courses c ON ab.courses_id = c.courses_id
    WHERE u.account_status = 'Approved'
    LIMIT 10
";

$result = $conn->query($sql);
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        // Header
        echo "<tr>";
        while ($field = $result->fetch_field()) {
            echo "<th>" . $field->name . "</th>";
        }
        echo "</tr>";
        // Data
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No records found with the current query.<br>";
        echo "SQL: " . htmlspecialchars($sql) . "<br>";
        echo "Error: " . $conn->error;
    }
} else {
    echo "Query failed: " . $conn->error;
}

$conn->close();
?>
