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
            // Join the stream room first
            await this.joinStream();
            
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

            // Start polling for signaling messages
            this.startSignaling();

        } catch (error) {
            console.error('Connection setup error:', error);
            this.updateStatus('error', 'Connection setup failed');
            this.tryReconnect();
        }
    }

    async joinStream() {
        try {
            // First join the room
            const response = await fetch('webrtc_server.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'join_room',
                    stream_id: this.streamId
                })
            });

            const data = await response.json();
            if (!data.success || !data.room_id) {
                throw new Error('Failed to join stream room');
            }

            this.roomId = data.room_id;
            return data.room_id;
        } catch (error) {
            console.error('Error joining stream:', error);
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
        if (!this.roomId) return;
        
        try {
            await fetch('webrtc_server.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'send_candidate',
                    room_id: this.roomId,
                    candidate: JSON.stringify(candidate)
                })
            });
        } catch (error) {
            console.error('Error sending ICE candidate:', error);
        }
    }
    
    async sendAnswer(answer) {
        if (!this.roomId) return;
        
        try {
            await fetch('webrtc_server.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'send_answer',
                    room_id: this.roomId,
                    answer: JSON.stringify(answer)
                })
            });
        } catch (error) {
            console.error('Error sending answer:', error);
        }
    }
    
    startSignaling() {
        if (!this.roomId) return;
        
        let lastMessageId = 0;
        
        const pollMessages = async () => {
            if (!this.roomId) return;
            
            try {
                const response = await fetch('webrtc_server.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_messages',
                        room_id: this.roomId,
                        last_id: lastMessageId
                    })
                });

                const data = await response.json();
                if (data.success && data.messages) {
                    for (const message of data.messages) {
                        await this.handleSignalingMessage(message);
                        lastMessageId = Math.max(lastMessageId, message.id);
                    }
                }
            } catch (error) {
                console.error('Error polling messages:', error);
            }

            if (this.roomId) {
                setTimeout(pollMessages, 1000);
            }
        };

        pollMessages();
    }
    
    async handleSignalingMessage(message) {
        try {
            const data = JSON.parse(message.message_data);
            
            switch (message.message_type) {
                case 'offer':
                    await this.handleOffer(data);
                    break;
                case 'answer':
                    // We don't expect to receive answers as client
                    break;
                case 'candidate':
                    await this.handleCandidate(data);
                    break;
            }
        } catch (error) {
            console.error('Error handling signaling message:', error);
        }
    }
    
    async handleOffer(offer) {
        try {
            await this.peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
            const answer = await this.peerConnection.createAnswer();
            await this.peerConnection.setLocalDescription(answer);
            await this.sendAnswer(answer);
        } catch (error) {
            console.error('Error handling offer:', error);
        }
    }
    
    async handleCandidate(candidate) {
        try {
            await this.peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
        } catch (error) {
            console.error('Error handling ICE candidate:', error);
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