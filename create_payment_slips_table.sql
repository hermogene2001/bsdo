-- Table for payment slips
CREATE TABLE IF NOT EXISTS `payment_slips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `slip_path` varchar(500) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `verification_rate` decimal(5,2) DEFAULT 0.50,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `admin_notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `seller_id` (`seller_id`),
  KEY `status` (`status`),
  CONSTRAINT `payment_slips_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_slips_seller_fk` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;