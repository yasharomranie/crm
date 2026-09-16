// Minimal PWA service worker: caches only this app's own static, versioned
// assets (Vite's build output, vendored libraries, fonts, icons) so a
// repeat visit loads instantly and the app still opens (shell + styling)
// with no network at all. Deliberately does NOT touch anything else -
// Livewire's AJAX endpoints, the panel's dynamic pages, form submissions -
// this is a live CRM, and a stale cached response there would be actively
// harmful, not helpful. A fetch handler + a manifest is what actually
// makes the browser consider the app "installable"; that's the whole job
// of this file.
//
// Bump CACHE_NAME whenever the caching strategy itself changes (not on
// every deploy - static assets are already content-hashed by Vite, so
// their URLs change on their own and the cache-first handler below just
// stores the new URL alongside the old one; activate() prunes any
// *cache* left over from a previous version of this file, not individual
// stale files within the current one).

const CACHE_NAME = 'farsiwp-static-v2';
const OFFLINE_URL = '/offline.html';

const STATIC_PATH_PATTERNS = [
    /^\/build\//,
    /^\/icons\//,
    /^\/vendor\//,
    /^\/images\//,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL)),
    );
    // No self.skipWaiting() here on purpose: a new version installs and
    // waits rather than taking over immediately, so a tab with unsaved
    // form state doesn't get swapped out from under the user mid-edit.
    // pwa-register.blade.php's update banner is what lets them choose
    // when to activate it (see the "message" listener below).
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

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
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

    // Full-page navigations (clicking a link, typing a URL, opening the
    // installed app) always go to the network first - this is a live app,
    // never served stale - and only fall back to the offline shell when
    // there's truly no connection.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );

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

// Web Push only delivers an encrypted payload to this worker - actually
// showing something for it, and deciding what a tap on it does, is this
// app's own job. The payload shape here is whatever a NotificationChannels
// \WebPush\WebPushMessage builds server-side (see e.g.
// App\Notifications\NewMessageReceived) - title/body/icon plus an
// arbitrary "data" bag, here just { url } to open on click.
self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload;
    try {
        payload = event.data.json();
    } catch (error) {
        return;
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'FarsiWP', {
            body: payload.body,
            icon: payload.icon || '/icons/icon-192.png',
            badge: payload.badge || '/icons/icon-192.png',
            dir: payload.dir || 'rtl',
            lang: payload.lang || 'fa',
            tag: payload.tag,
            data: payload.data,
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        }),
    );
});
