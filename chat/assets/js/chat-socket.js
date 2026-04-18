/**
 * WebSocket connection manager with exponential back-off reconnect,
 * ping/pong keep-alive, and a simple event-emitter API.
 *
 * Usage:
 *   const socket = new ChatSocket('ws://host:8080', sessionToken);
 *   socket.on('new_message', data => ...);
 *   socket.connect();
 */

export class ChatSocket {
    constructor(wsUrl, sessionToken) {
        this.wsUrl = wsUrl;
        this.token = sessionToken;
        this.ws = null;
        this.listeners = new Map();
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.baseReconnectDelay = 1000;
        this.pingInterval = null;
        this.connected = false;
        this.intentionallyClosed = false;
    }

    connect() {
        this.intentionallyClosed = false;
        let ws;
        try {
            ws = new WebSocket(`${this.wsUrl}?token=${encodeURIComponent(this.token)}`);
        } catch (err) {
            console.error('[ws] construct failed:', err);
            this.scheduleReconnect();
            return;
        }

        this.ws = ws;

        ws.addEventListener('open', () => {
            this.connected = true;
            this.reconnectAttempts = 0;
            this.startPing();
            this.emit('connected');
        });

        ws.addEventListener('message', (event) => {
            try {
                const msg = JSON.parse(event.data);
                this.emit(msg.type, msg.data !== undefined ? msg.data : msg);
                this.emit('*', msg);
            } catch (err) {
                console.error('[ws] bad frame:', err);
            }
        });

        ws.addEventListener('close', (event) => {
            this.connected = false;
            this.stopPing();
            this.emit('disconnected', event.code);
            if (!this.intentionallyClosed && event.code !== 4001) {
                this.scheduleReconnect();
            } else if (event.code === 4001) {
                this.emit('auth_failed');
            }
        });

        ws.addEventListener('error', (err) => {
            this.emit('error', err);
        });
    }

    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            this.emit('fallback_to_polling');
            return;
        }
        this.reconnectAttempts++;
        const delay = Math.min(this.baseReconnectDelay * Math.pow(1.5, this.reconnectAttempts), 30000);
        setTimeout(() => this.connect(), delay);
    }

    send(type, payload = {}) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({ type, ...payload }));
            return true;
        }
        return false;
    }

    on(event, callback) {
        if (!this.listeners.has(event)) this.listeners.set(event, []);
        this.listeners.get(event).push(callback);
    }

    off(event, callback) {
        const arr = this.listeners.get(event);
        if (!arr) return;
        const idx = arr.indexOf(callback);
        if (idx >= 0) arr.splice(idx, 1);
    }

    emit(event, data) {
        const cbs = this.listeners.get(event);
        if (cbs) cbs.slice().forEach((cb) => {
            try { cb(data); } catch (err) { console.error(`[ws] listener "${event}" threw:`, err); }
        });
    }

    startPing() {
        this.stopPing();
        this.pingInterval = setInterval(() => this.send('ping'), 25000);
    }

    stopPing() {
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }
    }

    sendTyping(conversationId, receiverId, memberIds) {
        this.send('typing', { conversationId, receiverId, memberIds });
    }

    sendStopTyping(conversationId, receiverId, memberIds) {
        this.send('stop_typing', { conversationId, receiverId, memberIds });
    }

    sendReadReceipt(conversationId, senderId) {
        this.send('read_receipt', { conversationId, senderId });
    }

    notifyNewMessage({ messageId, conversationId, receiverId, memberIds, content, messageType, filePath, caption, replyToId }) {
        this.send('chat_message', {
            messageId, conversationId, receiverId, memberIds, content, messageType, filePath, caption, replyToId,
        });
    }

    disconnect() {
        this.intentionallyClosed = true;
        this.stopPing();
        if (this.ws) this.ws.close(1000, 'client');
    }
}
