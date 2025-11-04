// LiveStream Viewer Class
class LiveStreamViewer {
    constructor(options) {
        this.streamId = options.streamId;
        this.videoElement = options.videoElement;
        this.statusElement = options.statusElement;
        this.peerConnection = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 2000; // Start with 2 seconds
        this.isConnected = false;
        this.connectionCheckInterval = null;
        this.stunServers = {
            iceServers: [
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' }
            ]
        };

        this.init();
    }

    async init() {
        try {
            await this.setupConnection();
            this.startConnectionCheck();
        } catch (error) {
            console.error('Error initializing stream:', error);
            this.updateStatus('error', 'Failed to initialize stream connection');
        }
    }

    async setupConnection() {
        try {
            this.peerConnection = new RTCPeerConnection(this.stunServers);
            
            // Handle incoming tracks
            this.peerConnection.ontrack = (event) => {
                if (this.videoElement.srcObject !== event.streams[0]) {
                    this.videoElement.srcObject = event.streams[0];
                    this.updateStatus('connected', 'Stream connected');
                    this.isConnected = true;
                }
            };

            // Handle connection state changes
            this.peerConnection.onconnectionstatechange = () => {
                this.handleConnectionStateChange();
            };

            // Handle ICE candidate events
            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.sendIceCandidate(event.candidate);
                }
            };

            // Get offer from server
            const offer = await this.getStreamOffer();
            if (!offer) {
                throw new Error('Failed to get stream offer');
            }

            await this.peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
            const answer = await this.peerConnection.createAnswer();
            await this.peerConnection.setLocalDescription(answer);

            // Send answer to server
            await this.sendAnswer(answer);

        } catch (error) {
            console.error('Connection setup error:', error);
            this.updateStatus('error', 'Connection setup failed');
            this.tryReconnect();
        }
    }

    async getStreamOffer() {
        try {
            const response = await fetch('webrtc_server.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_offer',
                    stream_id: this.streamId
                })
            });

            const data = await response.json();
            if (!data.success || !data.offer) {
                throw new Error('Invalid offer received');
            }

            return data.offer;
        } catch (error) {
            console.error('Error getting stream offer:', error);
            throw error;
        }
    }

    async sendAnswer(answer) {
        try {
            await fetch('webrtc_server.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'send_answer',
                    stream_id: this.streamId,
                    answer: JSON.stringify(answer)
                })
            });
        } catch (error) {
            console.error('Error sending answer:', error);
            throw error;
        }
    }

    async sendIceCandidate(candidate) {
        try {
            await fetch('webrtc_server.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'ice_candidate',
                    stream_id: this.streamId,
                    candidate: JSON.stringify(candidate)
                })
            });
        } catch (error) {
            console.error('Error sending ICE candidate:', error);
        }
    }

    handleConnectionStateChange() {
        const state = this.peerConnection.connectionState;
        switch (state) {
            case 'connected':
                this.updateStatus('connected', 'Stream connected');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.reconnectDelay = 2000;
                break;
            case 'disconnected':
            case 'failed':
                this.updateStatus('error', 'Stream disconnected');
                this.isConnected = false;
                this.tryReconnect();
                break;
            case 'closed':
                this.updateStatus('error', 'Stream ended');
                this.isConnected = false;
                break;
        }
    }

    async tryReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            this.updateStatus('error', 'Connection failed after multiple attempts');
            return;
        }

        this.reconnectAttempts++;
        this.updateStatus('reconnecting', `Reconnecting (Attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);

        setTimeout(async () => {
            try {
                if (this.peerConnection) {
                    this.peerConnection.close();
                }
                await this.setupConnection();
            } catch (error) {
                console.error('Reconnection attempt failed:', error);
                this.reconnectDelay *= 1.5; // Exponential backoff
                this.tryReconnect();
            }
        }, this.reconnectDelay);
    }

    startConnectionCheck() {
        // Clear any existing interval
        if (this.connectionCheckInterval) {
            clearInterval(this.connectionCheckInterval);
        }

        // Check connection status every 5 seconds
        this.connectionCheckInterval = setInterval(async () => {
            try {
                const response = await fetch('monitor_stream_connection.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'check_connection',
                        stream_id: this.streamId
                    })
                });

                const data = await response.json();
                if (!data.success || !data.is_live) {
                    this.updateStatus('error', 'Stream has ended');
                    this.cleanup();
                }
            } catch (error) {
                console.error('Connection check failed:', error);
            }
        }, 5000);
    }

    updateStatus(type, message) {
        if (this.statusElement) {
            this.statusElement.className = `stream-status ${type}`;
            this.statusElement.textContent = message;
        }
    }

    cleanup() {
        if (this.connectionCheckInterval) {
            clearInterval(this.connectionCheckInterval);
        }
        if (this.peerConnection) {
            this.peerConnection.close();
        }
        if (this.videoElement.srcObject) {
            const tracks = this.videoElement.srcObject.getTracks();
            tracks.forEach(track => track.stop());
            this.videoElement.srcObject = null;
        }
    }
}