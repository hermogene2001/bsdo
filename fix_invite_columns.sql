-- SQL to add missing invitation link columns to live_streams table
-- This script checks if columns exist and adds them if they don't

-- Add invite_code column if it doesn't exist
ALTER TABLE live_streams 
ADD COLUMN IF NOT EXISTS invite_code VARCHAR(128) DEFAULT NULL AFTER hls_url;

-- Add invite_expires_at column if it doesn't exist
ALTER TABLE live_streams 
ADD COLUMN IF NOT EXISTS invite_expires_at DATETIME DEFAULT NULL AFTER invite_code;

-- Add indexes for performance if they don't exist
CREATE INDEX IF NOT EXISTS idx_invite_code ON live_streams(invite_code);
CREATE INDEX IF NOT EXISTS idx_invite_expires_at ON live_streams(invite_expires_at);

-- Add index for invitation_code as well (different column)
CREATE INDEX IF NOT EXISTS idx_invitation_code ON live_streams(invitation_code);