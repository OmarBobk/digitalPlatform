"use strict";

const CACHE_NAME = "offline-cache-v1";
const OFFLINE_URL = '/offline.html';

const filesToCache = [
    OFFLINE_URL
];

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(filesToCache))
    );
});

self.addEventListener("fetch", (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    return caches.match(OFFLINE_URL);
                })
        );
    } else {
        event.respondWith(
            caches.match(event.request)
                .then((response) => {
                    return response || fetch(event.request);
                })
        );
    }
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

function playNotificationSound(soundUrl) {
    if (!soundUrl) return Promise.resolve();
    const url = soundUrl.startsWith('/') ? self.location.origin + soundUrl : soundUrl;
    return fetch(url)
        .then((res) => (res.ok ? res.arrayBuffer() : Promise.reject(new Error('fetch failed'))))
        .then((arrayBuffer) => {
            try {
                const ctx = new (self.AudioContext || self.webkitAudioContext)();
                return ctx.decodeAudioData(arrayBuffer).then((audioBuffer) => {
                    const source = ctx.createBufferSource();
                    source.buffer = audioBuffer;
                    source.connect(ctx.destination);
                    const resumePromise = ctx.resume ? ctx.resume() : Promise.resolve();
                    return resumePromise.then(() => {
                        source.start(0);
                    });
                });
            } catch (e) {
                return Promise.reject(e);
            }
        })
        .catch(() => {});
}

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
    const showPromise = self.registration.showNotification(title, options);
    const soundPromise = sound ? playNotificationSound(sound) : Promise.resolve();
    event.waitUntil(Promise.all([showPromise, soundPromise]));
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
