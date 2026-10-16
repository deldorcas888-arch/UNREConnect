<?php
// Database connection
require_once './admin/db_connect.php';

echo "<h2>🔧 Fixing Donation Database Structure</h2>";

try {
    // Step 1: Drop existing donations table and its constraints
    echo "<p>📋 Step 1: Dropping existing donations table...</p>";
    $conn->query("DROP TABLE IF EXISTS `donations`");
    echo "✅ Donations table dropped successfully<br>";

    // Step 2: Drop and recreate donation_campaigns table to ensure clean structure
    echo "<p>📋 Step 2: Creating donation_campaigns table...</p>";
    $conn->query("DROP TABLE IF EXISTS `donation_campaigns`");
    $sql = "CREATE TABLE `donation_campaigns` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `goal_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `current_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `status` ENUM('active', 'inactive', 'completed') NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql);
    echo "✅ Donation campaigns table created successfully<br>";

    // Step 3: Drop and recreate donation_types table to ensure clean structure
    echo "<p>📋 Step 3: Creating donation_types table...</p>";
    $conn->query("DROP TABLE IF EXISTS `donation_types`");
    $sql = "CREATE TABLE `donation_types` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql);
    echo "✅ Donation types table created successfully<br>";

    // Step 4: Insert default data
    echo "<p>📋 Step 4: Inserting default data...</p>";
    
    // Insert default campaign
    $conn->query("INSERT INTO donation_campaigns (title, description, goal_amount, start_date, end_date) VALUES ('General Donation Campaign', 'Support UNRE initiatives and programs', 10000.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))");
    echo "✅ Default campaign inserted<br>";

    // Insert default donation type
    $conn->query("INSERT INTO donation_types (name, description) VALUES ('General Donation', 'General contribution to UNRE')");
    echo "✅ Default donation type inserted<br>";

    // Step 5: Get the IDs for defaults
    $campaignResult = $conn->query("SELECT id FROM donation_campaigns LIMIT 1");
    $campaignId = 1;
    if ($campaignResult && $campaignResult->num_rows > 0) {
        $campaignRow = $campaignResult->fetch_assoc();
        $campaignId = $campaignRow['id'];
    }

    $typeResult = $conn->query("SELECT id FROM donation_types LIMIT 1");
    $typeId = 1;
    if ($typeResult && $typeResult->num_rows > 0) {
        $typeRow = $typeResult->fetch_assoc();
        $typeId = $typeRow['id'];
    }

    echo "✅ Using Campaign ID: $campaignId, Type ID: $typeId<br>";

    // Step 6: Create donations table with correct structure
    echo "<p>📋 Step 5: Creating donations table with correct structure...</p>";
    $sql = "CREATE TABLE `donations` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `donor_id` INT NOT NULL,
        `campaign_id` INT NOT NULL DEFAULT $campaignId,
        `donation_type_id` INT NOT NULL DEFAULT $typeId,
        `amount` DECIMAL(10,2) NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `transaction_id` VARCHAR(255),
        `payment_status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
        `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
        `notes` TEXT,
        `billing_first_name` VARCHAR(100) NOT NULL,
        `billing_last_name` VARCHAR(100) NOT NULL,
        `billing_email` VARCHAR(255) NOT NULL,
        `billing_phone` VARCHAR(50),
        `billing_address` TEXT,
        `billing_city` VARCHAR(100),
        `billing_state` VARCHAR(100),
        `billing_country` VARCHAR(100) DEFAULT 'Papua New Guinea',
        `billing_postal_code` VARCHAR(20),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_donor_id` (`donor_id`),
        KEY `idx_campaign_id` (`campaign_id`),
        KEY `idx_payment_status` (`payment_status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql);
    echo "✅ Donations table created successfully<br>";

    // Step 7: Add foreign key constraints
    echo "<p>📋 Step 6: Adding foreign key constraints...</p>";
    
    $conn->query("
        ALTER TABLE `donations` 
        ADD CONSTRAINT `fk_donations_donor_id` 
        FOREIGN KEY (`donor_id`) REFERENCES `users`(`users_id`) ON DELETE CASCADE
    ");
    echo "✅ Foreign key for donor_id added<br>";
    
    $conn->query("
        ALTER TABLE `donations` 
        ADD CONSTRAINT `fk_donations_campaign_id` 
        FOREIGN KEY (`campaign_id`) REFERENCES `donation_campaigns`(`id`) ON DELETE RESTRICT
    ");
    echo "✅ Foreign key for campaign_id added<br>";
    
    $conn->query("
        ALTER TABLE `donations` 
        ADD CONSTRAINT `fk_donations_type_id` 
        FOREIGN KEY (`donation_type_id`) REFERENCES `donation_types`(`id`) ON DELETE RESTRICT
    ");
    echo "✅ Foreign key for donation_type_id added<br>";

    // Step 8: Verify the structure
    echo "<p>📋 Step 7: Verifying table structure...</p>";
    
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

    // Step 9: Test data insertion
    echo "<p>📋 Step 8: Testing data insertion...</p>";
    
    // Check if there are any users in the system
    $userResult = $conn->query("SELECT users_id FROM users LIMIT 1");
    if ($userResult && $userResult->num_rows > 0) {
        $userRow = $userResult->fetch_assoc();
        $testUserId = $userRow['users_id'];
        
        // Try to insert a test donation
        $testSql = "INSERT INTO donations (donor_id, amount, payment_method, billing_first_name, billing_last_name, billing_email) VALUES (?, 100.00, 'test', 'Test', 'User', 'test@example.com')";
        $stmt = $conn->prepare($testSql);
        $stmt->bind_param("i", $testUserId);
        
        if ($stmt->execute()) {
            $testId = $conn->insert_id;
            echo "✅ Test donation inserted successfully (ID: $testId)<br>";
            
            // Clean up test data
            $conn->query("DELETE FROM donations WHERE id = $testId");
            echo "✅ Test data cleaned up<br>";
        } else {
            echo "❌ Test insertion failed: " . $stmt->error . "<br>";
        }
    } else {
        echo "⚠️ No users found in system - skipping test insertion<br>";
    }

    echo "<h3>🎉 Database Fix Completed Successfully!</h3>";
    echo "<p>The donation system database has been properly configured. You can now use the donation form without foreign key constraint errors.</p>";
    echo "<p><a href='alumni/homepage.php?page=donate' style='background: #004d25; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Donation Form</a></p>";

} catch (Exception $e) {
    echo "<h3>❌ Error Occurred</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}

$conn->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #f5f5f5;
}

h2 {
    color: #004d25;
    border-bottom: 2px solid #004d25;
    padding-bottom: 10px;
}

p {
    margin: 10px 0;
    padding: 10px;
    background: white;
    border-radius: 5px;
    border-left: 4px solid #004d25;
}

table {
    background: white;
    border-radius: 5px;
    overflow: hidden;
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

h3 {
    color: #004d25;
    text-align: center;
    padding: 20px;
    background: white;
    border-radius: 10px;
    margin: 20px 0;
}
</style>
