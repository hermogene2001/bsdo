class WebRTCClient {
    constructor(options) {
        this.streamId = options.streamId;
        this.videoElement = options.videoElement;
        this.statusCallback = options.onStatusChange || (() => {});
        this.peerConnection = null;
        this.signalingUrl = options.signalingUrl || 'webrtc_server.php';
        this.roomId = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        
        this.configuration = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' },
                { urls: 'stun:stun3.l.google.com:19302' },
                { urls: 'stun:stun4.l.google.com:19302' }
            ],
            iceCandidatePoolSize: 10
        };
        
        this.init();
    }

    async init() {
        try {
            // First check if stream is actually live
            const response = await fetch(`check_stream_status.php?stream_id=${this.streamId}`);
            const status = await response.json();
            
            if (!status.is_live) {
                this.statusCallback('error', 'Stream is not live');
                return;
            }

            if (!status.has_seller) {
                this.statusCallback('error', 'Waiting for seller to start streaming...');
                // Retry after 5 seconds
                setTimeout(() => this.init(), 5000);
                return;
            }

            await this.joinRoom();
            this.startSignaling();
            
            // Start connection monitoring
            this.startConnectionMonitoring();
        } catch (error) {
            console.error('Failed to initialize WebRTC:', error);
            this.statusCallback('error', 'Failed to connect to stream. Retrying...');
            // Retry after 3 seconds
            setTimeout(() => this.init(), 3000);
        }
    }

    async startConnectionMonitoring() {
        const checkConnection = async () => {
            if (!this.isConnected || !this.roomId) return;
            
            try {
                const response = await fetch(`check_stream_status.php?stream_id=${this.streamId}`);
                const status = await response.json();
                
                if (!status.is_live || !status.has_seller) {
                    this.statusCallback('error', 'Stream has ended or seller disconnected');
                    this.cleanup();
                    return;
                }
            } catch (error) {
                console.error('Connection check failed:', error);
            }

            if (this.isConnected) {
                setTimeout(checkConnection, 5000);
            }
        };

        checkConnection();
    }

    async joinRoom() {
        try {
            const response = await fetch(this.signalingUrl, {
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
            if (!data.success) {
                throw new Error(data.error || 'Failed to join room');
            }

            this.roomId = data.room_id;
            this.createPeerConnection();
            this.statusCallback('connecting', 'Connecting to stream...');
        } catch (error) {
            console.error('Error joining room:', error);
            throw error;
        }
    }

    createPeerConnection() {
        this.peerConnection = new RTCPeerConnection(this.configuration);

        this.peerConnection.ontrack = (event) => {
            if (this.videoElement && event.streams[0]) {
                this.videoElement.srcObject = event.streams[0];
                this.isConnected = true;
                this.statusCallback('connected', 'Stream connected');
            }
        };

        this.peerConnection.onicecandidate = async (event) => {
            if (event.candidate) {
                try {
                    await this.sendSignalingMessage('candidate', event.candidate);
                } catch (error) {
                    console.error('Error sending ICE candidate:', error);
                }
            }
        };

        this.peerConnection.onconnectionstatechange = () => {
            console.log('Connection state:', this.peerConnection.connectionState);
            switch (this.peerConnection.connectionState) {
                case 'connected':
                    this.isConnected = true;
                    this.statusCallback('connected', 'Stream connected');
                    break;
                case 'disconnected':
                case 'failed':
                    this.isConnected = false;
                    this.statusCallback('error', 'Connection lost');
                    this.tryReconnect();
                    break;
                case 'closed':
                    this.isConnected = false;
                    this.statusCallback('closed', 'Stream ended');
                    break;
            }
        };
    }

    async startSignaling() {
        let lastMessageId = 0;
        
        const pollMessages = async () => {
            if (!this.roomId) return;
            
            try {
                const response = await fetch(this.signalingUrl, {
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
                    await this.handleAnswer(data);
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
            await this.sendSignalingMessage('answer', answer);
        } catch (error) {
            console.error('Error handling offer:', error);
        }
    }

    async handleAnswer(answer) {
        try {
            await this.peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
        } catch (error) {
            console.error('Error handling answer:', error);
        }
    }

    async handleCandidate(candidate) {
        try {
            await this.peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
        } catch (error) {
            console.error('Error handling ICE candidate:', error);
        }
    }

    async sendSignalingMessage(type, data) {
        try {
            await fetch(this.signalingUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'send_message',
                    room_id: this.roomId,
                    message_type: type,
                    message_data: JSON.stringify(data)
                })
            });
        } catch (error) {
            console.error('Error sending signaling message:', error);
            throw error;
        }
    }

    async tryReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            this.statusCallback('error', 'Failed to reconnect after multiple attempts');
            return;
        }

        this.reconnectAttempts++;
        this.statusCallback('reconnecting', `Reconnecting (Attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);

        try {
            if (this.peerConnection) {
                this.peerConnection.close();
            }
            await this.init();
        } catch (error) {
            console.error('Reconnection attempt failed:', error);
            setTimeout(() => this.tryReconnect(), 2000 * Math.pow(2, this.reconnectAttempts));
        }
    }

    cleanup() {
        if (this.peerConnection) {
            this.peerConnection.close();
        }
        if (this.videoElement && this.videoElement.srcObject) {
            this.videoElement.srcObject.getTracks().forEach(track => track.stop());
            this.videoElement.srcObject = null;
        }
        this.roomId = null;
    }
}