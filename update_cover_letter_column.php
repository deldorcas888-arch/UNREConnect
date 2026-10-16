<?php
// Database configuration
require_once 'config.php';

// Check if the script is being run from the command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

try {
    // Connect to the database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");
    
    // Check if the column exists and needs to be modified
    $checkColumn = $conn->query("SHOW COLUMNS FROM job_applications LIKE 'cover_letter'");
    
    if ($checkColumn->num_rows > 0) {
        // Column exists, modify it to store file paths
        $sql = "ALTER TABLE job_applications MODIFY COLUMN cover_letter VARCHAR(255) NOT NULL COMMENT 'Stores the filename of the uploaded cover letter'";
        
        if ($conn->query($sql) === TRUE) {
            echo "Successfully updated cover_letter column to store file paths.\n";
        } else {
            throw new Exception("Error updating cover_letter column: " . $conn->error);
        }
    } else {
        echo "cover_letter column not found. Make sure the table exists.\n";
    }
    
    // Close the connection
    $conn->close();
    
    echo "Database update completed successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Create the cover_letters directory if it doesn't exist
$coverLetterDir = __DIR__ . '/uploads/cover_letters';
if (!file_exists($coverLetterDir)) {
    if (mkdir($coverLetterDir, 0755, true)) {
        echo "Created cover_letters directory at: $coverLetterDir\n";
    } else {
        echo "Failed to create cover_letters directory at: $coverLetterDir\n";
    }
} else {
    echo "cover_letters directory already exists at: $coverLetterDir\n";
}

echo "All updates completed.\n";
