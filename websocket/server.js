'use strict';

/**
 * AlumGlass Real-time Chat WebSocket Server.
 *
 * Responsibilities:
 *   1. Authenticate clients against PHP sessions (via /chat/api/verify_session.php)
 *   2. Relay messages in real time (PHP API already persisted them)
 *   3. Broadcast typing indicators + read receipts
 *   4. Track per-user online presence
 *
 * The server does NOT touch the database. Message storage and
 * authorization are PHP's job — this is a dumb relay.
 *
 * Usage:
 *   WS_PORT=8080 PHP_AUTH_URL=http://localhost/chat/api/verify_session.php node server.js
 */

const WebSocket = require('ws');
const http = require('http');
const url = require('url');

const { authenticateUser } = require('./auth');

const PORT = parseInt(process.env.WS_PORT || '8080', 10);
const PHP_AUTH_URL = process.env.PHP_AUTH_URL || 'http://localhost/chat/api/verify_session.php';
const HEARTBEAT_MS = 30000;

/** Map<userId, Set<WebSocket>> */
const clients = new Map();
/** Map<userId, {status, lastSeen}> */
const presence = new Map();

const server = http.createServer((req, res) => {
    if (req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: 'ok',
            users_online: clients.size,
            sockets: [...clients.values()].reduce((n, s) => n + s.size, 0),
            uptime_seconds: Math.floor(process.uptime()),
        }));
        return;
    }
    res.writeHead(404);
    res.end();
});

const wss = new WebSocket.Server({ server });

wss.on('connection', async (ws, req) => {
    const params = url.parse(req.url, true).query;
    const token = params.token;

    let userId;
    try {
        userId = await authenticateUser(token, PHP_AUTH_URL);
    } catch (err) {
        console.error('[ws] auth error:', err.message);
        ws.close(4002, 'Auth service unavailable');
        return;
    }

    if (!userId) {
        ws.close(4001, 'Authentication failed');
        return;
    }

    if (!clients.has(userId)) clients.set(userId, new Set());
    clients.get(userId).add(ws);
    ws.userId = userId;
    ws.isAlive = true;

    updatePresence(userId, 'online');
    broadcastPresence(userId, 'online');

    console.log(`[ws] user ${userId} connected (${clients.get(userId).size} sockets, ${clients.size} users)`);

    ws.on('pong', () => { ws.isAlive = true; });

    ws.on('message', (data) => {
        let msg;
        try {
            msg = JSON.parse(data);
        } catch (err) {
            console.error('[ws] invalid JSON from user', userId);
            return;
        }
        handleMessage(ws, userId, msg);
    });

    ws.on('close', () => {
        const sockets = clients.get(userId);
        if (sockets) {
            sockets.delete(ws);
            if (sockets.size === 0) {
                clients.delete(userId);
                updatePresence(userId, 'offline');
                broadcastPresence(userId, 'offline');
            }
        }
        console.log(`[ws] user ${userId} disconnected`);
    });

    ws.on('error', (err) => {
        console.error('[ws] socket error:', err.message);
    });

    // Initial sync: connected confirmation + current presence snapshot
    send(ws, { type: 'connected', userId });
    send(ws, { type: 'presence_list', data: Object.fromEntries(presence) });
});

function handleMessage(ws, senderId, msg) {
    switch (msg.type) {
        case 'chat_message':
            relayMessage(senderId, msg);
            break;
        case 'typing':
            if (msg.conversationId) {
                relayToRecipients(msg, {
                    type: 'typing',
                    userId: senderId,
                    conversationId: msg.conversationId,
                }, senderId);
            }
            break;
        case 'stop_typing':
            if (msg.conversationId) {
                relayToRecipients(msg, {
                    type: 'stop_typing',
                    userId: senderId,
                    conversationId: msg.conversationId,
                }, senderId);
            }
            break;
        case 'read_receipt':
            if (msg.senderId) {
                sendToUser(msg.senderId, {
                    type: 'read_receipt',
                    conversationId: msg.conversationId,
                    readBy: senderId,
                    readAt: new Date().toISOString(),
                });
            }
            break;
        case 'ping':
            send(ws, { type: 'pong', ts: Date.now() });
            break;
        default:
            // ignore unknown types silently
            break;
    }
}

function relayMessage(senderId, msg) {
    const payload = {
        type: 'new_message',
        data: {
            id: msg.messageId,
            conversationId: msg.conversationId,
            senderId,
            content: msg.content,
            messageType: msg.messageType || 'text',
            filePath: msg.filePath || null,
            caption: msg.caption || null,
            replyToId: msg.replyToId || null,
            timestamp: new Date().toISOString(),
        },
    };

    if (Array.isArray(msg.memberIds) && msg.memberIds.length > 0) {
        for (const memberId of msg.memberIds) {
            if (memberId !== senderId) sendToUser(memberId, payload);
        }
        return;
    }

    if (msg.receiverId) {
        sendToUser(msg.receiverId, payload);
    }
}

function relayToRecipients(msg, payload, senderId) {
    if (Array.isArray(msg.memberIds) && msg.memberIds.length > 0) {
        for (const memberId of msg.memberIds) {
            if (memberId !== senderId) sendToUser(memberId, payload);
        }
    } else if (msg.receiverId) {
        sendToUser(msg.receiverId, payload);
    }
}

function sendToUser(userId, data) {
    const sockets = clients.get(userId);
    if (!sockets) return;
    const json = JSON.stringify(data);
    for (const ws of sockets) {
        if (ws.readyState === WebSocket.OPEN) ws.send(json);
    }
}

function send(ws, data) {
    if (ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify(data));
    }
}

function updatePresence(userId, status) {
    presence.set(userId, { status, lastSeen: new Date().toISOString() });
}

function broadcastPresence(userId, status) {
    const payload = JSON.stringify({
        type: 'presence',
        userId,
        status,
        lastSeen: new Date().toISOString(),
    });
    for (const [uid, sockets] of clients.entries()) {
        if (uid === userId) continue;
        for (const ws of sockets) {
            if (ws.readyState === WebSocket.OPEN) ws.send(payload);
        }
    }
}

// Heartbeat: drop sockets that stopped responding to pings.
const heartbeat = setInterval(() => {
    wss.clients.forEach((ws) => {
        if (ws.isAlive === false) return ws.terminate();
        ws.isAlive = false;
        try { ws.ping(); } catch (_) { /* ignore */ }
    });
}, HEARTBEAT_MS);

wss.on('close', () => clearInterval(heartbeat));

server.listen(PORT, () => {
    console.log(`AlumGlass WebSocket server listening on :${PORT}`);
    console.log(`auth → ${PHP_AUTH_URL}`);
});

function shutdown(signal) {
    console.log(`[ws] received ${signal}, shutting down`);
    clearInterval(heartbeat);
    wss.clients.forEach((ws) => ws.close(1001, 'server shutdown'));
    server.close(() => process.exit(0));
    setTimeout(() => process.exit(1), 5000).unref();
}
process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
