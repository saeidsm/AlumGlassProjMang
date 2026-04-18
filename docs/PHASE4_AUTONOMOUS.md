# Phase 4 — UI/UX Overhaul: Real-time Chat, Mobile, Forms, Design System
# فاز ۴ — بازسازی کامل تجربه کاربری

> **حالت:** خودکار — بدون توقف بین زیرفازها
> **شروع از:** `main` (v1.0.0)
> **خروجی:** v2.0.0
> **اولویت اجرا:** 4A (Chat) → 4B (Mobile) → 4C (Forms) → 4D (Design System)

---

## ⚠️ CRITICAL INSTRUCTIONS

1. Execute 4A → 4B → 4C → 4D sequentially. No approval needed.
2. Each sub-phase: branch → work → commit → merge to main.
3. **4A (Chat) is a full rewrite** — create new files, don't patch the old 2500-line monolith.
4. **Backward compatibility**: old URLs must still work (redirect if needed).
5. After all sub-phases: update docs, tag v2.0.0, create final report.
6. PHP lint every changed PHP file. If PHP unavailable, verify syntax carefully.
7. Run `node --check` on all JS files if Node.js is available.

---

## Architecture Decisions

### WebSocket Server: Node.js + ws library
- **Why Node.js**: Native WebSocket support, event-driven, perfect for real-time chat
- **Why not Ratchet/Swoole**: cPanel hosting limitations, Node.js is simpler to deploy
- **Architecture**: PHP handles auth + storage → Node.js handles real-time relay
- **Fallback**: Long-polling for environments where WebSocket is unavailable

### Frontend approach: Vanilla JS modules (no React/Vue)
- **Why**: Consistent with existing PHP codebase, no build step required
- **How**: ES6 modules with `<script type="module">`, CSS custom properties, Web Components where useful

---

## 4A — Real-time WebSocket Chat System
> **Branch:** `claude/phase-4a-realtime-chat`
> **This is a FULL REWRITE of the messaging system.**

### New file structure:

```
chat/                              # New chat module
├── index.php                      # Chat page (replaces messages.php)
├── assets/
│   ├── css/
│   │   └── chat.css               # Chat-specific styles
│   └── js/
│       ├── chat-app.js            # Main chat application (ES6 module)
│       ├── chat-socket.js         # WebSocket connection manager
│       ├── chat-ui.js             # UI rendering functions
│       ├── chat-notifications.js  # Browser notifications
│       └── chat-search.js         # Message search
├── api/
│   ├── conversations.php          # GET: list conversations, POST: create group
│   ├── messages.php               # GET: load messages, POST: send message
│   ├── search.php                 # GET: search messages
│   ├── upload.php                 # POST: file/audio upload
│   ├── read.php                   # POST: mark as read
│   └── contacts.php               # GET: contact list with online status
│
websocket/                          # WebSocket server (Node.js)
├── package.json
├── server.js                       # WebSocket server
├── auth.js                         # PHP session verification
└── ecosystem.config.js             # PM2 config for production
```

### Database changes:

```sql
-- New tables for group chat and enhanced messaging

CREATE TABLE IF NOT EXISTS `conversations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('direct', 'group', 'channel') NOT NULL DEFAULT 'direct',
  `name` VARCHAR(100) DEFAULT NULL,           -- Group/channel name (null for direct)
  `avatar_path` VARCHAR(255) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_type (type),
  INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_members` (
  `conversation_id` INT UNSIGNED NOT NULL,
  `user_id` INT NOT NULL,
  `role` ENUM('admin', 'member') DEFAULT 'member',
  `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_read_at` DATETIME DEFAULT NULL,
  `is_muted` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (conversation_id, user_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhance existing messages table
ALTER TABLE `messages` 
  ADD COLUMN `conversation_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
  ADD COLUMN `reply_to_id` INT UNSIGNED DEFAULT NULL AFTER `is_deleted`,
  ADD COLUMN `reactions` JSON DEFAULT NULL AFTER `reply_to_id`,
  ADD INDEX idx_conversation (conversation_id, timestamp),
  ADD INDEX idx_reply (reply_to_id);

