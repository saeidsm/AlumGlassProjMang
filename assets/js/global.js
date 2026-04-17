/* AlumGlass Global JS Utilities
 * Exposes window.AG with shared helpers for toast, loading, CSRF, forms.
 */
(function (window, document) {
    'use strict';

    const AG = window.AG || {};

    // ── Toast Notifications ────────────────────────────────────
    AG.toast = function (message, type, duration) {
        type = type || 'info';
        duration = duration || 4000;

        let container = document.querySelector('.ag-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'ag-toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'ag-toast ag-toast-' + type;
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(function () {
            toast.classList.add('ag-toast-out');
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }, duration);

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
