-- Create seller_codes table for BSDO Sale
-- This table stores unique seller codes for each seller account

USE bsdo_sale;

CREATE TABLE IF NOT EXISTS `seller_codes` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `seller_id` INT NOT NULL,
    `seller_code` VARCHAR(255) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_seller_id` (`seller_id`),
    INDEX `idx_seller_code` (`seller_code`),
    CONSTRAINT `fk_seller_codes_user` FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample seller codes for existing sellers (optional)
-- This would typically be handled by the registration process
-- INSERT INTO `seller_codes` (`seller_id`, `seller_code`) 
-- SELECT `id`, CONCAT('SELLER', LPAD(`id`, 6, '0')) FROM `users` WHERE `role` = 'seller';

SELECT 'Seller codes table created successfully!' AS status;