-- User online status tracking
CREATE TABLE IF NOT EXISTS `user_presence` (
  `user_id` INT PRIMARY KEY,
  `status` ENUM('online', 'away', 'offline') DEFAULT 'offline',
  `last_seen` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `socket_id` VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Create migration script: `scripts/migrations/004_chat_tables.sql`

### WebSocket Server (`websocket/server.js`):

```javascript
/**
 * AlumGlass Real-time Chat Server
 * 
 * Lightweight WebSocket server that:
 * 1. Authenticates users via PHP session token
 * 2. Relays messages in real-time
 * 3. Tracks online presence
 * 4. Handles typing indicators
 * 
 * Does NOT handle message storage (PHP API does that).
 * 
 * Usage: node server.js
 * Production: pm2 start ecosystem.config.js
 */

const WebSocket = require('ws');
const http = require('http');
const url = require('url');
const crypto = require('crypto');

const PORT = process.env.WS_PORT || 8080;
const PHP_AUTH_URL = process.env.PHP_AUTH_URL || 'http://localhost/chat/api/verify_session.php';

// Connected clients: Map<userId, Set<WebSocket>>
const clients = new Map();
// User presence: Map<userId, {status, lastSeen}>
const presence = new Map();

const server = http.createServer((req, res) => {
    // Health check endpoint
    if (req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ 
            status: 'ok', 
            connections: clients.size,
            uptime: process.uptime() 
        }));
        return;
    }
    res.writeHead(404);
    res.end();
});

const wss = new WebSocket.Server({ server });

wss.on('connection', async (ws, req) => {
    const params = url.parse(req.url, true).query;
    const sessionToken = params.token;
    
    // Authenticate via PHP
    let userId;
    try {
        userId = await authenticateUser(sessionToken);
        if (!userId) {
            ws.close(4001, 'Authentication failed');
            return;
        }
    } catch (err) {
        console.error('Auth error:', err.message);
        ws.close(4002, 'Auth service unavailable');
        return;
    }
    
    // Register client
    if (!clients.has(userId)) {
        clients.set(userId, new Set());
    }
    clients.get(userId).add(ws);
    ws.userId = userId;
    
    // Update presence
    updatePresence(userId, 'online');
    broadcastPresence(userId, 'online');
    
    console.log(`User ${userId} connected (${clients.get(userId).size} sessions)`);
    
    // Handle messages
    ws.on('message', (data) => {
        try {
            const msg = JSON.parse(data);
            handleMessage(ws, userId, msg);
        } catch (err) {
            console.error('Invalid message:', err.message);
        }
    });
    
    // Handle disconnect
    ws.on('close', () => {
        const userSockets = clients.get(userId);
        if (userSockets) {
            userSockets.delete(ws);
            if (userSockets.size === 0) {
                clients.delete(userId);
                updatePresence(userId, 'offline');
                broadcastPresence(userId, 'offline');
            }
        }
        console.log(`User ${userId} disconnected`);
    });
    
    // Send initial presence data
    ws.send(JSON.stringify({
        type: 'presence_list',
        data: Object.fromEntries(presence)
    }));
});

function handleMessage(ws, senderId, msg) {
    switch (msg.type) {
        case 'chat_message':
            // Relay to recipient(s) — PHP API already saved it
            relayMessage(senderId, msg);
            break;
            
        case 'typing':
            // Broadcast typing indicator to conversation members
            broadcastToConversation(msg.conversationId, {
                type: 'typing',
                userId: senderId,
                conversationId: msg.conversationId
            }, senderId);
            break;
            
        case 'stop_typing':
            broadcastToConversation(msg.conversationId, {
                type: 'stop_typing',
                userId: senderId,
                conversationId: msg.conversationId
            }, senderId);
            break;
            
        case 'read_receipt':
            // Notify sender that their message was read
            sendToUser(msg.senderId, {
                type: 'read_receipt',
                conversationId: msg.conversationId,
                readBy: senderId,
                readAt: new Date().toISOString()
            });
            break;
            
        case 'ping':
            ws.send(JSON.stringify({ type: 'pong' }));
            break;
    }
}

function relayMessage(senderId, msg) {
    const payload = {
        type: 'new_message',
        data: {
            id: msg.messageId,
            conversationId: msg.conversationId,
            senderId: senderId,
            content: msg.content,
            messageType: msg.messageType || 'text',
            filePath: msg.filePath || null,
            caption: msg.caption || null,
            timestamp: new Date().toISOString()
        }
    };
    
    // For direct messages, send to receiver
    if (msg.receiverId) {
        sendToUser(msg.receiverId, payload);
    }
    
    // For group messages, broadcast to all members
    if (msg.memberIds && Array.isArray(msg.memberIds)) {
        msg.memberIds.forEach(memberId => {
            if (memberId !== senderId) {
                sendToUser(memberId, payload);
            }
        });
    }
}

function sendToUser(userId, data) {
    const sockets = clients.get(userId);
    if (sockets) {
        const json = JSON.stringify(data);
        sockets.forEach(ws => {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(json);
            }
        });
    }
}

function broadcastToConversation(conversationId, data, excludeUserId) {
    // In production, query conversation_members from DB
    // For now, broadcast to all connected users
    const json = JSON.stringify(data);
    clients.forEach((sockets, userId) => {
        if (userId !== excludeUserId) {
            sockets.forEach(ws => {
                if (ws.readyState === WebSocket.OPEN) {
                    ws.send(json);
                }
            });
        }
    });
}

function updatePresence(userId, status) {
    presence.set(userId, { status, lastSeen: new Date().toISOString() });
}

function broadcastPresence(userId, status) {
    const data = JSON.stringify({
        type: 'presence',
        userId,
        status,
        lastSeen: new Date().toISOString()
    });
    
    clients.forEach((sockets, uid) => {
        if (uid !== userId) {
            sockets.forEach(ws => {
                if (ws.readyState === WebSocket.OPEN) {
                    ws.send(data);
                }
            });
        }
    });
}

async function authenticateUser(token) {
    // Verify session token against PHP backend
    try {
        const resp = await fetch(PHP_AUTH_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token })
        });
        const data = await resp.json();
        return data.valid ? data.userId : null;
    } catch (err) {
        // Fallback: decode token directly (if using JWT-like tokens)
        console.error('PHP auth unavailable:', err.message);
        return null;
    }
}

server.listen(PORT, () => {
    console.log(`AlumGlass WebSocket server running on port ${PORT}`);
});
```

### `websocket/package.json`:
```json
{
  "name": "alumglass-websocket",
  "version": "1.0.0",
  "description": "AlumGlass Real-time Chat WebSocket Server",
  "main": "server.js",
  "scripts": {
    "start": "node server.js",
    "dev": "node --watch server.js"
  },
  "dependencies": {
    "ws": "^8.16.0"
  }
}
```

### `websocket/ecosystem.config.js` (PM2):
```javascript
module.exports = {
  apps: [{
    name: 'alumglass-ws',
    script: 'server.js',
    instances: 1,
    env: {
      WS_PORT: 8080,
      PHP_AUTH_URL: 'http://localhost/chat/api/verify_session.php',
      NODE_ENV: 'production'
    },
    max_memory_restart: '150M',
    log_file: '../logs/websocket.log',
    error_file: '../logs/websocket-error.log'
  }]
};
```

### Chat PHP API (`chat/api/`):

**`chat/api/verify_session.php`** — Called by WebSocket server to verify PHP sessions:
```php
<?php
// Verifies a session token for WebSocket authentication
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

if (empty($token)) {
    echo json_encode(['valid' => false]);
    exit;
}

// Token is the PHP session ID — validate it
session_id($token);
session_start();

if (!empty($_SESSION['user_id'])) {
    echo json_encode([
        'valid' => true,
        'userId' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? ''
    ]);
} else {
    echo json_encode(['valid' => false]);
}
```

**`chat/api/conversations.php`** — List/create conversations:
```php
<?php
// GET: list user's conversations (with last message + unread count)
// POST: create new group conversation
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) { http_response_code(401); exit; }

$pdo = getCommonDBConnection();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch conversations with last message and unread count
    $stmt = $pdo->prepare("
        SELECT 
            c.id, c.type, c.name, c.avatar_path,
            (SELECT message_content FROM messages WHERE conversation_id = c.id ORDER BY timestamp DESC LIMIT 1) as last_message,
            (SELECT timestamp FROM messages WHERE conversation_id = c.id ORDER BY timestamp DESC LIMIT 1) as last_message_time,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.timestamp > COALESCE(cm.last_read_at, '1970-01-01') AND m.sender_id != ?) as unread_count,
            -- For direct chats, get the other user's info
            CASE WHEN c.type = 'direct' THEN (
                SELECT CONCAT(u.first_name, ' ', u.last_name) FROM conversation_members cm2 
                JOIN users u ON cm2.user_id = u.id 
                WHERE cm2.conversation_id = c.id AND cm2.user_id != ? LIMIT 1
            ) ELSE c.name END as display_name,
            CASE WHEN c.type = 'direct' THEN (
                SELECT u.avatar_path FROM conversation_members cm2 
                JOIN users u ON cm2.user_id = u.id 
                WHERE cm2.conversation_id = c.id AND cm2.user_id != ? LIMIT 1
            ) ELSE c.avatar_path END as display_avatar
        FROM conversations c
        JOIN conversation_members cm ON c.id = cm.conversation_id AND cm.user_id = ?
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    echo json_encode(['success' => true, 'conversations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    // Create group conversation
    $name = trim($_POST['name'] ?? '');
    $memberIds = json_decode($_POST['members'] ?? '[]', true);
    
    if (empty($name) || empty($memberIds)) {
        echo json_encode(['success' => false, 'message' => 'Name and members required']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO conversations (type, name, created_by) VALUES ('group', ?, ?)");
        $stmt->execute([$name, $userId]);
        $convId = $pdo->lastInsertId();
        
        // Add creator as admin
        $stmt = $pdo->prepare("INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$convId, $userId]);
        
        // Add members
        $stmt = $pdo->prepare("INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, 'member')");
        foreach ($memberIds as $memberId) {
            $stmt->execute([$convId, (int)$memberId]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'conversationId' => $convId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to create group']);
    }
}
```

**`chat/api/messages.php`** — Send/receive messages:
```php
<?php
// GET: load messages for a conversation (with pagination)
// POST: send a new message
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) { http_response_code(401); exit; }

$pdo = getCommonDBConnection();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $convId = (int)($_GET['conversation_id'] ?? 0);
    $before = $_GET['before'] ?? null; // For infinite scroll
    $limit = min(50, max(10, (int)($_GET['limit'] ?? 30)));
    
    // Verify user is member
    $stmt = $pdo->prepare("SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$convId, $userId]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Not a member']);
        exit;
    }
    
    $sql = "SELECT m.*, u.first_name, u.last_name, u.avatar_path,
                   rm.message_content as reply_content, ru.first_name as reply_fname
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            LEFT JOIN messages rm ON m.reply_to_id = rm.id
            LEFT JOIN users ru ON rm.sender_id = ru.id
            WHERE m.conversation_id = ? AND m.is_deleted = 0";
    $params = [$convId];
    
    if ($before) {
        $sql .= " AND m.id < ?";
        $params[] = (int)$before;
    }
    
    $sql .= " ORDER BY m.id DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $convId = (int)($_POST['conversation_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $replyToId = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;
    $messageType = $_POST['message_type'] ?? 'text';
    
    // Handle file upload if present
    $filePath = null;
    $caption = trim($_POST['caption'] ?? '');
    if (!empty($_FILES['file'])) {
        // Validate and save file
        $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','zip','mp3','mp4','wav','ogg'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'File type not allowed']);
            exit;
        }
        $filename = uniqid('chat_') . '.' . $ext;
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename);
        $filePath = '/chat/uploads/' . $filename;
        $messageType = in_array($ext, ['jpg','jpeg','png','gif']) ? 'image' : 
                       (in_array($ext, ['mp3','wav','ogg']) ? 'audio' : 
                       (in_array($ext, ['mp4']) ? 'video' : 'file'));
    }
    
    if (empty($content) && empty($filePath)) {
        echo json_encode(['success' => false, 'message' => 'Message content or file required']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, file_path, caption, reply_to_id, timestamp) VALUES (?, ?, 0, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$convId, $userId, $content, $messageType, $filePath, $caption ?: null, $replyToId]);
    $msgId = $pdo->lastInsertId();
    
    // Get full message data for response
    $stmt = $pdo->prepare("SELECT m.*, u.first_name, u.last_name, u.avatar_path FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.id = ?");
    $stmt->execute([$msgId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update conversation timestamp
    $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);
    
    echo json_encode(['success' => true, 'message' => $message]);
}
```

**`chat/api/search.php`** — Search messages:
```php
<?php
// GET: search messages across user's conversations
header('Content-Type: application/json');
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) { http_response_code(401); exit; }

$pdo = getCommonDBConnection();
$userId = $_SESSION['user_id'];
$query = trim($_GET['q'] ?? '');
$convId = !empty($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit;
}

$sql = "SELECT m.id, m.conversation_id, m.message_content, m.timestamp, m.message_type,
               u.first_name, u.last_name,
               c.name as conv_name, c.type as conv_type
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        JOIN conversation_members cm ON m.conversation_id = cm.conversation_id AND cm.user_id = ?
        JOIN conversations c ON m.conversation_id = c.id
        WHERE m.message_content LIKE ? AND m.is_deleted = 0";
$params = [$userId, "%$query%"];

if ($convId) {
    $sql .= " AND m.conversation_id = ?";
    $params[] = $convId;
}

$sql .= " ORDER BY m.timestamp DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
```

### Chat Frontend (`chat/assets/js/`):

**`chat/assets/js/chat-socket.js`** — WebSocket connection manager:
```javascript
/**
 * WebSocket connection manager with auto-reconnect and fallback
 */
export class ChatSocket {
    constructor(wsUrl, sessionToken) {
        this.wsUrl = wsUrl;
        this.token = sessionToken;
        this.ws = null;
        this.listeners = new Map();
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.reconnectDelay = 1000;
        this.pingInterval = null;
        this.connected = false;
    }
    
    connect() {
        try {
            this.ws = new WebSocket(`${this.wsUrl}?token=${this.token}`);
            
            this.ws.onopen = () => {
                this.connected = true;
                this.reconnectAttempts = 0;
                this.reconnectDelay = 1000;
                this.startPing();
                this.emit('connected');
                console.log('[WS] Connected');
            };
            
            this.ws.onmessage = (event) => {
                try {
                    const msg = JSON.parse(event.data);
                    this.emit(msg.type, msg.data || msg);
                } catch (e) {
                    console.error('[WS] Parse error:', e);
                }
            };
            
            this.ws.onclose = (event) => {
                this.connected = false;
                this.stopPing();
                if (event.code !== 4001) { // Not auth failure
                    this.scheduleReconnect();
                }
                this.emit('disconnected', event.code);
            };
            
            this.ws.onerror = (err) => {
                console.error('[WS] Error:', err);
                this.emit('error', err);
            };
        } catch (err) {
            console.error('[WS] Connection failed:', err);
            this.scheduleReconnect();
        }
    }
    
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log('[WS] Max reconnect attempts reached, falling back to polling');
            this.emit('fallback_to_polling');
            return;
        }
        this.reconnectAttempts++;
        const delay = Math.min(this.reconnectDelay * Math.pow(1.5, this.reconnectAttempts), 30000);
        console.log(`[WS] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
        setTimeout(() => this.connect(), delay);
    }
    
    send(type, data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({ type, ...data }));
        }
    }
    
    on(event, callback) {
        if (!this.listeners.has(event)) this.listeners.set(event, []);
        this.listeners.get(event).push(callback);
    }
    
    emit(event, data) {
        const cbs = this.listeners.get(event);
        if (cbs) cbs.forEach(cb => cb(data));
    }
    
    startPing() {
        this.pingInterval = setInterval(() => {
            this.send('ping');
        }, 25000);
    }
    
    stopPing() {
        if (this.pingInterval) clearInterval(this.pingInterval);
    }
    
    sendTyping(conversationId) {
        this.send('typing', { conversationId });
    }
    
    sendStopTyping(conversationId) {
        this.send('stop_typing', { conversationId });
    }
    
    sendReadReceipt(conversationId, senderId) {
        this.send('read_receipt', { conversationId, senderId });
    }
    
    notifyNewMessage(messageId, conversationId, receiverId, memberIds, content, messageType) {
        this.send('chat_message', { 
            messageId, conversationId, receiverId, memberIds, content, messageType 
        });
    }
    
    disconnect() {
        this.stopPing();
        if (this.ws) this.ws.close(1000);
    }
}
```

**`chat/assets/js/chat-app.js`** — Main application controller:
```javascript
/**
 * AlumGlass Chat Application
 * 
 * Main controller that ties together:
 * - WebSocket real-time connection
 * - AJAX API calls for data persistence
 * - UI rendering
 * - Browser notifications
 * - Message search
 */
