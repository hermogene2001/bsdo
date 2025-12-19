-- Update products table to add fee tracking for product uploads
USE bsdo_sale;

-- Add fee columns to products table
ALTER TABLE `products` 
ADD COLUMN `upload_fee` DECIMAL(10,2) DEFAULT NULL AFTER `verification_payment_status`,
ADD COLUMN `upload_fee_paid` TINYINT(1) DEFAULT 0 AFTER `upload_fee`;

-- Add indexes for better performance
ALTER TABLE `products` 
ADD INDEX `idx_upload_fee` (`upload_fee`),
ADD INDEX `idx_upload_fee_paid` (`upload_fee_paid`);