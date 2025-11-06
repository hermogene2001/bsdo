-- Add invitation columns to live_streams table
ALTER TABLE live_streams
ADD COLUMN invitation_code VARCHAR(32) AFTER stream_key,
ADD COLUMN invitation_enabled BOOLEAN DEFAULT true AFTER invitation_code,
ADD COLUMN invitation_expiry DATETIME DEFAULT NULL AFTER invitation_enabled;

-- Create index for faster invitation code lookups
CREATE INDEX idx_invitation_code ON live_streams(invitation_code);

-- Update existing streams to have default invitation codes
UPDATE live_streams 
SET invitation_code = CONCAT('INIT_', SUBSTRING(MD5(RAND()) FROM 1 FOR 8))
WHERE invitation_code IS NULL;