import { ChatSocket } from './chat-socket.js';

const WS_URL = document.querySelector('meta[name="ws-url"]')?.content || 'ws://localhost:8080';
const SESSION_TOKEN = document.querySelector('meta[name="session-token"]')?.content || '';
const CURRENT_USER_ID = parseInt(document.querySelector('meta[name="user-id"]')?.content || '0');
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

class ChatApp {
    constructor() {
        this.socket = new ChatSocket(WS_URL, SESSION_TOKEN);
        this.activeConversationId = null;
        this.conversations = [];
        this.messages = new Map(); // conversationId → messages[]
        this.typingUsers = new Map(); // conversationId → Set<userId>
        this.onlineUsers = new Set();
        this.searchMode = false;
        
        // DOM references
        this.els = {
            convList: document.getElementById('conversation-list'),
            chatArea: document.getElementById('chat-messages'),
            chatHeader: document.getElementById('chat-header'),
            messageInput: document.getElementById('message-input'),
            sendBtn: document.getElementById('send-btn'),
            attachBtn: document.getElementById('attach-btn'),
            fileInput: document.getElementById('file-input'),
            searchInput: document.getElementById('search-input'),
            searchResults: document.getElementById('search-results'),
            typingIndicator: document.getElementById('typing-indicator'),
            connectionStatus: document.getElementById('connection-status'),
            newGroupBtn: document.getElementById('new-group-btn'),
            emojiBtn: document.getElementById('emoji-btn'),
            replyBar: document.getElementById('reply-bar'),
            mobileBackBtn: document.getElementById('mobile-back-btn'),
        };
        
        this.init();
    }
    
