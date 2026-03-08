"use strict";

// Standalone push worker: use this if erag/laravel-pwa overwrites sw.js.
// Register in JS: navigator.serviceWorker.register('/push-sw.js', { scope: '/' });

var DEFAULT_SOUND_PATH = '/sounds/topup.mp3';

function resolveSoundPath(sound) {
    if (!sound || sound === 'default') return DEFAULT_SOUND_PATH;
    if (sound.startsWith('/') && sound.indexOf('.') !== -1) return sound;
    if (sound.startsWith('http')) return sound;
    return DEFAULT_SOUND_PATH;
}

function playNotificationSound(soundUrl) {
    if (typeof self.AudioContext === 'undefined' && typeof self.webkitAudioContext === 'undefined') return Promise.resolve();
    var path = resolveSoundPath(soundUrl);
    var base = self.location.origin;
    var url = path.startsWith('http') ? path : base + (path.startsWith('/') ? path : '/' + path);
    var Ctor = self.AudioContext || self.webkitAudioContext;
    var ctx = new Ctor();
    var resumeFirst = ctx.resume ? ctx.resume() : Promise.resolve();
    function tryPlay(attemptUrl) {
        return fetch(attemptUrl, { cache: 'no-cache' })
            .then(function (res) {
                if (!res.ok) return Promise.reject(new Error('fetch failed'));
                return res.arrayBuffer();
            })
            .then(function (arrayBuffer) {
                return new Promise(function (resolve, reject) {
                    ctx.decodeAudioData(arrayBuffer, resolve, reject);
                });
            })
            .then(function (audioBuffer) {
                var source = ctx.createBufferSource();
                source.buffer = audioBuffer;
                source.connect(ctx.destination);
                source.start(0);
            });
    }
    return resumeFirst
        .then(function () { return tryPlay(url); })
        .catch(function () {
            if (path !== DEFAULT_SOUND_PATH) return tryPlay(base + DEFAULT_SOUND_PATH);
        });
}

function delay(ms) {
    return new Promise(function (r) { setTimeout(r, ms); });
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
    const rawSound = data.sound || '';
    const sound = resolveSoundPath(rawSound);
    const url = data.url || '/';
    const options = {
        body: body,
        tag: 'push-' + (data.url || 'default'),
        data: { url: url },
        silent: false
    };
    if (sound) {
        options.sound = sound.startsWith('/') ? self.location.origin + sound : sound;
    }
    const showPromise = self.registration.showNotification(title, options);
    var soundPromise = sound
        ? showPromise.then(function () { return delay(150); }).then(function () { return playNotificationSound(sound); })
        : Promise.resolve();
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

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
