-- Fix Donation Database Structure
-- Run this script in your database management tool (phpMyAdmin, MySQL Workbench, etc.)

-- Step 1: Drop existing tables
DROP TABLE IF EXISTS `donations`;
DROP TABLE IF EXISTS `donation_campaigns`;
DROP TABLE IF EXISTS `donation_types`;

-- Step 2: Create donation_campaigns table
CREATE TABLE `donation_campaigns` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 3: Create donation_types table
CREATE TABLE `donation_types` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 4: Insert default data
INSERT INTO `donation_campaigns` (title, description, goal_amount, start_date, end_date) 
VALUES ('General Donation Campaign', 'Support UNRE initiatives and programs', 10000.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR));

INSERT INTO `donation_types` (name, description) 
VALUES ('General Donation', 'General contribution to UNRE');

-- Step 5: Create donations table
CREATE TABLE `donations` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `donor_id` INT NOT NULL,
    `campaign_id` INT NOT NULL DEFAULT 1,
    `donation_type_id` INT NOT NULL DEFAULT 1,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 6: Add foreign key constraints
ALTER TABLE `donations` 
ADD CONSTRAINT `fk_donations_donor_id` 
FOREIGN KEY (`donor_id`) REFERENCES `users`(`users_id`) ON DELETE CASCADE;

ALTER TABLE `donations` 
ADD CONSTRAINT `fk_donations_campaign_id` 
FOREIGN KEY (`campaign_id`) REFERENCES `donation_campaigns`(`id`) ON DELETE RESTRICT;

ALTER TABLE `donations` 
ADD CONSTRAINT `fk_donations_type_id` 
FOREIGN KEY (`donation_type_id`) REFERENCES `donation_types`(`id`) ON DELETE RESTRICT;

-- Verification queries
SELECT 'Donation Campaigns Table Structure:' as info;
DESCRIBE donation_campaigns;

SELECT 'Donation Types Table Structure:' as info;
DESCRIBE donation_types;

SELECT 'Donations Table Structure:' as info;
DESCRIBE donations;

SELECT 'Default Data Inserted:' as info;
SELECT * FROM donation_campaigns;
SELECT * FROM donation_types;