    async init() {
        // Load conversations
        await this.loadConversations();
        
        // Connect WebSocket
        this.socket.connect();
        this.bindSocketEvents();
        this.bindUIEvents();
        
        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        
        // Auto-select conversation from URL
        const urlParams = new URLSearchParams(window.location.search);
        const convId = urlParams.get('conversation');
        const userId = urlParams.get('user_id'); // backward compat with old messages.php
        
        if (convId) {
            this.selectConversation(parseInt(convId));
        } else if (userId) {
            await this.findOrCreateDirectConversation(parseInt(userId));
        }
    }
    
    // ... (AJAX methods for loading data, sending messages, etc.)
    
    async loadConversations() {
        try {
            const res = await fetch('/chat/api/conversations.php');
            const data = await res.json();
            if (data.success) {
                this.conversations = data.conversations;
                this.renderConversationList();
            }
        } catch (err) {
            console.error('Failed to load conversations:', err);
        }
    }
    
    async loadMessages(conversationId, beforeId = null) {
        const url = `/chat/api/messages.php?conversation_id=${conversationId}` + 
                    (beforeId ? `&before=${beforeId}` : '');
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (data.success) {
                if (!this.messages.has(conversationId)) {
                    this.messages.set(conversationId, []);
                }
                const existing = this.messages.get(conversationId);
                if (beforeId) {
                    existing.unshift(...data.messages);
                } else {
                    this.messages.set(conversationId, data.messages);
                }
                this.renderMessages(conversationId);
            }
        } catch (err) {
            console.error('Failed to load messages:', err);
        }
    }
    
    async sendMessage() {
        const content = this.els.messageInput.value.trim();
        const file = this.els.fileInput?.files[0];
        
        if (!content && !file) return;
        if (!this.activeConversationId) return;
        
        const formData = new FormData();
        formData.append('conversation_id', this.activeConversationId);
        formData.append('content', content);
        formData.append('csrf_token', CSRF_TOKEN);
        
        if (this.replyToId) {
            formData.append('reply_to_id', this.replyToId);
        }
        if (file) {
            formData.append('file', file);
        }
        
        // Optimistic UI — show message immediately
        const tempId = 'temp_' + Date.now();
        this.addOptimisticMessage(tempId, content, file);
        
        // Clear input
        this.els.messageInput.value = '';
        this.els.messageInput.style.height = 'auto';
        this.clearReply();
        if (this.els.fileInput) this.els.fileInput.value = '';
        
        try {
            const res = await fetch('/chat/api/messages.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                // Replace optimistic message with real one
                this.replaceOptimisticMessage(tempId, data.message);
                
                // Notify via WebSocket
                const conv = this.conversations.find(c => c.id == this.activeConversationId);
                this.socket.notifyNewMessage(
                    data.message.id,
                    this.activeConversationId,
                    conv?.type === 'direct' ? this.getOtherUserId(conv) : null,
                    conv?.type === 'group' ? this.getConversationMemberIds(conv) : null,
                    content,
                    data.message.message_type
                );
            } else {
                this.removeOptimisticMessage(tempId);
                AG.toast(data.message || 'Failed to send', 'danger');
            }
        } catch (err) {
            this.removeOptimisticMessage(tempId);
            AG.toast('Connection error', 'danger');
        }
    }
    
    // ... rendering methods, event binding, etc.
    // Full implementation in chat-ui.js and chat-app.js
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.chatApp = new ChatApp();
});
```

### Chat Page (`chat/index.php`):

This should be a clean, modern chat interface. Key UI features:
- **Left panel**: conversation list with search, unread badges, online indicators
- **Right panel**: active chat with messages, typing indicator, emoji, file upload
- **Mobile**: full-screen conversation list OR chat (toggle), bottom input bar
- **Header**: user avatar + name + online status + call/video placeholders
- **Messages**: bubbles with avatar, timestamp, read receipts, reply quotes, reactions
- **Input**: auto-resize textarea, emoji picker, file attach, voice record, send button

The PHP file should be minimal — just auth check, initial data load, and HTML skeleton. All rendering done via JavaScript.

### Backward compatibility:

Create redirect in `messages.php`:
```php
<?php
// Backward compatibility — redirect to new chat
require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();
$userId = $_GET['user_id'] ?? '';
$redirect = '/chat/';
if ($userId) {
    $redirect .= '?user_id=' . urlencode($userId);
}
header('Location: ' . $redirect);
exit;
```

### Commits:
```
feat(chat): create WebSocket server (Node.js + ws)
feat(chat): create chat database migration (conversations, members, presence)
feat(chat): create chat PHP API (conversations, messages, search, upload, contacts)
feat(chat): create chat-socket.js WebSocket client with reconnect and fallback
feat(chat): create chat-app.js main application controller
feat(chat): create chat-ui.js rendering (conversation list, messages, bubbles)
feat(chat): create chat/index.php page with modern UI
feat(chat): create chat.css with responsive design and dark mode support
feat(chat): add browser notifications (chat-notifications.js)
feat(chat): add message search with highlighting (chat-search.js)
feat(chat): add emoji picker, file preview, voice recording
feat(chat): add group chat creation and management
refactor(global): redirect messages.php to new /chat/ 
chore(chat): add WebSocket PM2 config for production
```

### Verification:
```bash
# New files exist
test -f chat/index.php && echo "✅ chat page" || echo "❌"
test -f chat/assets/js/chat-app.js && echo "✅ chat app" || echo "❌"
test -f chat/assets/js/chat-socket.js && echo "✅ socket client" || echo "❌"
test -f websocket/server.js && echo "✅ ws server" || echo "❌"
test -f websocket/package.json && echo "✅ package.json" || echo "❌"
ls chat/api/*.php | wc -l  # Should be 5+

# Old URL redirects
grep -q "Location.*chat" messages.php && echo "✅ redirect" || echo "❌"

# Node.js syntax check
node --check websocket/server.js 2>&1
```

---

## 4B — Mobile Experience
> **Branch:** `claude/phase-4b-mobile-ux`

### 4B.1 Bottom Navigation Bar

Create `assets/css/mobile-nav.css` and `assets/js/mobile-nav.js`:

A fixed bottom navigation bar for mobile (hidden on desktop) with 5 key actions:
1. Home (dashboard)
2. Reports (daily reports)
3. Chat (with unread badge)
4. Calendar
5. Profile/More

```css
@media (max-width: 768px) {
    .ag-bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 60px;
        background: var(--ag-white);
        border-top: 0.5px solid var(--ag-gray-300);
        display: flex;
        justify-content: space-around;
        align-items: center;
        z-index: 1000;
        padding-bottom: env(safe-area-inset-bottom); /* iPhone notch */
    }
    .ag-bottom-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        color: var(--ag-gray-500);
        text-decoration: none;
        font-size: 10px;
        position: relative;
        padding: 4px 12px;
    }
    .ag-bottom-nav-item.active { color: var(--ag-primary); }
    .ag-bottom-nav-item .badge {
        position: absolute;
        top: -2px;
        right: 2px;
        background: var(--ag-danger);
        color: white;
        font-size: 9px;
        min-width: 16px;
        height: 16px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    /* Add padding to body so content isn't hidden behind nav */
    body { padding-bottom: 70px; }
    /* Hide desktop sidebar on mobile */
    .sidebar { display: none; }
}
```

Include in all headers.

### 4B.2 Responsive Data Tables

Create `assets/css/responsive-tables.css` and `assets/js/responsive-tables.js`:

For tables wider than mobile viewport:
- On mobile: convert to card-based layout (each row becomes a card with label:value pairs)
- Or: horizontal scroll wrapper with shadow indicators
- Priority columns shown, secondary columns collapsed with "show more"

```css
@media (max-width: 768px) {
    .ag-table-responsive {
        /* Card-based view */
    }
    .ag-table-responsive thead { display: none; }
    .ag-table-responsive tr {
        display: block;
        background: var(--ag-white);
        border-radius: var(--ag-radius-md);
        margin-bottom: 8px;
        padding: 12px;
        box-shadow: var(--ag-shadow-sm);
    }
    .ag-table-responsive td {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        border: none;
    }
    .ag-table-responsive td::before {
        content: attr(data-label);
        font-weight: 500;
        color: var(--ag-gray-500);
        font-size: 12px;
    }
}
```

### 4B.3 Touch Interactions

- Pull-to-refresh on dashboards
- Swipe-left on chat messages for reply/delete
- Swipe between conversation and list on chat
- Touch-friendly button sizes (min 44x44px)

### 4B.4 PWA Support

Create `manifest.json` and `service-worker.js`:
- Installable on home screen
- Offline fallback page
- Cache static assets (CSS, JS, fonts)
- Push notification support (future)

### Commits:
```
feat(mobile): add bottom navigation bar for mobile devices
feat(mobile): add responsive table card-view CSS
feat(mobile): add pull-to-refresh and swipe gestures
feat(mobile): add PWA manifest and service worker
style(mobile): ensure all touch targets are 44px minimum
refactor(mobile): merge contractor_batch_update_mobile feature parity
```

---

## 4C — Form Wizard & Auto-save
> **Branch:** `claude/phase-4c-form-wizard`

### 4C.1 Create reusable form wizard component

**`assets/js/form-wizard.js`:**
```javascript
/**
 * Multi-step form wizard with progress indicator
 * 
 * Usage:
 *   <div data-wizard>
 *     <div data-step="1" data-title="اطلاعات پایه">...</div>
 *     <div data-step="2" data-title="پرسنل">...</div>
 *     <div data-step="3" data-title="مصالح">...</div>
 *   </div>
 *   <script>new FormWizard(document.querySelector('[data-wizard]'))</script>
 */
