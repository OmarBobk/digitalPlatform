"use strict";

const CACHE_NAME = "offline-cache-v3";
const OFFLINE_URL = '/offline.html';

const filesToCache = [
    OFFLINE_URL
];

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(filesToCache))
    );
    self.skipWaiting();
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
        }).then(function () {
            return self.clients.claim();
        })
    );
});

// --- Push notification (FCM) ---
const DEFAULT_SOUND_PATH = '/sounds/fulfillment.mp3';
/** Same-origin tabs listen in app.js; works when clients.matchAll returns 0 (common with FCM + PWA). */
const PUSH_SOUND_BROADCAST = 'karman-push-sound';
const PUSH_SOUND_MSG_TYPE = 'KARMAN_PLAY_PUSH_SOUND';

function resolveSoundPath(sound) {
    if (!sound || sound === 'default') return DEFAULT_SOUND_PATH;
    if (sound.startsWith('/') && sound.indexOf('.') !== -1) return sound;
    if (sound.startsWith('http')) return sound;
    return DEFAULT_SOUND_PATH;
}

// Custom sound via Web Audio only works when AudioContext exists in the SW (often not on mobile).
// We still set options.sound and silent:false on the notification so the device may play system default.
function playNotificationSound(soundUrl) {
    console.log('[sw] playNotificationSound', { soundUrl: soundUrl });
    if (typeof self.AudioContext === 'undefined' && typeof self.webkitAudioContext === 'undefined') {
        console.log('[sw] AudioContext not available in this service worker (common on mobile) - custom Web Audio skipped. Notification uses options.sound + silent:false.');
        return Promise.resolve();
    }
    const path = resolveSoundPath(soundUrl);
    const base = self.location.origin;
    const url = path.startsWith('http') ? path : base + (path.startsWith('/') ? path : '/' + path);
    console.log('[sw] sound url', { path: path, url: url });
    const Ctor = self.AudioContext || self.webkitAudioContext;
    const ctx = new Ctor();
    const resumeFirst = ctx.resume ? ctx.resume() : Promise.resolve();
    function tryPlay(attemptUrl) {
        console.log('[sw] fetch sound', attemptUrl);
        return fetch(attemptUrl, { cache: 'no-cache' })
            .then(function (res) {
                console.log('[sw] fetch result', { ok: res.ok, status: res.status });
                if (!res.ok) return Promise.reject(new Error('fetch failed ' + res.status));
                return res.arrayBuffer();
            })
            .then(function (arrayBuffer) {
                console.log('[sw] decodeAudioData, length=', arrayBuffer.byteLength);
                return new Promise(function (resolve, reject) {
                    ctx.decodeAudioData(arrayBuffer, resolve, reject);
                });
            })
            .then(function (audioBuffer) {
                console.log('[sw] playing, duration=', audioBuffer.duration);
                const source = ctx.createBufferSource();
                source.buffer = audioBuffer;
                source.connect(ctx.destination);
                source.start(0);
                console.log('[sw] source.start(0) done');
            });
    }
    return resumeFirst
        .then(function () { return tryPlay(url); })
        .then(function () { console.log('[sw] sound played ok'); })
        .catch(function (err) {
            console.log('[sw] sound failed', err && err.message ? err.message : err);
            if (path !== DEFAULT_SOUND_PATH) {
                console.log('[sw] fallback', base + DEFAULT_SOUND_PATH);
                return tryPlay(base + DEFAULT_SOUND_PATH);
            }
        });
}

function delay(ms) {
    return new Promise(function (r) { setTimeout(r, ms); });
}

/**
 * Deliver sound URL to open tabs: BroadcastChannel (reliable when matchAll is empty) + postMessage to clients.
 *
 * @returns {Promise<{ pageMayPlay: boolean }>}
 */
function notifyClientsToPlaySound(resolvedPath) {
    const base = self.location.origin;
    const path = resolveSoundPath(resolvedPath);
    const fullUrl = path.startsWith('http') ? path : base + (path.startsWith('/') ? path : '/' + path);

    var broadcastSent = false;
    try {
        if (typeof BroadcastChannel !== 'undefined') {
            var bc = new BroadcastChannel(PUSH_SOUND_BROADCAST);
            bc.postMessage({ type: PUSH_SOUND_MSG_TYPE, url: fullUrl });
            bc.close();
            broadcastSent = true;
            console.log('[sw] BroadcastChannel push sound', fullUrl);
        }
    } catch (e) {
        console.log('[sw] BroadcastChannel failed', e);
    }

    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
        var n = clientList ? clientList.length : 0;
        if (n === 0) {
            console.log('[sw] clients.matchAll: 0 window clients (BroadcastChannel still notified listeners)');
        } else {
            console.log('[sw] posting', PUSH_SOUND_MSG_TYPE, 'to', n, 'client(s)');
        }
        if (clientList) {
            clientList.forEach(function (client) {
                try {
                    client.postMessage({ type: PUSH_SOUND_MSG_TYPE, url: fullUrl });
                } catch (e) {
                    console.log('[sw] client.postMessage failed', e);
                }
            });
        }
        return { pageMayPlay: n > 0 || broadcastSent };
    });
}

self.addEventListener('push', (event) => {
    console.log('[sw] push event', event.data ? 'has data' : 'no data');
    if (!event.data) return;
    let payload;
    try {
        payload = event.data.json();
    } catch (e) {
        console.log('[sw] payload parse error', e);
        return;
    }
    const data = payload.data || (payload.message && payload.message.data) || payload;
    const title = data.title || 'Notification';
    const body = data.body || '';
    const rawSound = data.sound || '';
    const sound = resolveSoundPath(rawSound);
    const url = data.url || '/';
    console.log('[sw] notification', { title: title, rawSound: rawSound, sound: sound });
    const options = {
        body: body,
        data: { url: url },
        silent: false,
        vibrate: [400, 100, 400, 100, 400]
    };
    if (sound) {
        options.sound = sound.startsWith('/') ? self.location.origin + sound : sound;
    }
    const showPromise = self.registration.showNotification(title, options)
        .then(function () { console.log('[sw] showNotification done'); })
        .catch(function (e) { console.log('[sw] showNotification failed', e); });

    const soundPromise = sound
        ? notifyClientsToPlaySound(rawSound || sound).then(function (result) {
            if (result && result.pageMayPlay) {
                console.log('[sw] sound delegated to page (BroadcastChannel and/or postMessage) — skipping SW Web Audio');
                return Promise.resolve();
            }
            return delay(150).then(function () { return playNotificationSound(sound); });
        })
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
