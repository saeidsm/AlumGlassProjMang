/**
 * AlumGlass service worker
 *
 * Strategies:
 *  - navigation requests: network-first, fall back to /offline.html
 *  - same-origin static assets (css/js/woff2/png/svg/jpg): stale-while-revalidate
 *  - /chat/api/ and /api/: network-only (never cache live data)
 *  - /storage/ immutable blobs: cache-first (the URL already contains content hash)
 *
 * Bump CACHE_VERSION on each release so old caches get evicted cleanly.
 */

const CACHE_VERSION = 'ag-v2.0.0';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;
const IMMUTABLE_CACHE = `${CACHE_VERSION}-immutable`;

const PRECACHE_URLS = [
    '/offline.html',
    '/assets/css/design-system.css',
    '/assets/css/global.css',
    '/assets/css/mobile-nav.css',
    '/assets/css/responsive-tables.css',
    '/assets/css/touch-gestures.css',
    '/assets/js/global.js',
    '/assets/js/mobile-nav.js',
    '/assets/js/responsive-tables.js',
    '/assets/js/touch-gestures.js',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) =>
            cache.addAll(PRECACHE_URLS).catch(() => { /* tolerate missing files */ })
        ).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((k) => !k.startsWith(CACHE_VERSION)).map((k) => caches.delete(k))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== location.origin) return;

    // 1. Never cache live API
    if (url.pathname.startsWith('/chat/api/') || url.pathname.startsWith('/api/')) {
        return; // use default network
    }

    // 2. Immutable blob storage — cache-first (content-addressable)
    if (url.pathname.startsWith('/storage/')) {
        event.respondWith(cacheFirst(IMMUTABLE_CACHE, request));
        return;
    }

    // 3. Navigation → network-first with offline fallback
    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    // 4. Static assets → stale-while-revalidate
    if (isStaticAsset(url.pathname)) {
        event.respondWith(staleWhileRevalidate(RUNTIME_CACHE, request));
    }
});

function isStaticAsset(pathname) {
    return /\.(css|js|woff2?|ttf|eot|png|jpe?g|gif|svg|ico|webp|webmanifest)$/i.test(pathname);
}

async function cacheFirst(cacheName, request) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    if (cached) return cached;
    try {
        const res = await fetch(request);
        if (res.ok) cache.put(request, res.clone());
        return res;
    } catch (err) {
        return new Response('offline', { status: 503 });
    }
}

async function staleWhileRevalidate(cacheName, request) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const fetchPromise = fetch(request).then((res) => {
        if (res && res.ok) cache.put(request, res.clone());
        return res;
    }).catch(() => cached);
    return cached || fetchPromise;
}

async function networkFirstNavigation(request) {
    try {
        return await fetch(request);
    } catch (err) {
        const cache = await caches.open(STATIC_CACHE);
        const offline = await cache.match('/offline.html');
        return offline || new Response('Offline', {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
}
