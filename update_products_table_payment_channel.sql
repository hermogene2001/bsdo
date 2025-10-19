-- Update products table to add payment channel reference
USE bsdo_sale;

-- Add payment channel ID column to products table
ALTER TABLE `products` 
ADD COLUMN `payment_channel_id` INT(11) DEFAULT NULL AFTER `status`,
ADD COLUMN `verification_payment_status` ENUM('pending', 'paid', 'rejected') DEFAULT 'pending' AFTER `payment_channel_id`;

-- Add foreign key constraint
ALTER TABLE `products`
ADD CONSTRAINT `fk_products_payment_channel` 
FOREIGN KEY (`payment_channel_id`) REFERENCES `payment_channels` (`id`) ON DELETE SET NULL;

-- Add indexes for better performance
ALTER TABLE `products` 
ADD INDEX `idx_payment_channel_id` (`payment_channel_id`),
ADD INDEX `idx_verification_payment_status` (`verification_payment_status`);