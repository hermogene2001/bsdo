# BSDO Live Streaming Setup Guide

## 🚀 Quick Start

### Option 1: Node.js Streaming Server (Recommended)

1. **Install Dependencies**
   ```bash
   # Install Node.js (if not installed)
   # Download from: https://nodejs.org/

   # Install FFmpeg
   choco install ffmpeg
   ```

2. **Run Setup Script**
   ```bash
   # Run the setup script
   setup_streaming_node.bat
   ```

3. **Update Database URLs**
   ```bash
   php fix_streaming_urls.php
   ```

4. **Test Setup**
   ```bash
   php test_streaming.php
   ```

### Option 2: Nginx RTMP Server

1. **Install Nginx with RTMP**
   ```bash
   choco install nginx
   ```

2. **Configure Nginx**
   - Copy `nginx_rtmp_config.conf` to `C:\tools\nginx\conf\nginx.conf`
   - Create directory: `C:\tmp\hls`

3. **Start Nginx**
   ```bash
   C:\tools\nginx\nginx.exe
   ```

## 🎥 How to Stream

### For Sellers (Using OBS Studio)

1. **Download OBS Studio**: https://obsproject.com/

2. **Configure OBS**:
   - Open OBS Studio
   - Go to Settings → Stream
   - Service: Custom
   - Server: `rtmp://localhost:1935/live`
   - Stream Key: `[your_stream_key_from_dashboard]`

3. **Start Streaming**:
   - Click "Start Streaming" in OBS
   - Go to your seller dashboard and set stream to "Live"

### For Viewers

1. **Browse Live Streams**: Visit `live.php`
2. **Watch Stream**: Click "Join Stream" on any live stream
3. **HLS Playback**: Videos play automatically using HLS.js

## 🔧 Configuration

### Stream URLs
- **RTMP (for broadcasting)**: `rtmp://localhost:1935/live/[stream_key]`
- **HLS (for viewing)**: `http://localhost:8080/hls/[stream_key]/index.m3u8`

### Ports Used
- RTMP Server: Port 1935
- HLS Server: Port 8080

## 🐛 Troubleshooting

### Common Issues

1. **"Stream server not accessible"**
   - Make sure the Node.js server is running
   - Check if ports 1935 and 8080 are available
   - Run: `netstat -an | find "1935"` and `netstat -an | find "8080"`

2. **"FFmpeg not found"**
   ```bash
   choco install ffmpeg
   ```

3. **Database connection errors**
   - Check your `config.php` settings
   - Make sure MySQL is running

4. **OBS can't connect**
   - Verify the stream key matches exactly
   - Check that the stream is marked as "live" in database

### Test Commands

```bash
# Test RTMP connection
telnet localhost 1935

# Test HLS server
curl http://localhost:8080/health

# Check running processes
tasklist | find "node"
tasklist | find "nginx"
```

## 📁 Files Created

- `fix_streaming_urls.php` - Updates database URLs
- `streaming-server.js` - Node.js RTMP/HLS server
- `package.json` - Node.js dependencies
- `test_streaming.php` - Test script
- `setup_streaming_node.bat` - Setup script
- `nginx_rtmp_config.conf` - Nginx configuration (alternative)

## 🎯 Next Steps

1. Test the setup with the provided scripts
2. Create a test stream using OBS Studio
3. Verify playback in the web interface
4. Customize the streaming settings as needed

## 📞 Support

If you encounter issues:
1. Run `php test_streaming.php` to diagnose problems
2. Check the console output of the streaming server
3. Verify all dependencies are installed
4. Check firewall settings for ports 1935 and 8080