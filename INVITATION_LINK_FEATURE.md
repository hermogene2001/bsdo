# Live Stream Invitation Link Feature

## Overview
This document describes the invitation link feature for live streaming, which allows sellers to generate unique links that clients can use to access their live streams.

## How It Works

### For Sellers
1. When viewing a live stream, sellers can generate an invitation link using the "Generate Invite Link" button
2. Sellers can set an expiration time for the link (1 hour to 7 days)
3. The generated link can be copied and shared with clients
4. **IMPORTANT**: Once an invitation link is generated, it cannot be regenerated even if it expires. Sellers must create a new stream for a new invitation link.

### For Clients
1. Clients receive an invitation link from a seller
2. When they click the link, they are taken directly to the live stream (not a list of streams)
3. If the link has expired, they will see an error message

## Technical Implementation

### Database Schema
Two new columns were added to the `live_streams` table:
- `invite_code` (VARCHAR 128) - Unique code for the invitation link
- `invite_expires_at` (DATETIME) - Expiration timestamp for the link

### Seller Interface
The seller interface in [seller/live_stream.php](file:///c%3A/xampp/htdocs/bsdo/seller/live_stream.php) includes:
- A form to generate invitation links with customizable expiration times
- Display of the generated link with copy functionality
- **Restriction**: Sellers cannot regenerate invitation links if the first one has expired

### Client Interface
The client interface in [watch_stream.php](file:///c%3A/xampp/htdocs/bsdo/watch_stream.php) handles:
- Resolving invitation codes to stream IDs
- Checking link expiration
- **Direct access**: Clients are directed straight to the specific stream, not a list

## Security Features
- Unique invitation codes are generated using cryptographically secure random functions
- Links can be set to expire after a specified time period
- Links cannot be regenerated once created, preventing abuse
- Only valid, non-expired links allow access to streams

## Testing
The feature has been tested for:
- Database schema updates
- Link generation and resolution
- Expiration handling
- Link regeneration restriction

## Usage Instructions

### Generating an Invitation Link
1. Go to the seller dashboard and navigate to "Live Stream"
2. Start or select a live stream
3. Click "Generate Invite Link"
4. Select an expiration time from the dropdown
5. Click the "Generate Invite Link" button
6. Copy the generated link and share it with clients

### Using an Invitation Link
1. Receive an invitation link from a seller
2. Click the link to access the live stream directly
3. If the link has expired, you will see an error message

### Important Notes
- **Once generated, invitation links cannot be regenerated even if they expire**
- If you need a new link, you must create a new stream
- This restriction prevents abuse of the invitation system

## Future Enhancements
Potential improvements for future versions:
- QR code generation for invitation links
- Analytics for tracking link usage
- Notification system for expired links