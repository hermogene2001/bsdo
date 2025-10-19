-- BSDO Sale Referral System Setup
-- Run this SQL file to create the referral and wallet tables
-- Execute: mysql -u root bsdo_sale < setup_referral_system.sql

USE bsdo_sale;

-- Create user wallets table
CREATE TABLE IF NOT EXISTS `user_wallets` (
    `user_id` INT PRIMARY KEY,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create referrals table
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

-- Insert sample wallet entries for existing users (optional)
INSERT IGNORE INTO `user_wallets` (`user_id`, `balance`)
SELECT `id`, 0.00 FROM `users`;

SELECT 'Referral system tables created successfully!' AS status;
