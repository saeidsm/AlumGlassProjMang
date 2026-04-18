/**
 * Pure rendering helpers for the chat UI.
 * No business logic here — the ChatApp controller owns state and
 * calls these to produce DOM.
 */

const FA_MONTH_NAMES = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

export function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${hh}:${mm}`;
}

export function formatDateLabel(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    const now = new Date();
    const dayMs = 86400000;
    const sameDay = d.toDateString() === now.toDateString();
    if (sameDay) return 'امروز';
    const yesterday = new Date(now.getTime() - dayMs);
    if (d.toDateString() === yesterday.toDateString()) return 'دیروز';
    return `${d.getDate()} ${FA_MONTH_NAMES[d.getMonth()] || ''}`;
}

export function conversationRow(conv, activeId, presence) {
    const name = conv.display_name || conv.name || '(بدون نام)';
    const avatar = conv.display_avatar || '/assets/images/default-avatar.jpg';
    const unread = parseInt(conv.unread_count, 10) || 0;
    const active = String(conv.id) === String(activeId) ? 'is-active' : '';
    const status = conv.peer_user_id && presence.get(conv.peer_user_id) === 'online' ? 'is-online' : '';
    const preview = previewForMessage(conv.last_message, conv.last_message_type);

    return `
        <button class="chat-conv ${active}" data-conv-id="${conv.id}" type="button">
            <span class="chat-conv__avatar ${status}">
                <img src="${escapeHtml(avatar)}" alt="" loading="lazy"
                     onerror="this.src='/assets/images/default-avatar.jpg'">
            </span>
            <span class="chat-conv__body">
                <span class="chat-conv__row">
                    <span class="chat-conv__name">${escapeHtml(name)}</span>
                    <span class="chat-conv__time">${formatTime(conv.last_message_time)}</span>
                </span>
                <span class="chat-conv__row">
                    <span class="chat-conv__preview">${escapeHtml(preview)}</span>
                    ${unread > 0 ? `<span class="chat-badge">${unread}</span>` : ''}
                </span>
            </span>
        </button>
    `;
}

function previewForMessage(content, type) {
    if (content) return content.length > 60 ? content.slice(0, 60) + '…' : content;
    switch (type) {
        case 'image': return '🖼️ تصویر';
        case 'audio': return '🎵 پیام صوتی';
        case 'video': return '🎬 ویدیو';
        case 'file':  return '📎 فایل';
        default:      return '';
    }
}

export function messageBubble(msg, currentUserId) {
    const mine = parseInt(msg.sender_id, 10) === parseInt(currentUserId, 10);
    const name = [msg.sender_fname, msg.sender_lname].filter(Boolean).join(' ');
    const avatar = msg.sender_avatar || '/assets/images/default-avatar.jpg';
    const time = formatTime(msg.timestamp);

    let bodyHtml = '';
    if (msg.reply_to_id && msg.reply_content) {
        const replyName = [msg.reply_fname, msg.reply_lname].filter(Boolean).join(' ');
        bodyHtml += `
            <div class="chat-msg__reply">
                <span class="chat-msg__reply-name">${escapeHtml(replyName)}</span>
                <span class="chat-msg__reply-text">${escapeHtml((msg.reply_content || '').slice(0, 80))}</span>
            </div>`;
    }

    if (msg.file_path) {
        bodyHtml += renderAttachment(msg);
    }
    if (msg.message_content) {
        bodyHtml += `<div class="chat-msg__text">${linkify(escapeHtml(msg.message_content))}</div>`;
    }
    if (msg.caption) {
        bodyHtml += `<div class="chat-msg__caption">${escapeHtml(msg.caption)}</div>`;
    }

    const status = mine ? `
        <span class="chat-msg__status" aria-hidden="true">
            ${msg.is_read ? '✓✓' : '✓'}
        </span>` : '';

    return `
        <article class="chat-msg ${mine ? 'is-mine' : 'is-theirs'}" data-msg-id="${msg.id}" data-sender-id="${msg.sender_id}">
            ${!mine ? `<img class="chat-msg__avatar" src="${escapeHtml(avatar)}" alt="" onerror="this.src='/assets/images/default-avatar.jpg'">` : ''}
            <div class="chat-msg__wrap">
                ${!mine ? `<div class="chat-msg__name">${escapeHtml(name)}</div>` : ''}
                <div class="chat-msg__bubble">
                    ${bodyHtml}
                    <div class="chat-msg__meta">
                        <time class="chat-msg__time">${time}</time>
                        ${status}
                    </div>
                </div>
            </div>
        </article>
    `;
}

function renderAttachment(msg) {
    const url = escapeHtml(msg.file_path);
    switch (msg.message_type) {
        case 'image':
            return `<a class="chat-msg__image" href="${url}" target="_blank" rel="noopener">
                <img src="${url}" alt="" loading="lazy">
            </a>`;
        case 'audio':
            return `<audio class="chat-msg__audio" controls preload="none" src="${url}"></audio>`;
        case 'video':
            return `<video class="chat-msg__video" controls preload="metadata" src="${url}"></video>`;
        case 'file':
        default:
            return `<a class="chat-msg__file" href="${url}" target="_blank" rel="noopener" download>
                <span class="chat-msg__file-icon">📎</span>
                <span class="chat-msg__file-name">${escapeHtml(msg.caption || 'فایل')}</span>
            </a>`;
    }
}

function linkify(str) {
    return str.replace(/(https?:\/\/[^\s<]+)/g, (m) => `<a href="${m}" target="_blank" rel="noopener">${m}</a>`);
}

export function daySeparator(label) {
    return `<div class="chat-day-sep"><span>${escapeHtml(label)}</span></div>`;
}

export function typingIndicator(names) {
    if (!names.length) return '';
    const who = names.length > 2 ? `${names.slice(0, 2).join('، ')} و دیگران` : names.join('، ');
    return `
        <div class="chat-typing" role="status">
            <span class="chat-typing__dots"><i></i><i></i><i></i></span>
            <span class="chat-typing__who">${escapeHtml(who)} در حال تایپ…</span>
        </div>`;
}

export function emptyState(title, desc = '') {
    return `
        <div class="chat-empty">
            <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
            </svg>
            <p class="chat-empty__title">${escapeHtml(title)}</p>
            ${desc ? `<p class="chat-empty__desc">${escapeHtml(desc)}</p>` : ''}
        </div>`;
}

export function searchResultRow(result) {
    const name = [result.first_name, result.last_name].filter(Boolean).join(' ');
    return `
        <button class="chat-search-hit" data-conv-id="${result.conversation_id}" data-msg-id="${result.id}" type="button">
            <span class="chat-search-hit__who">${escapeHtml(name)}</span>
            <span class="chat-search-hit__text">${escapeHtml((result.message_content || '').slice(0, 120))}</span>
            <span class="chat-search-hit__time">${formatTime(result.timestamp)}</span>
        </button>`;
}
