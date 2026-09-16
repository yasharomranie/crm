// Minimal PWA service worker: caches only this app's own static, versioned
// assets (Vite's build output, vendored libraries, fonts, icons) so a
// repeat visit loads instantly and the app still opens (shell + styling)
// with no network at all. Deliberately does NOT touch anything else -
// Livewire's AJAX endpoints, the panel's dynamic pages, form submissions -
// this is a live CRM, and a stale cached response there would be actively
// harmful, not helpful. A fetch handler + a manifest is what actually
// makes the browser consider the app "installable"; that's the whole job
// of this file.

const CACHE_NAME = 'farsiwp-static-v1';

const STATIC_PATH_PATTERNS = [
    /^\/build\//,
    /^\/icons\//,
    /^\/vendor\//,
    /^\/images\//,
];

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (!STATIC_PATH_PATTERNS.some((pattern) => pattern.test(url.pathname))) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);

            if (response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        }),
    );
});
