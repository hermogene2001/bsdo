// Live Stream Chat Handler
class LiveStreamChat {
    constructor(streamId, chatContainer, messageInput, sendButton, options = {}) {
        this.streamId = streamId;
        this.chatContainer = chatContainer;
        this.messageInput = messageInput;
        this.sendButton = sendButton;
        this.lastMessageId = 0;
        this.options = {
            refreshInterval: options.refreshInterval || 2000,
            maxMessages: options.maxMessages || 200,
            isSeller: options.isSeller || false
        };

        this.init();
    }

    init() {
        this.setupEventListeners();
        this.startMessagePolling();
    }

    setupEventListeners() {
        // Send message on button click
        this.sendButton.addEventListener('click', () => this.sendMessage());

        // Send message on Enter key
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Pin message functionality for sellers
        if (this.options.isSeller) {
            this.chatContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('pin-message')) {
                    const messageId = e.target.dataset.messageId;
                    this.pinMessage(messageId);
                }
            });
        }
    }

    async sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message) return;

        try {
            const response = await fetch('handle_live_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'send_message',
                    stream_id: this.streamId,
                    message: message,
                    message_type: 'text'
                })
            });

            const data = await response.json();
            if (data.success) {
                this.messageInput.value = '';
            } else {
                console.error('Error sending message:', data.error);
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    }

    async getNewMessages() {
        try {
            const response = await fetch('handle_live_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_messages',
                    stream_id: this.streamId,
                    last_id: this.lastMessageId
                })
            });

            const data = await response.json();
            if (data.success && data.messages.length > 0) {
                this.renderNewMessages(data.messages);
                this.lastMessageId = data.messages[data.messages.length - 1].id;
            }
        } catch (error) {
            console.error('Error fetching messages:', error);
        }
    }

    renderNewMessages(messages) {
        messages.forEach(message => {
            const messageElement = this.createMessageElement(message);
            this.chatContainer.appendChild(messageElement);
        });

        // Remove old messages if exceeding max
        while (this.chatContainer.children.length > this.options.maxMessages) {
            this.chatContainer.removeChild(this.chatContainer.firstChild);
        }

        // Scroll to bottom
        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
    }

    createMessageElement(message) {
        const div = document.createElement('div');
        div.className = `chat-message ${message.is_seller ? 'seller-message' : ''} ${message.message_type}`;
        div.dataset.messageId = message.id;

        const header = document.createElement('div');
        header.className = 'message-header';
        header.innerHTML = `
            <img src="${message.profile_image || 'default-avatar.png'}" class="user-avatar">
            <span class="username">${message.username}</span>
            <span class="timestamp">${new Date(message.created_at).toLocaleTimeString()}</span>
        `;

        const content = document.createElement('div');
        content.className = 'message-content';
        content.textContent = message.message;

        div.appendChild(header);
        div.appendChild(content);

        if (this.options.isSeller && !message.is_seller) {
            const actions = document.createElement('div');
            actions.className = 'message-actions';
            actions.innerHTML = `
                <button class="pin-message" data-message-id="${message.id}">Pin</button>
                <button class="moderate-user" data-user-id="${message.user_id}">Moderate</button>
            `;
            div.appendChild(actions);
        }

        return div;
    }

    async pinMessage(messageId) {
        try {
            const response = await fetch('handle_live_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'pin_message',
                    stream_id: this.streamId,
                    message_id: messageId
                })
            });

            const data = await response.json();
            if (data.success) {
                // Update UI to show pinned message
                const messageElement = this.chatContainer.querySelector(`[data-message-id="${messageId}"]`);
                if (messageElement) {
                    messageElement.classList.add('pinned');
                }
            }
        } catch (error) {
            console.error('Error pinning message:', error);
        }
    }

    startMessagePolling() {
        setInterval(() => this.getNewMessages(), this.options.refreshInterval);
    }
}