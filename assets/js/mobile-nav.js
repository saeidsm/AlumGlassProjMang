/**
 * Mounts the AlumGlass mobile bottom navigation once the DOM is ready.
 *
 * Includes: Home (dashboard), Reports, Chat (with unread badge),
 * Calendar, Profile/More. Active item is inferred from the current URL.
 *
 * The unread badge on Chat subscribes to /chat/api/conversations.php
 * on page load; it does not open a WebSocket here (cheap HTTP poll).
 */

(function () {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }

    function mount() {
        if (document.querySelector('.ag-bottom-nav')) return;

        const path = location.pathname;
        const items = [
            { key: 'home',     href: '/select_project.php', label: 'خانه', svg: iconHome(),    paths: ['/select_project.php', '/index.php'] },
            { key: 'reports',  href: guessReportsHref(),    label: 'گزارش‌ها', svg: iconReports(), paths: ['daily_reports', 'reports'] },
            { key: 'chat',     href: '/chat/',              label: 'پیام‌ها', svg: iconChat(),    paths: ['/chat/', '/messages.php'] },
            { key: 'calendar', href: guessCalendarHref(),   label: 'تقویم', svg: iconCalendar(),paths: ['calendar'] },
            { key: 'more',     href: '/profile.php',        label: 'بیشتر', svg: iconMore(),    paths: ['/profile.php', '/admin.php'] },
        ];

        const nav = document.createElement('nav');
        nav.className = 'ag-bottom-nav';
        nav.setAttribute('role', 'navigation');
        nav.setAttribute('aria-label', 'ناوبری پایین');

        nav.innerHTML = items.map((it) => {
            const active = it.paths.some((p) => path.includes(p));
            return `
                <a class="ag-bottom-nav-item ${active ? 'is-active' : ''}"
                   href="${it.href}" aria-current="${active ? 'page' : 'false'}" data-key="${it.key}">
                    ${it.svg}
                    <span>${it.label}</span>
                    ${it.key === 'chat' ? '<span class="ag-nav-badge" hidden aria-label="پیام‌های خوانده‌نشده" data-unread-badge></span>' : ''}
                </a>`;
        }).join('');

        document.body.appendChild(nav);
        document.body.classList.add('has-bottom-nav');

        // Ensure CSS is loaded even if the page didn't include it in <head>.
        ensureCss('/assets/css/mobile-nav.css');

        loadUnread(nav);
    }

    function loadUnread(nav) {
        // Only run for authenticated pages — fetch silently, swallow errors.
        fetch('/chat/api/conversations.php', { credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                if (!data || !data.success) return;
                const total = (data.conversations || [])
                    .reduce((n, c) => n + (parseInt(c.unread_count, 10) || 0), 0);
                const badge = nav.querySelector('[data-unread-badge]');
                if (!badge) return;
                if (total > 0) {
                    badge.textContent = total > 99 ? '99+' : String(total);
                    badge.hidden = false;
                } else {
                    badge.hidden = true;
                }
            })
            .catch(() => { /* ignore, not fatal */ });
    }

    function ensureCss(href) {
        if ([...document.styleSheets].some((s) => (s.href || '').includes(href))) return;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }

    function guessReportsHref() {
        const match = location.pathname.match(/^\/(ghom|pardis)\//);
        if (match) return `/${match[1]}/daily_reports_dashboard_ps.php`;
        return '/pardis/daily_reports_dashboard_ps.php';
    }

    function guessCalendarHref() {
        const match = location.pathname.match(/^\/(ghom|pardis)\//);
        if (match) return `/${match[1]}/my_calendar.php`;
        return '/ghom/my_calendar.php';
    }

    // --- Icons (Lucide-inspired, no external dependency) ---
    function iconHome() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
    }
    function iconReports() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
    }
    function iconChat() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg>';
    }
    function iconCalendar() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
    }
    function iconMore() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>';
    }
})();