export class FormWizard {
    constructor(container, options = {}) {
        this.container = container;
        this.steps = [...container.querySelectorAll('[data-step]')];
        this.currentStep = 0;
        this.autoSaveKey = options.autoSaveKey || 'form_' + location.pathname;
        this.autoSaveInterval = options.autoSaveInterval || 30000; // 30s
        this.onComplete = options.onComplete || null;
        
        this.buildUI();
        this.restoreProgress();
        this.startAutoSave();
    }
    
    buildUI() {
        // Create progress bar, step indicators, nav buttons
        // Insert before steps
    }
    
    restoreProgress() {
        const saved = localStorage.getItem(this.autoSaveKey);
        if (saved) {
            const data = JSON.parse(saved);
            // Restore form values
            // Show toast: "پیش‌نویس بازیابی شد"
        }
    }
    
    startAutoSave() {
        setInterval(() => this.saveProgress(), this.autoSaveInterval);
        // Also save on input change (debounced)
    }
    
    saveProgress() {
        const formData = new FormData(this.container.closest('form'));
        const data = Object.fromEntries(formData);
        data._currentStep = this.currentStep;
        data._savedAt = new Date().toISOString();
        localStorage.setItem(this.autoSaveKey, JSON.stringify(data));
    }
    
    // ... navigation, validation, progress UI methods
}
```

### 4C.2 Apply to daily report form

Convert `pardis/daily_report_form_ps.php` sections into wizard steps:
- Step 1: اطلاعات پایه (تاریخ، آب‌وهوا، شماره گزارش)
- Step 2: پرسنل (جدول نفرات)
- Step 3: ماشین‌آلات (جدول ماشین‌آلات)
- Step 4: مصالح (جدول مصالح)
- Step 5: فعالیت‌ها (جدول فعالیت‌ها)
- Step 6: عکس‌ها و توضیحات (آپلود عکس + متن)
- Step 7: مرور و ارسال (خلاصه + تأیید)

### 4C.3 Apply to meeting minutes form

Split `pardis/meeting_minutes_form.php`:
- Step 1: اطلاعات جلسه (تاریخ، محل، شرکت‌کنندگان)
- Step 2: دستور جلسه (مصوبات)
- Step 3: بارگذاری مستندات
- Step 4: مرور و ثبت

### Commits:
```
feat(forms): create reusable FormWizard component with progress and auto-save
refactor(pardis): convert daily_report_form_ps to 7-step wizard
refactor(pardis): convert meeting_minutes_form to 4-step wizard
feat(forms): add auto-save with localStorage and restore notification
style(forms): add wizard progress bar and step indicator CSS
```

---

## 4D — Design System Enhancements
> **Branch:** `claude/phase-4d-design-system`

### 4D.1 Dark Mode

Add to `assets/css/design-system.css`:
```css
@media (prefers-color-scheme: dark) {
    :root {
        --ag-primary: #60a5fa;
        --ag-primary-dark: #1e40af;
        --ag-white: #1a1a2e;
        --ag-gray-100: #16213e;
        --ag-gray-200: #1a1a2e;
        --ag-gray-300: #2a2a4a;
        --ag-gray-500: #9ca3af;
        --ag-gray-700: #d1d5db;
        --ag-gray-900: #f3f4f6;
        /* ... all semantic colors adjusted */
    }
}
```

Add manual toggle button (stored in localStorage).

### 4D.2 Toast Notification System

Already defined in `global.js` from Phase 2. Ensure it's used consistently:
- Replace all `alert()` calls with `AG.toast()`
- Replace all `Swal.fire()` simple alerts with `AG.toast()` (keep Swal for confirms)

### 4D.3 Breadcrumbs

Create `includes/breadcrumbs.php`:
```php
function renderBreadcrumbs(array $items): string {
    // $items = [['label' => 'Home', 'url' => '/'], ['label' => 'Reports', 'url' => '/ghom/reports.php'], ['label' => 'Daily']]
}
```

Add to every page header.

### 4D.4 Empty States

Create reusable empty state component:
```html
<div class="ag-empty-state">
    <svg><!-- illustration --></svg>
    <p class="ag-empty-title">هنوز گزارشی ثبت نشده</p>
    <p class="ag-empty-desc">برای ثبت اولین گزارش، دکمه زیر را بزنید</p>
    <a href="..." class="ag-btn ag-btn-primary">ثبت گزارش</a>
