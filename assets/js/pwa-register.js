/**
 * Registers the AlumGlass service worker and fires an "update available"
 * toast when a new version has been installed. Opt-in — include via
 *   <script src="/assets/js/pwa-register.js" defer></script>
 */

(function () {
    if (!('serviceWorker' in navigator)) return;
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        // SW only registers over HTTPS (or localhost) — skip silently on plain HTTP dev.
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
            .then((reg) => {
                if (reg.waiting) notifyUpdate(reg);
                reg.addEventListener('updatefound', () => {
                    const installing = reg.installing;
                    installing?.addEventListener('statechange', () => {
                        if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                            notifyUpdate(reg);
                        }
                    });
                });
            })
            .catch((err) => console.warn('[pwa] registration failed:', err));

        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (refreshing) return;
            refreshing = true;
            location.reload();
        });
    });

    function notifyUpdate(reg) {
        if (window.AG?.toast) {
            window.AG.toast('نسخه جدیدی از برنامه آماده است. برای فعال‌سازی تازه‌سازی کنید.', 'info', {
                action: { label: 'تازه‌سازی', onClick: () => reg.waiting?.postMessage({ type: 'SKIP_WAITING' }) },
            });
        } else {
            console.info('[pwa] update available — call reg.waiting.postMessage({type:"SKIP_WAITING"}) to activate');
        }
    }
})();
