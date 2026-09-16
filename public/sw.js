const CACHE = 'mandaditos-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add(OFFLINE_URL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== CACHE)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

// Network-first for page navigations, with an offline fallback.
self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
    }
});

// Web Push: show the incoming notification.
self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'Mandaditos app';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        data: { url: (payload.data && payload.data.url) || '/dashboard' },
        tag: payload.tag,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// Focus/open the relevant page when a notification is clicked.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl =
        (event.notification.data && event.notification.data.url) ||
        '/dashboard';

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                for (const client of clientList) {
                    if (client.url.includes(targetUrl) && 'focus' in client) {
                        return client.focus();
                    }
                }

                if (self.clients.openWindow) {
                    return self.clients.openWindow(targetUrl);
                }

                return undefined;
            }),
    );
});
