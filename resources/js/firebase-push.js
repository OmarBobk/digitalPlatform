/**
 * Firebase Cloud Messaging push setup for admin PWA.
 * Only runs when window.Laravel?.isAdmin === true.
 * Asks for notification permission first (so the prompt shows before/without PWA install),
 * then gets FCM token and registers with backend.
 *
 * Push sounds: registered in app.js (listener) so it loads before this async chunk.
 */

import { getApps, initializeApp } from 'firebase/app';
import { getMessaging, getToken, isSupported } from 'firebase/messaging';

function getConfig() {
  const apiKey = import.meta.env.VITE_FIREBASE_API_KEY;
  const appId = import.meta.env.VITE_FIREBASE_APP_ID;
  const projectId = import.meta.env.VITE_FIREBASE_PROJECT_ID;
  const messagingSenderId = import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID;
  const authDomain = import.meta.env.VITE_FIREBASE_AUTH_DOMAIN;
  const storageBucket = import.meta.env.VITE_FIREBASE_STORAGE_BUCKET;
  if (!apiKey || !appId || !messagingSenderId) return null;
  return {
    apiKey,
    appId,
    projectId: projectId || undefined,
    messagingSenderId,
    authDomain: authDomain || undefined,
    storageBucket: storageBucket || undefined,
  };
}

/**
 * Mirror of checks Firebase's isSupported() uses. Logs which one fails so we can debug.
 * @returns {Promise<{ ok: boolean, failed: string[] }>}
 */
async function checkFcmSupportRequirements() {
  const failed = [];
  if (typeof window === 'undefined') failed.push('window');
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) failed.push('serviceWorker');
  if (typeof window !== 'undefined' && !('Notification' in window)) failed.push('Notification');
  if (typeof window !== 'undefined' && !('PushManager' in window)) failed.push('PushManager');
  if (typeof fetch === 'undefined') failed.push('fetch');
  try {
    if (typeof indexedDB === 'undefined') failed.push('indexedDB');
  } catch {
    failed.push('indexedDB');
  }
  try {
    const reg = await navigator.serviceWorker.getRegistration();
    if (reg && typeof reg.showNotification !== 'function') failed.push('ServiceWorkerRegistration.showNotification');
  } catch {
    failed.push('serviceWorker.getRegistration');
  }
  return { ok: failed.length === 0, failed };
}

async function registerPush() {
  console.log('registerPush started');
  if (!('Notification' in window) || !('serviceWorker' in navigator)) return;

  // Request permission first so the browser prompt shows (before PWA install or FCM setup).
  let permission;
  try {
    permission = await Notification.requestPermission();
  } catch {
    return;
  }
  if (permission !== 'granted') return;
  console.log('registerPush permission granted');

  const config = getConfig();
  if (!config) {
    console.warn('registerPush: missing Firebase config (VITE_FIREBASE_API_KEY, APP_ID, MESSAGING_SENDER_ID)');
    return;
  }
  console.log('registerPush config ok');

  const supported = await isSupported();
  const requirementCheck = await checkFcmSupportRequirements();

  if (!supported) {
    const secure = typeof window !== 'undefined' && window.isSecureContext;
    console.warn(
      'registerPush: FCM isSupported()=false. Secure context:',
      secure,
      requirementCheck.failed.length ? 'Missing/failed: ' + requirementCheck.failed.join(', ') : '(our API checks passed)'
    );
    if (!requirementCheck.ok) {
      return;
    }
    console.log('registerPush: trying getToken anyway (our checks passed)');
  } else {
    console.log('registerPush FCM supported');
  }

  const app = getApps().length === 0 ? initializeApp(config) : getApps()[0];
  const messaging = getMessaging(app);

  let registration;
  try {
    registration = await navigator.serviceWorker.ready;
  } catch (e) {
    console.warn('registerPush: service worker not ready', e);
    return;
  }
  const vapidKey = import.meta.env.VITE_FIREBASE_VAPID_KEY;
  const options = { serviceWorkerRegistration: registration };
  if (vapidKey) options.vapidKey = vapidKey;

  let token;
  try {
    token = await getToken(messaging, options);
  } catch (e) {
    const msg = e?.message ?? String(e);
    const isVapidError = /applicationServerKey|not valid|VAPID/i.test(msg);
    if (isVapidError) {
      console.warn(
        'registerPush: invalid or missing VAPID key. Set VITE_FIREBASE_VAPID_KEY in .env: ' +
          'Firebase Console → Project settings → Cloud Messaging → Web Push certificates → Key pair.',
        e
      );
    } else {
      console.warn('registerPush: getToken failed (check VAPID key and SW scope)', e);
    }
    return;
  }
  if (!token) {
    console.warn('registerPush: getToken returned null');
    return;
  }
  console.log('registerPush token', token);
  const url = '/api/admin/push/register-token';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };
  if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
  console.log('registerPush fetching');
  await fetch(url, {
    method: 'POST',
    headers,
    credentials: 'same-origin',
    body: JSON.stringify({
      fcm_token: token,
      device_name: typeof navigator !== 'undefined' ? navigator.userAgent : null,
    }),
  });
  console.log('registerPush fetched');
}

if (window.Laravel?.isAdmin === true) {
  registerPush().catch((e) => console.warn('registerPush error', e));
}
