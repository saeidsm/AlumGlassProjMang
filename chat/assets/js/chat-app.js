/**
 * Chat application controller.
 * Owns all state; rendering is delegated to chat-ui.js.
 */

import { ChatSocket } from './chat-socket.js';
import {
    conversationRow, messageBubble, typingIndicator, daySeparator, emptyState, escapeHtml,
    formatDateLabel,
} from './chat-ui.js';
import { ChatSearch } from './chat-search.js';
import { initNotifications, notify } from './chat-notifications.js';

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';

const WS_URL = meta('ws-url') || (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + '/ws';
const SESSION_TOKEN = meta('session-token');
const CURRENT_USER_ID = parseInt(meta('user-id') || '0', 10);
const CSRF_TOKEN = meta('csrf-token');

class ChatApp {
    constructor() {
        this.activeConversationId = null;
        this.activeConversation = null;
        this.conversations = [];
        /** conversationId → Map<messageId, message> */
        this.messages = new Map();
        this.typing = new Map();
        this.presence = new Map();
        this.contacts = [];
        this.replyTo = null;
        this.pendingFile = null;

        this.els = {
            convList: document.getElementById('conv-list'),
            chatArea: document.getElementById('chat-messages'),
            chatHeader: document.getElementById('chat-header'),
            messageInput: document.getElementById('message-input'),
            sendBtn: document.getElementById('send-btn'),
            attachBtn: document.getElementById('attach-btn'),
            fileInput: document.getElementById('file-input'),
            searchInput: document.getElementById('chat-search-input'),
            searchResults: document.getElementById('chat-search-results'),
            typingIndicator: document.getElementById('typing-indicator'),
            connectionStatus: document.getElementById('connection-status'),
            newGroupBtn: document.getElementById('new-group-btn'),
            groupModal: document.getElementById('group-modal'),
            groupForm: document.getElementById('group-form'),
            replyBar: document.getElementById('reply-bar'),
            replyText: document.getElementById('reply-text'),
            clearReplyBtn: document.getElementById('clear-reply-btn'),
            mobileBackBtn: document.getElementById('mobile-back-btn'),
            chatShell: document.getElementById('chat-shell'),
            previewBar: document.getElementById('preview-bar'),
            previewText: document.getElementById('preview-text'),
            clearPreviewBtn: document.getElementById('clear-preview-btn'),
            contactPickerBtn: document.getElementById('contact-picker-btn'),
            contactModal: document.getElementById('contact-modal'),
            contactList: document.getElementById('contact-list'),
        };

        this.socket = new ChatSocket(WS_URL, SESSION_TOKEN);

        new ChatSearch({
            inputEl: this.els.searchInput,
            resultsEl: this.els.searchResults,
            onSelect: (convId, msgId) => this.jumpToMessage(convId, msgId),
        });

        this.init();
    }

    async init() {
        this.bindUI();
        this.bindSocket();

        initNotifications();

        await Promise.all([this.loadConversations(), this.loadContacts()]);

        this.socket.connect();

        const params = new URLSearchParams(location.search);
        const convId = parseInt(params.get('conversation') || '0', 10);
        const peerId = parseInt(params.get('user_id') || '0', 10);
        if (convId) {
            this.selectConversation(convId);
        } else if (peerId) {
            await this.openDirectWith(peerId);
        }
    }

    // ── Data ──

    async loadConversations() {
        try {
            const res = await fetch('/chat/api/conversations.php', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.success) {
                this.conversations = data.conversations || [];
                this.renderConvList();
            }
        } catch (err) {
            console.error('[chat] conversations failed', err);
        }
    }

    async loadContacts() {
        try {
            const res = await fetch('/chat/api/contacts.php', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.success) {
                this.contacts = data.contacts || [];
                for (const c of this.contacts) {
                    if (c.status === 'online') this.presence.set(parseInt(c.id, 10), 'online');
                }
                this.renderContactList();
            }
        } catch (err) {
            console.error('[chat] contacts failed', err);
        }
    }

    async loadMessages(convId, { beforeId = null } = {}) {
        try {
            const qs = new URLSearchParams({ conversation_id: convId });
            if (beforeId) qs.set('before', beforeId);
            const res = await fetch(`/chat/api/messages.php?${qs}`, { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) return;

            if (!this.messages.has(convId)) this.messages.set(convId, new Map());
            const bucket = this.messages.get(convId);
            for (const m of data.messages) bucket.set(parseInt(m.id, 10), m);
            if (convId === this.activeConversationId) this.renderMessages();

            // Mark the conversation as read server-side
            this.markRead(convId);
        } catch (err) {
            console.error('[chat] messages failed', err);
        }
    }

    async openDirectWith(peerId) {
        try {
            const fd = new FormData();
            fd.append('peer_id', peerId);
            fd.append('csrf_token', CSRF_TOKEN);
            const res = await fetch('/chat/api/direct.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json();
            if (data.success) {
                if (data.created) await this.loadConversations();
                this.selectConversation(data.conversationId);
            }
        } catch (err) {
            console.error('[chat] direct failed', err);
        }
    }

    async markRead(convId) {
        try {
            const fd = new FormData();
            fd.append('conversation_id', convId);
            fd.append('csrf_token', CSRF_TOKEN);
            await fetch('/chat/api/read.php', { method: 'POST', body: fd, credentials: 'same-origin' });

            const conv = this.conversations.find((c) => c.id == convId);
            if (conv) { conv.unread_count = 0; this.renderConvList(); }
        } catch (_) { /* non-fatal */ }
    }

    // ── Selection & rendering ──

    selectConversation(convId) {
        const conv = this.conversations.find((c) => String(c.id) === String(convId));
        if (!conv) {
            // Maybe freshly created — refetch once.
            this.loadConversations().then(() => {
                const again = this.conversations.find((c) => String(c.id) === String(convId));
                if (again) this.selectConversation(convId);
            });
            return;
        }
        this.activeConversationId = parseInt(convId, 10);
        this.activeConversation = conv;
        this.renderConvList();
        this.renderChatHeader();
        this.els.chatArea.innerHTML = '<div class="chat-loading">در حال بارگذاری…</div>';
        this.loadMessages(this.activeConversationId);

        if (this.els.chatShell) this.els.chatShell.classList.add('is-conversation-open');
        history.replaceState(null, '', `?conversation=${this.activeConversationId}`);
    }

    renderConvList() {
        if (!this.els.convList) return;
        if (!this.conversations.length) {
            this.els.convList.innerHTML = emptyState('گفتگویی نیست', 'برای شروع چت، یک مخاطب انتخاب کنید.');
            return;
        }
        this.els.convList.innerHTML = this.conversations
            .map((c) => conversationRow(c, this.activeConversationId, this.presence))
            .join('');
    }

    renderChatHeader() {
        if (!this.els.chatHeader || !this.activeConversation) return;
        const c = this.activeConversation;
        const status = c.type === 'direct' && c.peer_user_id
            ? (this.presence.get(parseInt(c.peer_user_id, 10)) === 'online' ? 'آنلاین' : 'آفلاین')
            : (c.type === 'group' ? 'گروه' : '');
        this.els.chatHeader.innerHTML = `
            <button id="mobile-back-btn" class="chat-header__back" type="button" aria-label="بازگشت">‹</button>
            <img class="chat-header__avatar" src="${escapeHtml(c.display_avatar || '/assets/images/default-avatar.jpg')}" alt=""
                 onerror="this.src='/assets/images/default-avatar.jpg'">
            <div class="chat-header__meta">
                <div class="chat-header__name">${escapeHtml(c.display_name || c.name || '')}</div>
                <div class="chat-header__status">${escapeHtml(status)}</div>
            </div>
        `;
        document.getElementById('mobile-back-btn')?.addEventListener('click', () => this.closeConversation());
    }

    renderMessages() {
        if (!this.els.chatArea) return;
        const bucket = this.messages.get(this.activeConversationId);
        if (!bucket || bucket.size === 0) {
            this.els.chatArea.innerHTML = emptyState('هنوز پیامی رد و بدل نشده', 'اولین پیام را بفرستید!');
            return;
        }
        const ordered = [...bucket.values()].sort((a, b) => a.id - b.id);
        let lastLabel = '';
        const parts = [];
        for (const m of ordered) {
            const label = formatDateLabel(m.timestamp);
            if (label && label !== lastLabel) {
                parts.push(daySeparator(label));
                lastLabel = label;
            }
            parts.push(messageBubble(m, CURRENT_USER_ID));
        }
        this.els.chatArea.innerHTML = parts.join('');
        this.els.chatArea.scrollTop = this.els.chatArea.scrollHeight;
    }

    renderContactList() {
        if (!this.els.contactList) return;
        this.els.contactList.innerHTML = this.contacts.map((c) => {
            const name = [c.first_name, c.last_name].filter(Boolean).join(' ');
            const online = c.status === 'online';
            return `<button class="chat-contact" type="button" data-user-id="${c.id}">
                <span class="chat-contact__avatar ${online ? 'is-online' : ''}">
                    <img src="${escapeHtml(c.avatar_path || '/assets/images/default-avatar.jpg')}"
                         onerror="this.src='/assets/images/default-avatar.jpg'" alt="">
                </span>
                <span class="chat-contact__name">${escapeHtml(name)}</span>
                ${online ? '<span class="chat-contact__dot" aria-label="آنلاین"></span>' : ''}
            </button>`;
        }).join('');
    }

    closeConversation() {
        this.activeConversationId = null;
        this.activeConversation = null;
        this.els.chatShell?.classList.remove('is-conversation-open');
        if (this.els.chatArea) this.els.chatArea.innerHTML = emptyState('گفتگویی انتخاب نشده');
        if (this.els.chatHeader) this.els.chatHeader.innerHTML = '';
        history.replaceState(null, '', location.pathname);
    }

    jumpToMessage(convId, msgId) {
        this.selectConversation(convId);
        requestAnimationFrame(() => {
            const el = this.els.chatArea?.querySelector(`[data-msg-id="${msgId}"]`);
            if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.classList.add('is-highlighted'); }
        });
    }

    // ── UI events ──

    bindUI() {
        // Conversation pick
        this.els.convList?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-conv-id]');
            if (btn) this.selectConversation(parseInt(btn.dataset.convId, 10));
        });

        // Send
        this.els.sendBtn?.addEventListener('click', () => this.sendMessage());
        this.els.messageInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            } else {
                this.handleTyping();
            }
        });
        this.els.messageInput?.addEventListener('input', () => {
            const ta = this.els.messageInput;
            ta.style.height = 'auto';
            ta.style.height = Math.min(160, ta.scrollHeight) + 'px';
        });

        // File attach
        this.els.attachBtn?.addEventListener('click', () => this.els.fileInput?.click());
        this.els.fileInput?.addEventListener('change', () => {
            const f = this.els.fileInput.files?.[0];
            this.pendingFile = f || null;
            this.renderPreview();
        });
        this.els.clearPreviewBtn?.addEventListener('click', () => {
            this.pendingFile = null;
            if (this.els.fileInput) this.els.fileInput.value = '';
            this.renderPreview();
        });

        // Reply
        this.els.chatArea?.addEventListener('dblclick', (e) => {
            const msg = e.target.closest('[data-msg-id]');
            if (msg) this.setReply(parseInt(msg.dataset.msgId, 10));
        });
        this.els.clearReplyBtn?.addEventListener('click', () => this.setReply(null));

        // Contacts / direct chat
        this.els.contactPickerBtn?.addEventListener('click', () => {
            this.els.contactModal?.classList.add('is-open');
        });
        this.els.contactList?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-user-id]');
            if (!btn) return;
            this.els.contactModal?.classList.remove('is-open');
            this.openDirectWith(parseInt(btn.dataset.userId, 10));
        });
        this.els.contactModal?.addEventListener('click', (e) => {
            if (e.target === this.els.contactModal) this.els.contactModal.classList.remove('is-open');
        });

        // New group
        this.els.newGroupBtn?.addEventListener('click', () => this.els.groupModal?.classList.add('is-open'));
        this.els.groupModal?.addEventListener('click', (e) => {
            if (e.target === this.els.groupModal) this.els.groupModal.classList.remove('is-open');
        });
        this.els.groupForm?.addEventListener('submit', (e) => { e.preventDefault(); this.createGroup(); });

        // Esc closes modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.els.contactModal?.classList.remove('is-open');
                this.els.groupModal?.classList.remove('is-open');
                this.els.searchResults?.classList.remove('is-open');
            }
        });
    }

    bindSocket() {
        this.socket.on('connected', () => this.setConnectionStatus('آنلاین'));
        this.socket.on('disconnected', () => this.setConnectionStatus('در حال اتصال…'));
        this.socket.on('auth_failed', () => this.setConnectionStatus('نشست منقضی شده'));
        this.socket.on('fallback_to_polling', () => this.startPolling());

        this.socket.on('new_message', (data) => this.handleIncomingMessage(data));
        this.socket.on('presence', (data) => this.handlePresence(data));
        this.socket.on('presence_list', (map) => this.handlePresenceList(map));
        this.socket.on('typing', (data) => this.handleTypingEvent(data, true));
        this.socket.on('stop_typing', (data) => this.handleTypingEvent(data, false));
        this.socket.on('read_receipt', (data) => this.handleReadReceipt(data));
    }

    setConnectionStatus(label) {
        if (this.els.connectionStatus) this.els.connectionStatus.textContent = label;
    }

    // ── Outgoing ──

    setReply(msgId) {
        if (!msgId) {
            this.replyTo = null;
            this.els.replyBar?.classList.remove('is-open');
            return;
        }
        const bucket = this.messages.get(this.activeConversationId);
        const msg = bucket?.get(msgId);
        if (!msg) return;
        this.replyTo = msgId;
        if (this.els.replyText) {
            const who = [msg.sender_fname, msg.sender_lname].filter(Boolean).join(' ');
            this.els.replyText.textContent = `${who}: ${(msg.message_content || '').slice(0, 80)}`;
        }
        this.els.replyBar?.classList.add('is-open');
        this.els.messageInput?.focus();
    }

    renderPreview() {
        if (!this.els.previewBar) return;
        if (!this.pendingFile) {
            this.els.previewBar.classList.remove('is-open');
            this.els.previewText && (this.els.previewText.textContent = '');
            return;
        }
        this.els.previewText && (this.els.previewText.textContent = this.pendingFile.name);
        this.els.previewBar.classList.add('is-open');
    }

    handleTyping() {
        if (!this.activeConversation) return;
        const conv = this.activeConversation;
        const now = Date.now();
        if (!this._lastTyping || now - this._lastTyping > 2500) {
            this.socket.sendTyping(
                this.activeConversationId,
                conv.type === 'direct' ? conv.peer_user_id : null,
                conv.type === 'group' ? this.memberIdsFor(conv) : null,
            );
            this._lastTyping = now;
            clearTimeout(this._stopTypingTimer);
            this._stopTypingTimer = setTimeout(() => {
                this.socket.sendStopTyping(
                    this.activeConversationId,
                    conv.type === 'direct' ? conv.peer_user_id : null,
                    conv.type === 'group' ? this.memberIdsFor(conv) : null,
                );
                this._lastTyping = 0;
            }, 3000);
        }
    }

    async sendMessage() {
        if (!this.activeConversationId) return;
        const content = (this.els.messageInput?.value || '').trim();
        const file = this.pendingFile;
        if (!content && !file) return;

        const fd = new FormData();
        fd.append('conversation_id', this.activeConversationId);
        fd.append('content', content);
        fd.append('csrf_token', CSRF_TOKEN);
        if (this.replyTo) fd.append('reply_to_id', this.replyTo);
        if (file) fd.append('file', file);

        const tempId = 'temp_' + Date.now();
        this.addOptimisticMessage(tempId, content, file);
        const savedContent = content;
        this.els.messageInput.value = '';
        this.els.messageInput.style.height = 'auto';
        this.setReply(null);
        this.pendingFile = null;
        if (this.els.fileInput) this.els.fileInput.value = '';
        this.renderPreview();

        try {
            const res = await fetch('/chat/api/messages.php', {
                method: 'POST', body: fd, credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success) {
                this.replaceOptimistic(tempId, data.message);
                this.socket.notifyNewMessage({
                    messageId: data.message.id,
                    conversationId: this.activeConversationId,
                    receiverId: this.activeConversation?.type === 'direct' ? this.activeConversation.peer_user_id : null,
                    memberIds: this.activeConversation?.type === 'group' ? data.member_ids : null,
                    content: savedContent,
                    messageType: data.message.message_type,
                    filePath: data.message.file_path,
                    caption: data.message.caption,
                    replyToId: data.message.reply_to_id,
                });
            } else {
                this.removeOptimistic(tempId);
                this.toast(data.message || 'ارسال پیام ناموفق بود', 'danger');
            }
        } catch (err) {
            this.removeOptimistic(tempId);
            this.toast('خطای شبکه', 'danger');
        }
    }

    async createGroup() {
        const fd = new FormData(this.els.groupForm);
        if (!fd.has('csrf_token')) fd.append('csrf_token', CSRF_TOKEN);
        const selected = [...this.els.groupForm.querySelectorAll('[name="members"]:checked')].map((c) => c.value);
        const name = (fd.get('name') || '').toString().trim();
        if (!name || selected.length === 0) {
            this.toast('نام گروه و حداقل یک عضو الزامی است', 'warning');
            return;
        }
        fd.set('name', name);
        fd.set('members', JSON.stringify(selected));
        try {
            const res = await fetch('/chat/api/conversations.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json();
            if (data.success) {
                this.els.groupModal?.classList.remove('is-open');
                await this.loadConversations();
                this.selectConversation(data.conversationId);
            } else {
                this.toast(data.message || 'ساخت گروه ناموفق بود', 'danger');
            }
        } catch (err) {
            this.toast('خطای شبکه', 'danger');
        }
    }

    // ── Optimistic UI ──

    addOptimisticMessage(tempId, content, file) {
        const bucket = this.messages.get(this.activeConversationId) || new Map();
        this.messages.set(this.activeConversationId, bucket);
        const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
        const dummyId = -Date.now();
        bucket.set(dummyId, {
            id: dummyId,
            conversation_id: this.activeConversationId,
            sender_id: CURRENT_USER_ID,
            sender_fname: 'شما',
            sender_lname: '',
            sender_avatar: '',
            message_content: content,
            message_type: file ? (file.type.startsWith('image/') ? 'image' : 'file') : 'text',
            file_path: file ? URL.createObjectURL(file) : null,
            timestamp: now,
            is_read: 0,
            _tempId: tempId,
        });
        this.renderMessages();
    }

    replaceOptimistic(tempId, real) {
        const bucket = this.messages.get(this.activeConversationId);
        if (!bucket) return;
        for (const [k, v] of bucket.entries()) {
            if (v._tempId === tempId) { bucket.delete(k); break; }
        }
        bucket.set(parseInt(real.id, 10), real);
        this.renderMessages();
    }

    removeOptimistic(tempId) {
        const bucket = this.messages.get(this.activeConversationId);
        if (!bucket) return;
        for (const [k, v] of bucket.entries()) {
            if (v._tempId === tempId) { bucket.delete(k); break; }
        }
        this.renderMessages();
    }

    // ── Incoming ──

    handleIncomingMessage(data) {
        if (!data || !data.conversationId) return;
        const convId = parseInt(data.conversationId, 10);
        if (!this.messages.has(convId)) this.messages.set(convId, new Map());
        const bucket = this.messages.get(convId);

        if (bucket.has(parseInt(data.id, 10))) return; // already have it

        bucket.set(parseInt(data.id, 10), {
            id: data.id,
            conversation_id: convId,
            sender_id: data.senderId,
            sender_fname: '',
            sender_lname: '',
            sender_avatar: '',
            message_content: data.content,
            message_type: data.messageType,
            file_path: data.filePath,
            caption: data.caption,
            timestamp: data.timestamp,
            reply_to_id: data.replyToId,
            is_read: 0,
        });

        if (convId === this.activeConversationId) {
            this.renderMessages();
            this.markRead(convId);
        } else {
            const conv = this.conversations.find((c) => c.id == convId);
            if (conv) { conv.unread_count = (parseInt(conv.unread_count, 10) || 0) + 1; this.renderConvList(); }
            notify({
                title: conv?.display_name || 'پیام جدید',
                body: data.content || '[فایل]',
                icon: conv?.display_avatar || '/assets/images/default-avatar.jpg',
                tag: 'chat-' + convId,
                onclick: () => this.selectConversation(convId),
            });
        }

        // Ensure sender info is present — refresh the conversation list (cheap).
        if (!this.conversations.some((c) => c.id == convId)) {
            this.loadConversations();
        }
    }

    handlePresence({ userId, status }) {
        this.presence.set(parseInt(userId, 10), status);
        this.renderConvList();
        if (this.activeConversation?.type === 'direct' && parseInt(this.activeConversation.peer_user_id, 10) === parseInt(userId, 10)) {
            this.renderChatHeader();
        }
        const contact = this.contacts.find((c) => c.id == userId);
        if (contact) { contact.status = status; this.renderContactList(); }
    }

    handlePresenceList(map) {
        if (!map || typeof map !== 'object') return;
        for (const [uid, p] of Object.entries(map)) {
            this.presence.set(parseInt(uid, 10), p.status);
        }
        this.renderConvList();
    }

    handleTypingEvent({ userId, conversationId }, isTyping) {
        const convId = parseInt(conversationId, 10);
        if (!this.typing.has(convId)) this.typing.set(convId, new Set());
        const set = this.typing.get(convId);
        if (isTyping) set.add(parseInt(userId, 10));
        else set.delete(parseInt(userId, 10));
        this.renderTypingIndicator();
    }

    handleReadReceipt({ conversationId, readBy }) {
        const convId = parseInt(conversationId, 10);
        const bucket = this.messages.get(convId);
        if (!bucket) return;
        for (const m of bucket.values()) {
            if (parseInt(m.sender_id, 10) === CURRENT_USER_ID) m.is_read = 1;
        }
        if (convId === this.activeConversationId) this.renderMessages();
    }

    renderTypingIndicator() {
        if (!this.els.typingIndicator || !this.activeConversationId) return;
        const set = this.typing.get(this.activeConversationId);
        if (!set || set.size === 0) {
            this.els.typingIndicator.innerHTML = '';
            return;
        }
        const names = [...set].map((uid) => {
            const c = this.contacts.find((x) => x.id == uid);
            return c ? [c.first_name, c.last_name].filter(Boolean).join(' ') : 'کاربر';
        });
        this.els.typingIndicator.innerHTML = typingIndicator(names);
    }

    // ── Helpers ──

    memberIdsFor(conv) {
        // Best-effort — the server returns real member_ids on send; this is only used for typing broadcasts.
        if (!conv) return null;
        if (conv._memberIds) return conv._memberIds;
        return null;
    }

    startPolling() {
        if (this._poll) return;
        this.setConnectionStatus('حالت پشتیبان');
        this._poll = setInterval(() => {
            if (this.activeConversationId) this.loadMessages(this.activeConversationId);
        }, 8000);
    }

    toast(message, level = 'info') {
        if (window.AG?.toast) window.AG.toast(message, level);
        else console.log(`[toast:${level}]`, message);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.chatApp = new ChatApp();
});
