-- Table for payment channels
CREATE TABLE IF NOT EXISTS `payment_channels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('bank','mobile_money','paypal','cryptocurrency','other') NOT NULL,
  `details` text NOT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample payment channels
INSERT INTO `payment_channels` (`name`, `type`, `details`, `account_name`, `account_number`, `bank_name`, `is_active`) VALUES
('Bank Transfer - Main Account', 'bank', 'Primary bank account for payments', 'BSDO Sale Ltd', '1234567890', 'Global Bank', 1),
('Mobile Money - M-Pesa', 'mobile_money', 'Kenya Mobile Money', 'BSDO Sale Ltd', '0700123456', NULL, 1),
('PayPal Account', 'paypal', 'International PayPal account', 'BSDO Sale Ltd', 'payments@bsdosale.com', NULL, 1);