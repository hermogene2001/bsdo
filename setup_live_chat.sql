-- Add live stream chat functionality
CREATE TABLE IF NOT EXISTS `live_stream_chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stream_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('text','highlight','announcement') DEFAULT 'text',
  `is_seller` tinyint(1) DEFAULT 0,
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `stream_id` (`stream_id`),
  KEY `user_id` (`user_id`),
  KEY `message_type` (`message_type`),
  KEY `is_pinned` (`is_pinned`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add chat moderation features
CREATE TABLE IF NOT EXISTS `live_stream_chat_moderation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stream_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('mute','ban','timeout') NOT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'Duration in minutes, NULL for permanent',
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stream_id` (`stream_id`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;