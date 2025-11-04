-- Add stream issues tracking
CREATE TABLE IF NOT EXISTS `stream_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stream_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `issue_type` enum('connection','quality','audio','video','other') NOT NULL,
  `details` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `stream_id` (`stream_id`),
  KEY `user_id` (`user_id`),
  KEY `issue_type` (`issue_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add quality options and connection status to live_streams
ALTER TABLE live_streams 
ADD COLUMN quality_options JSON DEFAULT NULL AFTER connection_status;

-- Add last activity tracking to live_stream_viewers
ALTER TABLE live_stream_viewers
ADD COLUMN last_activity timestamp NULL DEFAULT NULL AFTER joined_at,
ADD INDEX idx_last_activity (last_activity);

-- Add automatic cleanup trigger
DELIMITER //
CREATE TRIGGER cleanup_inactive_viewers AFTER UPDATE ON live_streams
FOR EACH ROW
BEGIN
    IF NEW.is_live = 0 AND OLD.is_live = 1 THEN
        UPDATE live_stream_viewers 
        SET is_active = 0, left_at = NOW() 
        WHERE stream_id = NEW.id AND is_active = 1;
    END IF;
END;
//
DELIMITER ;