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
    if (!soundUrl || typeof self.AudioContext === 'undefined' && typeof self.webkitAudioContext === 'undefined') return Promise.resolve();
    const base = self.location.origin;
    const url = soundUrl.startsWith('http') ? soundUrl : (soundUrl.startsWith('/') ? base + soundUrl : base + '/' + soundUrl);
    const Ctor = self.AudioContext || self.webkitAudioContext;
    const ctx = new Ctor();
    const resumeFirst = ctx.resume ? ctx.resume() : Promise.resolve();
    return resumeFirst.then(function () {
        return fetch(url, { cache: 'no-cache' });
    }).then(function (res) {
        if (!res.ok) return Promise.reject(new Error('fetch failed'));
        return res.arrayBuffer();
    }).then(function (arrayBuffer) {
        return ctx.decodeAudioData(arrayBuffer);
    }).then(function (audioBuffer) {
        const source = ctx.createBufferSource();
        source.buffer = audioBuffer;
        source.connect(ctx.destination);
        source.start(0);
    }).catch(function () {});
}

self.addEventListener('push', (event) => {
    if (!event.data) return;
    let payload;
    try {
        payload = event.data.json();
    } catch {
        return;
    }
    const data = payload.data || (payload.message && payload.message.data) || payload;
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
