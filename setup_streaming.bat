@echo off
echo === BSDO Live Streaming Setup ===
echo.

echo Step 1: Installing Nginx with RTMP module...
echo.

echo Installing Nginx...
choco install nginx -y

echo.
echo Step 2: Configuring Nginx...
echo.

echo Creating HLS directory...
if not exist "C:\tmp\hls" mkdir "C:\tmp\hls"

echo.
echo Copying nginx configuration...
copy nginx_rtmp_config.conf "C:\tools\nginx\conf\nginx.conf"

echo.
echo Step 3: Starting Nginx service...
echo.

echo Starting Nginx...
start "" "C:\tools\nginx\nginx.exe"

echo.
echo Step 4: Testing setup...
echo.

timeout /t 3 /nobreak > nul

echo Checking if Nginx is running...
netstat -an | find "1935" > nul
if %errorlevel% equ 0 (
    echo ✓ RTMP server is running on port 1935
) else (
    echo ✗ RTMP server not detected
)

netstat -an | find "8080" > nul
if %errorlevel% equ 0 (
    echo ✓ HLS server is running on port 8080
) else (
    echo ✗ HLS server not detected
)

echo.
echo === Setup Complete ===
echo.
echo Next steps:
echo 1. Run: php fix_streaming_urls.php
echo 2. Open OBS Studio
echo 3. Set RTMP URL: rtmp://localhost:1935/live
echo 4. Set Stream Key: [your_stream_key]
echo 5. Start streaming!
echo.
echo Test HLS playback: http://localhost:8080/hls/[stream_key].m3u8
echo.

pause