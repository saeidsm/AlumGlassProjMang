/**
 * Browser notifications for new chat messages.
 * Only fires for messages that arrive while the tab is backgrounded
 * or the active conversation differs from the message's conversation.
 */

let granted = false;

export async function initNotifications() {
    if (!('Notification' in window)) return false;
    if (Notification.permission === 'granted') { granted = true; return true; }
    if (Notification.permission === 'denied') return false;
    try {
        const r = await Notification.requestPermission();
        granted = r === 'granted';
        return granted;
    } catch (_) { return false; }
}

export function notify({ title, body, icon, tag, onclick }) {
    if (!granted) return null;
    if (document.visibilityState === 'visible') return null;
    try {
        const n = new Notification(title, {
            body: (body || '').slice(0, 180),
            icon: icon || '/assets/images/default-avatar.jpg',
            tag: tag || 'chat',
            lang: 'fa',
            dir: 'rtl',
        });
        if (onclick) {
            n.addEventListener('click', () => {
                window.focus();
                onclick();
                n.close();
            });
        }
        return n;
    } catch (_) { return null; }
}

export function canNotify() { return granted; }
