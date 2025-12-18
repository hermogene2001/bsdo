-- BSDO Sale Withdrawal System Setup
-- Run this SQL file to create all tables for the withdrawal and referral system
-- Execute: mysql -u root bsdo_sale < setup_withdrawal_system.sql

USE bsdo_sale;

-- Create settings table (if it doesn't exist)
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create user wallets table for referral system
CREATE TABLE IF NOT EXISTS `user_wallets` (
    `user_id` INT PRIMARY KEY,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create referrals table to track invitations
CREATE TABLE IF NOT EXISTS `referrals` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `inviter_id` INT NOT NULL,
    `invitee_id` INT NOT NULL,
    `invitee_role` ENUM('seller','client') NOT NULL,
    `referral_code` VARCHAR(255),
    `reward_to_inviter` DECIMAL(10,2) DEFAULT 0.00,
    `reward_to_invitee` DECIMAL(10,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_inviter` (`inviter_id`),
    INDEX `idx_invitee` (`invitee_id`),
    CONSTRAINT `fk_ref_inviter` FOREIGN KEY (`inviter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ref_invitee` FOREIGN KEY (`invitee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create withdrawal requests table
CREATE TABLE IF NOT EXISTS `withdrawal_requests` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `seller_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `payment_details` TEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `admin_notes` TEXT,
    `processed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_seller` (`seller_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_withdrawal_seller` FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add withdrawal threshold setting
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('min_withdrawal_amount', '5.00', 'Minimum amount required to request withdrawal');

-- Insert sample wallet entries for existing users (optional)
INSERT IGNORE INTO `user_wallets` (`user_id`, `balance`)
SELECT `id`, 0.00 FROM `users`;

SELECT 'Withdrawal and referral system tables created successfully!' AS status;