-- Live Streaming Database Schema for BSDO Sale
-- Run this SQL to create the necessary tables for live streaming functionality

-- Table for live streams
CREATE TABLE IF NOT EXISTS `live_streams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `category_id` int(11) DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `stream_key` varchar(100) NOT NULL,
  `is_live` tinyint(1) DEFAULT 0,
  `viewer_count` int(11) DEFAULT 0,
  `max_viewers` int(11) DEFAULT 0,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `duration` int(11) DEFAULT 0 COMMENT 'Duration in seconds',
  `status` enum('scheduled','live','ended','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `seller_id` (`seller_id`),
  KEY `category_id` (`category_id`),
  KEY `is_live` (`is_live`),
  KEY `status` (`status`),
  KEY `scheduled_at` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for live stream viewers (to track who's watching)
CREATE TABLE IF NOT EXISTS `live_stream_viewers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stream_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `joined_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `left_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `stream_id` (`stream_id`),
  KEY `user_id` (`user_id`),
  KEY `session_id` (`session_id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for live stream comments/chat
CREATE TABLE IF NOT EXISTS `live_stream_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stream_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `comment` text NOT NULL,
  `is_seller` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `stream_id` (`stream_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for live stream products (products featured in streams)
CREATE TABLE IF NOT EXISTS `live_stream_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stream_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `featured_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `is_highlighted` tinyint(1) DEFAULT 0,
  `special_price` decimal(10,2) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stream_id` (`stream_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for live stream analytics
CREATE TABLE IF NOT EXISTS `live_stream_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stream_id` int(11) NOT NULL,
  `peak_viewers` int(11) DEFAULT 0,
  `total_viewers` int(11) DEFAULT 0,
  `total_comments` int(11) DEFAULT 0,
  `total_products_featured` int(11) DEFAULT 0,
  `total_sales` decimal(10,2) DEFAULT 0.00,
  `engagement_score` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `stream_id` (`stream_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key constraints
ALTER TABLE `live_streams`
  ADD CONSTRAINT `live_streams_seller_fk` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `live_streams_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

ALTER TABLE `live_stream_viewers`
  ADD CONSTRAINT `live_stream_viewers_stream_fk` FOREIGN KEY (`stream_id`) REFERENCES `live_streams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `live_stream_viewers_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `live_stream_comments`
  ADD CONSTRAINT `live_stream_comments_stream_fk` FOREIGN KEY (`stream_id`) REFERENCES `live_streams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `live_stream_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `live_stream_products`
  ADD CONSTRAINT `live_stream_products_stream_fk` FOREIGN KEY (`stream_id`) REFERENCES `live_streams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `live_stream_products_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `live_stream_analytics`
  ADD CONSTRAINT `live_stream_analytics_stream_fk` FOREIGN KEY (`stream_id`) REFERENCES `live_streams` (`id`) ON DELETE CASCADE;

-- Insert some sample data for testing
INSERT INTO `live_streams` (`seller_id`, `title`, `description`, `category_id`, `stream_key`, `is_live`, `viewer_count`, `status`, `scheduled_at`) VALUES
(1, 'Electronics Showcase', 'Live demonstration of latest electronics and gadgets', 1, 'stream_key_123', 0, 0, 'scheduled', NOW() + INTERVAL 1 HOUR),
(2, 'Fashion Live Sale', 'Exclusive fashion items with live discounts', 2, 'stream_key_456', 1, 15, 'live', NOW()),
(3, 'Home & Garden Tips', 'Live tips and product demonstrations for home improvement', 3, 'stream_key_789', 0, 0, 'scheduled', NOW() + INTERVAL 2 HOUR);



