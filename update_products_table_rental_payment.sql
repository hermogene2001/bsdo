-- Update products table to ensure payment channel fields exist for rental products
USE bsdo_sale;

-- Check if payment_channel_id column exists, add if not
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'bsdo_sale' 
    AND TABLE_NAME = 'products' 
    AND COLUMN_NAME = 'payment_channel_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `payment_channel_id` INT(11) DEFAULT NULL AFTER `status`',
    'SELECT "Column payment_channel_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if verification_payment_status column exists, add if not
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'bsdo_sale' 
    AND TABLE_NAME = 'products' 
    AND COLUMN_NAME = 'verification_payment_status'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `verification_payment_status` ENUM("pending", "paid", "rejected") DEFAULT "pending" AFTER `payment_channel_id`',
    'SELECT "Column verification_payment_status already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint if not exists
SET @fk_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'bsdo_sale'
    AND TABLE_NAME = 'products'
    AND CONSTRAINT_NAME = 'fk_products_payment_channel'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `products` ADD CONSTRAINT `fk_products_payment_channel` FOREIGN KEY (`payment_channel_id`) REFERENCES `payment_channels` (`id`) ON DELETE SET NULL',
    'SELECT "Foreign key fk_products_payment_channel already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes for better performance if not exists
SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'bsdo_sale'
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'idx_payment_channel_id'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `products` ADD INDEX `idx_payment_channel_id` (`payment_channel_id`)',
    'SELECT "Index idx_payment_channel_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'bsdo_sale'
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'idx_verification_payment_status'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `products` ADD INDEX `idx_verification_payment_status` (`verification_payment_status`)',
    'SELECT "Index idx_verification_payment_status already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;