# RTMP/HLS Streaming Implementation

## Overview
This document describes the implementation of RTMP/HLS video streaming functionality for the BSDO Sale platform, replacing the previous WebRTC implementation.

## Changes Made

### 1. Database Schema Updates
- Added `rtmp_url` and `hls_url` columns to the `live_streams` table
- Added `invitation_code`, `invitation_enabled`, and `invitation_expiry` columns for stream access control
- These columns store the RTMP ingest URL and HLS playback URL for each stream

### 2. Seller Interface (RTMP)
- Modified `seller/live_stream_webrtc.php` to:
  - Remove all WebRTC camera functionality
  - Display RTMP server URL and stream key for use with OBS or similar software
  - Show instructions for setting up RTMP streaming
  - Added copy functionality for stream information

### 3. Viewer Interface (HLS)
- Modified `watch_stream.php` to:
  - Replace WebRTC player with HLS.js player
  - Use HTML5 video element with HLS support
  - Fall back to native HLS support for Safari browsers
  - Display HLS stream URL from database

### 4. Backend Services
- Created new `rtmp_server.php` for handling RTMP/HLS stream management
- Deprecated `webrtc_server.php` with deprecation notice
- Removed `public/js/webrtc-client.js` as it's no longer needed

### 5. Stream Management
- Added functionality to generate RTMP and HLS URLs when creating streams
- Added stream status management (live/offline)
- Maintained viewer tracking and chat functionality

## How It Works

### For Sellers:
1. Navigate to "Go Live" from the products page
2. Start a new live stream with title, description, and category
3. Use OBS Studio or similar software with the provided RTMP details:
   - RTMP Server URL
   - Stream Key
4. Start streaming from your software to go live
5. Feature products from your catalog during the stream
6. Interact with clients through the chat interface
7. End the stream when finished

### For Clients:
1. Browse live streams on the `live_streams.php` page
2. Join live streams to watch the HLS video feed
3. View featured products with special prices
4. Purchase products directly during the live stream
5. Chat with the seller in real-time

## Technical Details

### RTMP Configuration
- **RTMP Server**: Configurable endpoint for ingesting streams
- **Stream Keys**: Unique identifiers for each stream
- **Security**: Authentication required for stream management

### HLS Configuration
- **HLS Server**: Configurable endpoint for serving HLS segments
- **Adaptive Bitrate**: Multiple quality levels automatically selected
- **Browser Support**: Works in all modern browsers with HLS.js fallback

### Fallback Handling
- HLS.js library for browsers that don't natively support HLS
- Native HLS support for Safari browsers
- Error handling and retry mechanisms

## File Changes Summary

### Modified Files:
1. `seller/live_stream_webrtc.php` - Seller streaming interface
2. `watch_stream.php` - Viewer streaming interface
3. `database_schema.sql` - Database schema updates
4. `webrtc_server.php` - Deprecated with deprecation notice

### New Files:
1. `rtmp_server.php` - RTMP/HLS stream management API
2. `update_database_rtmp_invitation.php` - Database schema update script
3. `RTMP_HLS_STREAMING_IMPLEMENTATION.md` - This document

### Removed Files:
1. `public/js/webrtc-client.js` - WebRTC client JavaScript

## Deployment Notes
1. Configure RTMP server endpoint in the application
2. Configure HLS server endpoint in the application
3. Run `update_database_rtmp_invitation.php` to update your database schema
4. Test streaming with OBS or similar software
5. Verify viewer experience across different browsers