// Minimal PWA service worker for the گنجنامه CUSTOMER app - deliberately
// separate from public/sw.js (the staff admin panel's own worker): the two
// are different products for different audiences, registered with
// different scopes ('/app/' vs '/'), and must never share a cache name or
// this file's activate() would delete the other's cache (and vice versa).
// See public/sw.js's own top comment for the caching strategy this mirrors
// - only the scope and static-path allowlist differ here.

const CACHE_NAME = 'ganjnameh-app-static-v2';
const OFFLINE_URL = '/app-offline.html';

const STATIC_PATH_PATTERNS = [
    /^\/build\//,
    /^\/icons\//,
    /^\/customer-app\.webmanifest$/,
    // The smart guide's engine, model and runtime: large, rarely change, and
    // needed offline inside the museum.
    /^\/vendor\/guide\//,
    /^\/guide\/guide-engine\.js$/,
];

// Admin-managed guide data and the scanner page itself: network first (so
// edits show up), the last good copy when there is no connection.
const NETWORK_FIRST_PATHS = [/^\/guide\/data\.json$/, /^\/app\/guide$/];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL)),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE_NAME && key.startsWith('ganjnameh-app-')).map((key) => caches.delete(key)),
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

    // Full-page navigations always go to the network first - this app's
    // content is admin-managed and can change any time - and only fall
    // back to the offline shell when there's truly no connection.
    if (NETWORK_FIRST_PATHS.some((pattern) => pattern.test(url.pathname))) {
        event.respondWith(
            caches.open(CACHE_NAME).then(async (cache) => {
                try {
                    const response = await fetch(request);

                    if (response.ok) {
                        cache.put(request, response.clone());
                    }

                    return response;
                } catch (error) {
                    return (await cache.match(request)) ?? (request.mode === 'navigate' ? caches.match(OFFLINE_URL) : Response.error());
                }
            }),
        );

        return;
    }

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
