@echo off
echo === BSDO Live Streaming Setup (Node.js) ===
echo.

echo Step 1: Installing Node.js dependencies...
echo.

cd /d "%~dp0"

if exist package.json (
    echo Installing dependencies...
    npm install
) else (
    echo Error: package.json not found
    pause
    exit /b 1
)

echo.
echo Step 2: Installing FFmpeg (required for streaming)...
echo.

echo Checking for FFmpeg...
ffmpeg -version >nul 2>&1
if %errorlevel% neq 0 (
    echo FFmpeg not found. Installing via Chocolatey...
    choco install ffmpeg -y
) else (
    echo ✓ FFmpeg is already installed
)

echo.
echo Step 3: Creating HLS directory...
echo.

if not exist "public\hls" mkdir "public\hls"

echo.
echo Step 4: Updating stream URLs...
echo.

php fix_streaming_urls.php

echo.
echo Step 5: Starting streaming server...
echo.

echo Starting Node.js streaming server...
echo Press Ctrl+C to stop the server
echo.
echo RTMP URL: rtmp://localhost:1935/live
echo HLS URL: http://localhost:8080/hls/[stream_key]/index.m3u8
echo.

npm start

pause