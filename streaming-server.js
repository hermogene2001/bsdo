const NodeMediaServer = require('node-media-server');
const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');

console.log('=== BSDO Live Streaming Server ===');

// Create HLS output directory
const hlsPath = path.join(__dirname, 'public', 'hls');
if (!fs.existsSync(hlsPath)) {
    fs.mkdirSync(hlsPath, { recursive: true });
}

// RTMP configuration
const config = {
    rtmp: {
        port: 1935,
        chunk_size: 60000,
        gop_cache: true,
        ping: 30,
        ping_timeout: 60
    },
    http: {
        port: 8080,
        allow_origin: '*'
    },
    trans: {
        ffmpeg: 'ffmpeg', // Make sure ffmpeg is installed
        tasks: [
            {
                app: 'live',
                hls: true,
                hlsFlags: '[hls_time=2:hls_list_size=3:hls_flags=delete_segments]',
                hlsKeep: true, // Keep HLS segments on disk
                dash: false,
                mp4: false,
                hlsPath: hlsPath
            }
        ]
    }
};

const nms = new NodeMediaServer(config);

// Express server for HLS files
const app = express();
app.use(cors());
app.use('/hls', express.static(hlsPath));

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'running',
        rtmp_port: 1935,
        hls_port: 8080,
        timestamp: new Date().toISOString()
    });
});

// Start servers
nms.run();
app.listen(8080, () => {
    console.log('✓ HLS server running on http://localhost:8080');
    console.log('✓ RTMP server running on rtmp://localhost:1935');
    console.log('✓ HLS files served from /hls');
    console.log('');
    console.log('=== Server Ready ===');
    console.log('RTMP URL: rtmp://localhost:1935/live');
    console.log('HLS URL: http://localhost:8080/hls/[stream_key]/index.m3u8');
    console.log('Health check: http://localhost:8080/health');
});

nms.on('preConnect', (id, args) => {
    console.log('[NodeEvent on preConnect]', `id=${id} args=${JSON.stringify(args)}`);
});

nms.on('postConnect', (id, args) => {
    console.log('[NodeEvent on postConnect]', `id=${id} args=${JSON.stringify(args)}`);
});

nms.on('doneConnect', (id, args) => {
    console.log('[NodeEvent on doneConnect]', `id=${id} args=${JSON.stringify(args)}`);
});

nms.on('prePublish', (id, StreamPath, args) => {
    console.log('[NodeEvent on prePublish]', `id=${id} StreamPath=${StreamPath} args=${JSON.stringify(args)}`);
});

nms.on('postPublish', (id, StreamPath, args) => {
    console.log('[NodeEvent on postPublish]', `id=${id} StreamPath=${StreamPath} args=${JSON.stringify(args)}`);
    console.log(`✓ Stream started: ${StreamPath}`);
});

nms.on('donePublish', (id, StreamPath, args) => {
    console.log('[NodeEvent on donePublish]', `id=${id} StreamPath=${StreamPath} args=${JSON.stringify(args)}`);
    console.log(`✓ Stream ended: ${StreamPath}`);
});

nms.on('prePlay', (id, StreamPath, args) => {
    console.log('[NodeEvent on prePlay]', `id=${id} StreamPath=${StreamPath} args=${JSON.stringify(args)}`);
});

nms.on('postPlay', (id, StreamPath, args) => {
    console.log('[NodeEvent on postPlay]', `id=${id} StreamPath=${StreamPath} args=${JSON.stringify(args)}`);
});

nms.on('donePlay', (id, StreamPath, args) => {
    console.log('[NodeEvent on donePlay]', `id=${id} StreamPath=${StreamPath} args=${JSON.stringify(args)}`);
});