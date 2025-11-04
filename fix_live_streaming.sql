-- Add webrtc_messages table for signaling
CREATE TABLE IF NOT EXISTS `webrtc_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` varchar(100) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message_type` enum('offer','answer','ice-candidate') NOT NULL,
  `message_data` text NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `is_processed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  KEY `sender_id` (`sender_id`),
  KEY `message_type` (`message_type`),
  KEY `is_processed` (`is_processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add cleanup trigger for ended streams
DELIMITER //
CREATE TRIGGER cleanup_ended_streams AFTER UPDATE ON live_streams
FOR EACH ROW
BEGIN
    IF NEW.status = 'ended' AND OLD.status != 'ended' THEN
        -- Mark all viewers as inactive
        UPDATE live_stream_viewers 
        SET is_active = 0, left_at = CURRENT_TIMESTAMP 
        WHERE stream_id = NEW.id AND is_active = 1;
        
        -- Clean up WebRTC messages
        DELETE FROM webrtc_messages 
        WHERE room_id LIKE CONCAT('room_', NEW.id, '_%');
    END IF;
END;
//
DELIMITER ;

-- Add indexes for performance
ALTER TABLE live_streams 
ADD INDEX `idx_active_streams` (`is_live`, `status`),
ADD INDEX `idx_recent_streams` (`ended_at`);

-- Add connection status column
ALTER TABLE live_streams
ADD COLUMN `connection_status` enum('connected','disconnected','reconnecting') 
DEFAULT 'connected' AFTER `is_live`;