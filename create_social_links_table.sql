-- Table for social media links
CREATE TABLE IF NOT EXISTS `social_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `url` varchar(500) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default social links
INSERT INTO `social_links` (`name`, `url`, `icon`, `is_active`, `sort_order`) VALUES
('Facebook', 'https://facebook.com/bsdosale', 'fab fa-facebook-f', 1, 1),
('Twitter', 'https://twitter.com/bsdosale', 'fab fa-twitter', 1, 2),
('Instagram', 'https://instagram.com/bsdosale', 'fab fa-instagram', 1, 3),
('LinkedIn', 'https://linkedin.com/company/bsdosale', 'fab fa-linkedin-in', 1, 4),
('YouTube', 'https://youtube.com/bsdosale', 'fab fa-youtube', 1, 5);