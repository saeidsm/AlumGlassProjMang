/**
 * Debounced search helper used by ChatApp.
 * Calls /chat/api/search.php?q=... and renders hit rows.
 */

import { searchResultRow } from './chat-ui.js';

export class ChatSearch {
    constructor({ inputEl, resultsEl, onSelect }) {
        this.inputEl = inputEl;
        this.resultsEl = resultsEl;
        this.onSelect = onSelect;
        this.debounceTimer = null;
        this.controller = null;
        this.bind();
    }

    bind() {
        if (!this.inputEl) return;
        this.inputEl.addEventListener('input', () => this.scheduleSearch());
        this.inputEl.addEventListener('search', () => this.scheduleSearch());
        if (this.resultsEl) {
            this.resultsEl.addEventListener('click', (e) => {
                const hit = e.target.closest('[data-msg-id]');
                if (!hit) return;
                const convId = parseInt(hit.dataset.convId, 10);
                const msgId = parseInt(hit.dataset.msgId, 10);
                this.onSelect?.(convId, msgId);
            });
        }
    }

    scheduleSearch() {
        const q = (this.inputEl.value || '').trim();
        clearTimeout(this.debounceTimer);
        if (q.length < 2) {
            this.clear();
            return;
        }
        this.debounceTimer = setTimeout(() => this.run(q), 250);
    }

    async run(q) {
        if (this.controller) this.controller.abort();
        this.controller = new AbortController();
        try {
            const res = await fetch(`/chat/api/search.php?q=${encodeURIComponent(q)}`, {
                signal: this.controller.signal,
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (!data.success) { this.clear(); return; }
            this.render(data.results || []);
        } catch (err) {
            if (err.name !== 'AbortError') console.error('[search]', err);
        }
    }

    render(rows) {
        if (!this.resultsEl) return;
        if (!rows.length) {
            this.resultsEl.innerHTML = '<div class="chat-search__empty">نتیجه‌ای یافت نشد</div>';
            this.resultsEl.classList.add('is-open');
            return;
        }
        this.resultsEl.innerHTML = rows.map(searchResultRow).join('');
        this.resultsEl.classList.add('is-open');
    }

    clear() {
        if (this.resultsEl) {
            this.resultsEl.innerHTML = '';
            this.resultsEl.classList.remove('is-open');
        }
    }
}
