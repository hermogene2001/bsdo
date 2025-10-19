-- Table for carousel items
CREATE TABLE IF NOT EXISTS `carousel_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `image_path` varchar(500) NOT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample carousel items
INSERT INTO `carousel_items` (`title`, `description`, `image_path`, `link_url`, `sort_order`, `is_active`) VALUES
('Welcome to BSDO Sale', 'Your trusted e-commerce platform with live streaming, real-time inquiries, and rental products.', 'uploads/carousel/sample1.jpg', '#products', 1, 1),
('Live Shopping Experience', 'Join live streams and interact with sellers in real-time.', 'uploads/carousel/sample2.jpg', 'live_streams.php', 2, 1),
('Rent Products', 'Find amazing products to rent for short-term use.', 'uploads/carousel/sample3.jpg', 'products.php?type=rental', 3, 1);