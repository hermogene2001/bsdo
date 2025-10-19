<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camera Test - BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #000;
            color: white;
            padding: 20px;
        }
        
        #videoElement {
            width: 100%;
            max-width: 800px;
            height: 450px;
            background: #333;
            border-radius: 10px;
            margin: 20px auto;
            display: block;
        }
        
        .controls {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn {
            margin: 5px;
        }
        
        .status {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            border-radius: 5px;
        }
        
        .success {
            background-color: #28a745;
        }
        
        .error {
            background-color: #dc3545;
        }
        
        .info {
            background-color: #17a2b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Camera Access Test</h1>
        
        <div id="status" class="status info">
            Click "Start Camera" to test your camera access
        </div>
        
        <video id="videoElement" autoplay muted></video>
        
        <div class="controls">
            <button id="startBtn" class="btn btn-success">Start Camera</button>
            <button id="stopBtn" class="btn btn-danger" disabled>Stop Camera</button>
            <button id="switchBtn" class="btn btn-info" disabled>Switch Camera</button>
            <button id="muteBtn" class="btn btn-warning" disabled>Mute Audio</button>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <h5>Camera Information:</h5>
                <ul id="cameraInfo" class="list-group list-group-flush">
                    <li class="list-group-item bg-dark text-white">No camera detected</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h5>Browser Support:</h5>
                <ul id="browserInfo" class="list-group list-group-flush">
                    <li class="list-group-item bg-dark text-white">Checking...</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        let stream = null;
        let currentFacingMode = 'user';
        let isMuted = false;

        const videoElement = document.getElementById('videoElement');
        const startBtn = document.getElementById('startBtn');
        const stopBtn = document.getElementById('stopBtn');
        const switchBtn = document.getElementById('switchBtn');
        const muteBtn = document.getElementById('muteBtn');
        const status = document.getElementById('status');
        const cameraInfo = document.getElementById('cameraInfo');
        const browserInfo = document.getElementById('browserInfo');

        // Check browser support
        function checkBrowserSupport() {
            const info = [];
            
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                info.push('<li class="list-group-item bg-success text-white">✓ getUserMedia supported</li>');
            } else {
                info.push('<li class="list-group-item bg-danger text-white">✗ getUserMedia not supported</li>');
            }
            
            if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
                info.push('<li class="list-group-item bg-success text-white">✓ enumerateDevices supported</li>');
            } else {
                info.push('<li class="list-group-item bg-danger text-white">✗ enumerateDevices not supported</li>');
            }
            
            info.push(`<li class="list-group-item bg-info text-white">Browser: ${navigator.userAgent.split(' ')[0]}</li>`);
            
            browserInfo.innerHTML = info.join('');
        }

        // Get available cameras
        async function getCameras() {
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(device => device.kind === 'videoinput');
                
                const info = [];
                if (videoDevices.length === 0) {
                    info.push('<li class="list-group-item bg-warning text-white">No cameras found</li>');
                } else {
                    videoDevices.forEach((device, index) => {
                        info.push(`<li class="list-group-item bg-success text-white">✓ Camera ${index + 1}: ${device.label || 'Unknown'}</li>`);
                    });
                }
                
                cameraInfo.innerHTML = info.join('');
            } catch (error) {
                cameraInfo.innerHTML = '<li class="list-group-item bg-danger text-white">Error detecting cameras</li>';
            }
        }

        // Start camera
        async function startCamera() {
            try {
                status.innerHTML = 'Requesting camera access...';
                status.className = 'status info';
                
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { 
                        facingMode: currentFacingMode,
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: true
                });

                videoElement.srcObject = stream;
                
                startBtn.disabled = true;
                stopBtn.disabled = false;
                switchBtn.disabled = false;
                muteBtn.disabled = false;
                
                status.innerHTML = '✓ Camera started successfully!';
                status.className = 'status success';
                
                // Get camera info
                await getCameras();
                
            } catch (error) {
                console.error('Error accessing camera:', error);
                status.innerHTML = `✗ Error: ${error.message}`;
                status.className = 'status error';
                
                // Show common solutions
                let solutions = '<br><br><strong>Common Solutions:</strong><ul>';
                if (error.name === 'NotAllowedError') {
                    solutions += '<li>Allow camera access in your browser settings</li>';
                    solutions += '<li>Make sure you\'re using HTTPS (required for camera access)</li>';
                } else if (error.name === 'NotFoundError') {
                    solutions += '<li>Check if your camera is connected</li>';
                    solutions += '<li>Make sure no other application is using the camera</li>';
                } else if (error.name === 'NotSupportedError') {
                    solutions += '<li>Your browser may not support camera access</li>';
                    solutions += '<li>Try using Chrome, Firefox, or Safari</li>';
                }
                solutions += '</ul>';
                
                status.innerHTML += solutions;
            }
        }

        // Stop camera
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
                videoElement.srcObject = null;
                
                startBtn.disabled = false;
                stopBtn.disabled = true;
                switchBtn.disabled = true;
                muteBtn.disabled = true;
                
                status.innerHTML = 'Camera stopped';
                status.className = 'status info';
            }
        }

        // Switch camera
        async function switchCamera() {
            if (stream) {
                stopCamera();
                currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
                await startCamera();
            }
        }

        // Toggle mute
        function toggleMute() {
            if (stream) {
                const audioTracks = stream.getAudioTracks();
                audioTracks.forEach(track => {
                    track.enabled = isMuted;
                });
                isMuted = !isMuted;
                
                muteBtn.innerHTML = isMuted ? 'Unmute Audio' : 'Mute Audio';
                muteBtn.className = isMuted ? 'btn btn-success' : 'btn btn-warning';
            }
        }

        // Event listeners
        startBtn.addEventListener('click', startCamera);
        stopBtn.addEventListener('click', stopCamera);
        switchBtn.addEventListener('click', switchCamera);
        muteBtn.addEventListener('click', toggleMute);

        // Initialize
        checkBrowserSupport();
        getCameras();

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            stopCamera();
        });
    </script>
</body>
</html>









