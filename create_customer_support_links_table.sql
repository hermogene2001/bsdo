-- Table for customer support links
CREATE TABLE IF NOT EXISTS `customer_support_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `description` text,
  `icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default support links
INSERT INTO `customer_support_links` (`name`, `url`, `description`, `icon`, `is_active`, `sort_order`) VALUES
('Live Chat Support', '/handle_live_chat.php', 'Get instant help through our live chat system', 'fa-comments', 1, 1),
('Email Support', 'mailto:support@bsdosale.com', 'Send us an email for assistance', 'fa-envelope', 1, 2),
('Phone Support', 'tel:+1234567890', 'Call our support team directly', 'fa-phone', 1, 3),
('FAQ Section', '/faq.php', 'Browse our frequently asked questions', 'fa-question-circle', 1, 4);