</div>
```

### Commits:
```
feat(design): add dark mode CSS with manual toggle
refactor(global): replace alert() and simple Swal with AG.toast()
feat(design): add breadcrumb component to all pages
feat(design): add empty state components for lists and tables
style(design): standardize icon usage (Lucide icons)
chore(design): update design-system.css documentation
```

---

## Post-Completion

### Update docs:
- `ARCHITECTURE.md` — WebSocket server, chat module, PWA, form wizard
- `TECH_DEBT.md` — Mark resolved: TD-UX-002, all chat/mobile/form issues
- `CHANGELOG.md` — v2.0.0 entry
- `SETUP.md` — Node.js requirement, PM2 setup, WebSocket deployment

### Tag and report:
```bash
git tag v2.0.0 -m "Phase 4: UI/UX overhaul — real-time chat, mobile, forms, design system"
git push origin v2.0.0
```

### Create `docs/reports/phase4-final-report.md` with metrics.

### Provide download links:
```
📥 Report: https://github.com/saeidsm/AlumGlassProjMang/blob/main/docs/reports/phase4-final-report.md
📥 ZIP: https://github.com/saeidsm/AlumGlassProjMang/archive/refs/tags/v2.0.0.zip
```

---

## 🚀 START COMMAND

Begin with Phase 4A (chat). Create the branch and start building.
No approval needed. Execute all 4 sub-phases sequentially.
Start: `git checkout main && git pull && git checkout -b claude/phase-4a-realtime-chat`
