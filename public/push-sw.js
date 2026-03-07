"use strict";

// Standalone push worker: use this if erag/laravel-pwa overwrites sw.js.
// Register in JS: navigator.serviceWorker.register('/push-sw.js', { scope: '/' });

self.addEventListener('push', (event) => {
    if (!event.data) return;
    let payload;
    try {
        payload = event.data.json();
    } catch {
        return;
    }
    const data = payload.data || payload;
    const title = data.title || 'Notification';
    const body = data.body || '';
    const sound = data.sound || '';
    const url = data.url || '/';
    const options = {
        body: body,
        tag: 'push-' + (data.url || 'default'),
        data: { url: url }
    };
    if (sound) {
        options.silent = false;
        options.sound = sound.startsWith('/') ? self.location.origin + sound : sound;
    }
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url;
    if (!url) return;
    const fullUrl = url.startsWith('http') ? url : self.location.origin + (url.startsWith('/') ? url : '/' + url);
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url && 'focus' in client) {
                    client.navigate(fullUrl);
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(fullUrl);
            }
        })
    );
});

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
