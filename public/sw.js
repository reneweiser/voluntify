/**
 * Voluntify Scanner — Service Worker
 *
 * Strategy:
 * - Cache-first for static assets (CSS, JS, fonts, images)
 * - Network-first for API requests (scanner data/sync)
 * - Offline HTML fallback for navigation requests
 */

const CACHE_NAME = 'voluntify-scanner-v1';
const STATIC_ASSETS = [
    '/admin/scanner',
    '/admin/scanner/lookup',
];

// Install — pre-cache shell
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

// Activate — clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// Fetch — route by request type
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // API requests: network-first
    if (url.pathname.includes('/scanner/api/')) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    // Static assets: cache-first
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(event.request));
        return;
    }

    // Navigation: network-first with offline fallback
    if (event.request.mode === 'navigate') {
        event.respondWith(networkFirst(event.request));
        return;
    }

    // Default: network
    event.respondWith(fetch(event.request));
});

function isStaticAsset(pathname) {
    return /\.(css|js|woff2?|png|jpg|svg|ico)$/.test(pathname) || pathname.startsWith('/build/');
}

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Offline', { status: 503 });
    }
}

async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }
        return new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
    }
}
