<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = ''; // Default XAMPP password is empty

echo "<pre>";
try {
    // Connect to MySQL without selecting a database
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL server\n<br>";
    
    // Create database if it doesn't exist
    $conn->exec("CREATE DATABASE IF NOT EXISTS unreconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database 'unreconnect' created or already exists\n<br>";
    
    // Select the database
    $conn->exec("USE unreconnect");
    echo "Using database 'unreconnect'\n<br>";
    
    // Check if email_verifications table exists
    $result = $conn->query("SHOW TABLES LIKE 'email_verifications'");
    
    if ($result->rowCount() == 0) {
        // Table doesn't exist, create it
        $conn->exec(file_get_contents('database/create_email_verifications_table.sql'));
        echo "Created email_verifications table\n<br>";
    } else {
        echo "email_verifications table already exists\n<br>";
    }
    
    // Now run the migration
    echo "\nRunning migration...\n<br>";
    $migration_sql = file_get_contents('database/migrations/005_update_email_verifications_table.sql');
    $queries = explode(';', $migration_sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            echo "Executing: " . substr($query, 0, 50) . "...\n<br>";
            $conn->exec($query);
            echo "Query executed successfully\n<br>";
        }
    }
    
    echo "\nDatabase setup completed successfully!\n<br>";
    
} catch(PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n<br>";
}
echo "</pre>";
?>
