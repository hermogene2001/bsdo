-- Create WebRTC signaling table
CREATE TABLE IF NOT EXISTS `webrtc_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` varchar(255) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message_type` enum('offer','answer','candidate','join','leave') NOT NULL,
  `message_data` text NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `message_type` (`message_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;