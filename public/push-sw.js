"use strict";

// NOT REGISTERED. The app registers sw.js via erag/laravel-pwa (@RegisterServiceWorkerScript).
// Push handling (vibrate, sound, logs) lives in public/sw.js. This file is reference/backup only.

var DEFAULT_SOUND_PATH = '/sounds/topup.mp3';

function resolveSoundPath(sound) {
    if (!sound || sound === 'default') return DEFAULT_SOUND_PATH;
    if (sound.startsWith('/') && sound.indexOf('.') !== -1) return sound;
    if (sound.startsWith('http')) return sound;
    return DEFAULT_SOUND_PATH;
}

function playNotificationSound(soundUrl) {
    console.log('[push-sw] playNotificationSound called', { soundUrl: soundUrl });
    if (typeof self.AudioContext === 'undefined' && typeof self.webkitAudioContext === 'undefined') {
        console.log('[push-sw] no AudioContext/webkitAudioContext, skip sound');
        return Promise.resolve();
    }
    var path = resolveSoundPath(soundUrl);
    var base = self.location.origin;
    var url = path.startsWith('http') ? path : base + (path.startsWith('/') ? path : '/' + path);
    console.log('[push-sw] sound url', { path: path, url: url });
    var Ctor = self.AudioContext || self.webkitAudioContext;
    var ctx = new Ctor();
    var resumeFirst = ctx.resume ? ctx.resume() : Promise.resolve();
    function tryPlay(attemptUrl) {
        console.log('[push-sw] fetch sound', attemptUrl);
        return fetch(attemptUrl, { cache: 'no-cache' })
            .then(function (res) {
                console.log('[push-sw] fetch result', { url: attemptUrl, ok: res.ok, status: res.status });
                if (!res.ok) return Promise.reject(new Error('fetch failed ' + res.status));
                return res.arrayBuffer();
            })
            .then(function (arrayBuffer) {
                console.log('[push-sw] decodeAudioData, length=', arrayBuffer.byteLength);
                return new Promise(function (resolve, reject) {
                    ctx.decodeAudioData(arrayBuffer, resolve, reject);
                });
            })
            .then(function (audioBuffer) {
                console.log('[push-sw] playing audio buffer, duration=', audioBuffer.duration);
                var source = ctx.createBufferSource();
                source.buffer = audioBuffer;
                source.connect(ctx.destination);
                source.start(0);
                console.log('[push-sw] source.start(0) called');
            });
    }
    return resumeFirst
        .then(function () {
            console.log('[push-sw] AudioContext resumed');
            return tryPlay(url);
        })
        .then(function () { console.log('[push-sw] sound played ok'); })
        .catch(function (err) {
            console.log('[push-sw] sound play failed', err?.message || err);
            if (path !== DEFAULT_SOUND_PATH) {
                console.log('[push-sw] trying fallback', base + DEFAULT_SOUND_PATH);
                return tryPlay(base + DEFAULT_SOUND_PATH);
            }
        });
}

function delay(ms) {
    return new Promise(function (r) { setTimeout(r, ms); });
}

self.addEventListener('push', (event) => {
    console.log('[push-sw] push event', event.data ? 'has data' : 'no data');
    if (!event.data) return;
    let payload;
    try {
        payload = event.data.json();
    } catch (e) {
        console.log('[push-sw] payload parse error', e);
        return;
    }
    const data = payload.data || (payload.message && payload.message.data) || payload;
    const title = data.title || 'Notification';
    const body = data.body || '';
    const rawSound = data.sound || '';
    const sound = resolveSoundPath(rawSound);
    const url = data.url || '/';
    console.log('[push-sw] notification', { title: title, rawSound: rawSound, sound: sound });
    const options = {
        body: body,
        data: { url: url },
        silent: false,
        vibrate: [400, 100, 400, 100, 400]
    };
    if (sound) {
        options.sound = sound.startsWith('/') ? self.location.origin + sound : sound;
    }
    console.log('[push-sw] showNotification', { title: title, options: options });
    const showPromise = self.registration.showNotification(title, options)
        .then(function () { console.log('[push-sw] showNotification done'); })
        .catch(function (e) { console.log('[push-sw] showNotification failed', e); });
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
