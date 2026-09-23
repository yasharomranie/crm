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

const CACHE_NAME = 'farsiwp-static-v4';
const OFFLINE_URL = '/offline.html';

const STATIC_PATH_PATTERNS = [
    /^\/build\//,
    /^\/icons\//,
    /^\/vendor\//,
    /^\/images\//,
    // Filament's own core JS/CSS/fonts (dropdowns, modals, the sidebar's
    // Alpine components - every page depends on these) are published
    // under these paths, not /build/ - without them, a page served from
    // OFFLINE_CACHEABLE_PATHS's cache still fails to actually work once
    // rendered, since the framework JS it needs never loaded.
    /^\/css\/filament\//,
    /^\/js\/filament\//,
    /^\/fonts\/filament\//,
];

// Pages worth showing a last-known snapshot of when there's truly no
// network, instead of the generic offline shell - deliberately a short,
// explicit allowlist rather than every navigation: this app is live data,
// and a stale cached copy of most pages (a form mid-edit, a list someone
// else has since changed) would mislead more than help. A read-only status
// board like the room rack is the case where "possibly a few minutes old"
// beats "nothing at all". See room-rack.blade.php's own script for how it
// tells the viewer the copy is stale.
const OFFLINE_CACHEABLE_PATHS = [
    '/admin/room-rack',
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
        if (OFFLINE_CACHEABLE_PATHS.includes(url.pathname)) {
            // Cache.put()/match() reject a Request whose mode is "navigate"
            // (every full-page navigation request is exactly that) with
            // "Request mode 'navigate' is unsupported" - thrown async here,
            // inside a .then() with nothing awaiting or catching it, so it
            // failed completely silently: the page rendered fine while
            // online, nothing ever actually landed in the cache, and the
            // very next offline visit had nothing to fall back to. Passing
            // the URL string instead of the Request object sidesteps the
            // restriction entirely - the Cache API builds its own
            // default-mode Request from it internally.
            event.respondWith(
                fetch(request)
                    .then((response) => {
                        if (response.ok) {
                            caches.open(CACHE_NAME).then((cache) => cache.put(request.url, response.clone()));
                        }

                        return response;
                    })
                    .catch(async () => (await caches.match(request.url)) || caches.match(OFFLINE_URL)),
            );

            return;
        }

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
