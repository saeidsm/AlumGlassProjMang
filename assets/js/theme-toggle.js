/**
 * AlumGlass theme toggle
 *
 * Reads the user's stored preference on page load (synchronously, to
 * avoid a flash of light theme), exposes a button that flips between
 * light and dark, and persists the choice in localStorage.
 *
 * The preference is applied by setting `data-theme` on <html>. When no
 * manual preference is set, the system preference is respected via CSS
 * media query (see /assets/css/dark-mode.css).
 *
 * This file is intentionally self-contained and ES5-compatible so it
 * can be loaded without `type=module`.
 */

(function () {
    var STORAGE_KEY = 'ag-theme';

    // 1. Apply stored theme before paint to avoid a flash.
    try {
        var saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'dark' || saved === 'light') {
            document.documentElement.setAttribute('data-theme', saved);
        }
    } catch (_) { /* private mode / disabled */ }

    // 2. On DOM ready, inject a toggle button into the nav (or body).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectToggle);
    } else {
        injectToggle();
    }

    function injectToggle() {
        if (document.querySelector('.ag-theme-toggle')) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ag-theme-toggle';
        btn.setAttribute('aria-label', 'تغییر تم');
        btn.setAttribute('title', 'تغییر تم تیره/روشن');
        btn.innerHTML =
            '<svg class="ag-theme-toggle__moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>' +
            '</svg>' +
            '<svg class="ag-theme-toggle__sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                '<circle cx="12" cy="12" r="5"/>' +
                '<line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>' +
                '<line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>' +
                '<line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>' +
                '<line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>' +
            '</svg>';
        btn.addEventListener('click', toggle);

        // Prefer nav if it exists; fall back to fixed bottom-right corner.
        var nav = document.querySelector('.navbar-common .navbar-nav, nav.navbar .navbar-nav');
        if (nav) {
            var li = document.createElement('li');
            li.className = 'nav-item d-flex align-items-center';
            li.style.marginInlineStart = '6px';
            li.appendChild(btn);
            nav.appendChild(li);
        } else {
            btn.style.position = 'fixed';
            btn.style.insetInlineStart = '16px';
            btn.style.bottom = 'calc(76px + env(safe-area-inset-bottom, 0))';
            btn.style.zIndex = '1001';
            document.body.appendChild(btn);
        }
    }

    function toggle() {
        var current = document.documentElement.getAttribute('data-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem(STORAGE_KEY, next); } catch (_) {}
        document.dispatchEvent(new CustomEvent('ag:theme-change', { detail: { theme: next } }));
    }

    window.AG = window.AG || {};
    window.AG.setTheme = function (theme) {
        if (theme !== 'light' && theme !== 'dark') return;
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem(STORAGE_KEY, theme); } catch (_) {}
    };
})();
