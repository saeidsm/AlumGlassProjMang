/* AlumGlass Global JS Utilities
 * Exposes window.AG with shared helpers for toast, loading, CSRF, forms.
 */
(function (window, document) {
    'use strict';

    const AG = window.AG || {};

    // ── Toast Notifications ────────────────────────────────────
    // Third argument can be a number (legacy) or an options object
    // supporting {duration, action:{label, onClick}}.
    AG.toast = function (message, type, opts) {
        type = type || 'info';
        let duration = 4000;
        let action = null;
        if (typeof opts === 'number') {
            duration = opts;
        } else if (opts && typeof opts === 'object') {
            if (typeof opts.duration === 'number') duration = opts.duration;
            if (opts.action && typeof opts.action.onClick === 'function') action = opts.action;
        }

        let container = document.querySelector('.ag-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'ag-toast-container';
            container.setAttribute('role', 'status');
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'ag-toast ag-toast-' + type;

        const msgSpan = document.createElement('span');
        msgSpan.className = 'ag-toast__msg';
        msgSpan.textContent = message;
        toast.appendChild(msgSpan);

        if (action) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ag-toast__action';
            btn.textContent = action.label || 'OK';
            btn.addEventListener('click', function () {
                try { action.onClick(); } catch (_) {}
                dismiss();
            });
            toast.appendChild(btn);
        }

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'ag-toast__close';
        close.setAttribute('aria-label', 'بستن');
        close.textContent = '×';
        close.addEventListener('click', dismiss);
        toast.appendChild(close);

        container.appendChild(toast);

        const timer = setTimeout(dismiss, duration);

        function dismiss() {
            clearTimeout(timer);
            toast.classList.add('ag-toast-out');
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }

        return toast;
    };

    // ── Loading Overlay ────────────────────────────────────────
    AG.showLoading = function (selector) {
        const target = typeof selector === 'string'
            ? document.querySelector(selector)
            : selector;
        if (!target) return null;

        if (getComputedStyle(target).position === 'static') {
            target.style.position = 'relative';
        }

        let overlay = target.querySelector(':scope > .ag-loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'ag-loading-overlay';
            overlay.innerHTML = '<div class="ag-spinner ag-spinner-lg"></div>';
            target.appendChild(overlay);
        }
        overlay.style.display = 'flex';
        return overlay;
    };

    AG.hideLoading = function (selector) {
        const target = typeof selector === 'string'
            ? document.querySelector(selector)
            : selector;
        if (!target) return;
        const overlay = target.querySelector(':scope > .ag-loading-overlay');
        if (overlay) overlay.style.display = 'none';
    };

    // ── CSRF Token ─────────────────────────────────────────────
    AG.getCsrfToken = function () {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    // ── Fetch with CSRF ────────────────────────────────────────
    AG.fetch = function (url, options) {
        options = options || {};
        const method = (options.method || 'GET').toUpperCase();
        const headers = Object.assign({}, options.headers || {});

        if (method !== 'GET' && method !== 'HEAD') {
            headers['X-CSRF-Token'] = AG.getCsrfToken();
        }
        if (!headers['X-Requested-With']) {
            headers['X-Requested-With'] = 'XMLHttpRequest';
        }

        return fetch(url, Object.assign({}, options, {
            method: method,
            headers: headers,
            credentials: options.credentials || 'same-origin'
        }));
    };

    // ── Confirmation Dialog ────────────────────────────────────
    AG.confirm = function (message) {
        return window.confirm(message || 'آیا مطمئن هستید؟');
    };

    // ── Number Formatting ──────────────────────────────────────
    AG.formatNumber = function (num) {
        if (num === null || num === undefined || num === '') return '';
        const n = Number(num);
        if (isNaN(n)) return String(num);
        return n.toLocaleString('fa-IR');
    };

    // ── Persian Date Formatting ────────────────────────────────
    AG.formatPersianDate = function (dateStr) {
        if (!dateStr) return '';
        try {
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleDateString('fa-IR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
        } catch (e) {
            return dateStr;
        }
    };

    // ── Debounce ───────────────────────────────────────────────
    AG.debounce = function (fn, ms) {
        let timerId;
        return function () {
            const ctx = this;
            const args = arguments;
            clearTimeout(timerId);
            timerId = setTimeout(function () {
                fn.apply(ctx, args);
            }, ms);
        };
    };

    // ── Form Auto-save to localStorage ─────────────────────────
    AG.autoSaveForm = function (formId, storageKey) {
        const form = document.getElementById(formId);
        if (!form || !window.localStorage) return;
        storageKey = storageKey || 'ag-form-' + formId;

        const save = AG.debounce(function () {
            const data = {};
            const elements = form.querySelectorAll('input, textarea, select');
            elements.forEach(function (el) {
                if (!el.name) return;
                if (el.type === 'password' || el.type === 'file') return;
                if (el.type === 'checkbox' || el.type === 'radio') {
                    data[el.name] = el.checked;
                } else {
                    data[el.name] = el.value;
                }
            });
            try {
                localStorage.setItem(storageKey, JSON.stringify(data));
            } catch (e) { /* quota exceeded */ }
        }, 500);

        form.addEventListener('input', save);
        form.addEventListener('change', save);

        form.addEventListener('submit', function () {
            try { localStorage.removeItem(storageKey); } catch (e) {}
        });
    };

    AG.restoreForm = function (formId, storageKey) {
        const form = document.getElementById(formId);
        if (!form || !window.localStorage) return false;
        storageKey = storageKey || 'ag-form-' + formId;

        let data;
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return false;
            data = JSON.parse(raw);
        } catch (e) {
            return false;
        }

        Object.keys(data).forEach(function (name) {
            const el = form.elements[name];
            if (!el) return;
            if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = !!data[name];
            } else {
                el.value = data[name];
            }
        });
        return true;
    };

    AG.clearSavedForm = function (formId, storageKey) {
        storageKey = storageKey || 'ag-form-' + formId;
        try { localStorage.removeItem(storageKey); } catch (e) {}
    };

    window.AG = AG;
})(window, document);
