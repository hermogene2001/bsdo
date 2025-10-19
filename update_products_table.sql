-- Update products table to add address fields and image gallery support
USE bsdo_sale;

-- Add address fields to products table
ALTER TABLE `products` 
ADD COLUMN `address` TEXT DEFAULT NULL AFTER `image_url`,
ADD COLUMN `city` VARCHAR(100) DEFAULT NULL AFTER `address`,
ADD COLUMN `state` VARCHAR(100) DEFAULT NULL AFTER `city`,
ADD COLUMN `country` VARCHAR(100) DEFAULT NULL AFTER `state`,
ADD COLUMN `postal_code` VARCHAR(20) DEFAULT NULL AFTER `country`,
ADD COLUMN `image_gallery` JSON DEFAULT NULL AFTER `image_url`;

-- Add indexes for better performance
ALTER TABLE `products` 
ADD INDEX `idx_address` (`address`(255)),
ADD INDEX `idx_city` (`city`),
ADD INDEX `idx_country` (`country`);