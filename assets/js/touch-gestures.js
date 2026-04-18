/**
 * Touch gesture helpers for mobile.
 *
 * Features:
 * - Pull-to-refresh on elements tagged `data-ag-pull-refresh`.
 *   The element's reload handler is controlled by a data attribute
 *   (data-ag-refresh-action: "reload" triggers full page reload).
 * - Swipe detection: exposes `AG.onSwipe(el, {direction, handler})`.
 * - Suppresses bounce-scroll when pulling down to refresh.
 *
 * All gestures are opt-in; nothing fires unless the element opts in.
 */

(function () {
    window.AG = window.AG || {};

    const PULL_THRESHOLD = 70;        // px pull before refresh fires
    const PULL_MAX = 140;

    document.querySelectorAll('[data-ag-pull-refresh]').forEach(setupPullRefresh);

    // --- Pull to refresh ---

    function setupPullRefresh(root) {
        const action = root.dataset.agRefreshAction || 'reload';
        const indicator = createPullIndicator(root);

        let startY = 0;
        let pulling = false;
        let pullY = 0;

        root.addEventListener('touchstart', (e) => {
            if (root.scrollTop > 0) return;
            startY = e.touches[0].clientY;
            pulling = true;
        }, { passive: true });

        root.addEventListener('touchmove', (e) => {
            if (!pulling) return;
            const dy = e.touches[0].clientY - startY;
            if (dy <= 0) { reset(); return; }
            pullY = Math.min(dy, PULL_MAX);
            indicator.style.transform = `translateY(${pullY}px)`;
            indicator.style.opacity = Math.min(pullY / PULL_THRESHOLD, 1).toString();
            if (pullY > 30) indicator.classList.add('is-visible');
        }, { passive: true });

        root.addEventListener('touchend', () => {
            if (!pulling) return;
            if (pullY >= PULL_THRESHOLD) triggerRefresh(root, action, indicator);
            else reset();
            pulling = false;
            pullY = 0;
        }, { passive: true });

        function reset() {
            indicator.style.transform = '';
            indicator.style.opacity = '';
            indicator.classList.remove('is-visible');
        }
    }

    function createPullIndicator(root) {
        let el = root.querySelector('.ag-pull-indicator');
        if (el) return el;
        el = document.createElement('div');
        el.className = 'ag-pull-indicator';
        el.innerHTML = '<span class="ag-pull-spin" aria-hidden="true"></span><span>برای بازخوانی رها کنید</span>';
        root.prepend(el);
        return el;
    }

    function triggerRefresh(root, action, indicator) {
        indicator.classList.add('is-loading');
        if (action === 'reload') {
            setTimeout(() => location.reload(), 200);
            return;
        }
        const evt = new CustomEvent('ag:refresh', { bubbles: true, cancelable: true });
        root.dispatchEvent(evt);
        // Give the handler 2s to respond, then reset.
        setTimeout(() => {
            indicator.classList.remove('is-loading', 'is-visible');
            indicator.style.transform = '';
            indicator.style.opacity = '';
        }, 2000);
    }

    // --- Swipe helper ---

    AG.onSwipe = function (el, { direction = 'left', threshold = 50, handler }) {
        if (!el || typeof handler !== 'function') return () => {};

        let startX = 0;
        let startY = 0;
        let startT = 0;

        function onStart(e) {
            const t = e.touches[0];
            startX = t.clientX; startY = t.clientY; startT = Date.now();
        }
        function onEnd(e) {
            const t = (e.changedTouches || e.touches || [])[0];
            if (!t) return;
            const dx = t.clientX - startX;
            const dy = t.clientY - startY;
            const dt = Date.now() - startT;
            if (dt > 800) return;
            const isHorizontal = Math.abs(dx) > Math.abs(dy);
            if ((direction === 'left'  && isHorizontal && dx <= -threshold) ||
                (direction === 'right' && isHorizontal && dx >=  threshold) ||
                (direction === 'up'    && !isHorizontal && dy <= -threshold) ||
                (direction === 'down'  && !isHorizontal && dy >=  threshold)) {
                handler(e);
            }
        }
        el.addEventListener('touchstart', onStart, { passive: true });
        el.addEventListener('touchend', onEnd, { passive: true });
        return () => {
            el.removeEventListener('touchstart', onStart);
            el.removeEventListener('touchend', onEnd);
        };
    };

    // --- Ensure 44x44 touch targets on small screens (safety net) ---

    if (matchMedia('(max-width: 768px)').matches) {
        document.documentElement.classList.add('ag-touch');
    }
})();
