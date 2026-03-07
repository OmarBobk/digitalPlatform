/**
 * Firebase Cloud Messaging push setup for admin PWA.
 * Only runs when window.Laravel?.isAdmin === true.
 * Requests notification permission, gets FCM token, registers with backend.
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

async function registerPush() {
  const config = getConfig();
  if (!config) return;

  const supported = await isSupported();
  if (!supported) return;

  const app = getApps().length === 0 ? initializeApp(config) : getApps()[0];
  const messaging = getMessaging(app);

  try {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return;
  } catch {
    return;
  }

  const registration = await navigator.serviceWorker.ready;
  const vapidKey = import.meta.env.VITE_FIREBASE_VAPID_KEY;
  const options = { serviceWorkerRegistration: registration };
  if (vapidKey) options.vapidKey = vapidKey;
  const token = await getToken(messaging, options);
  if (!token) return;

  const url = '/api/admin/push/register-token';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };
  if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

  await fetch(url, {
    method: 'POST',
    headers,
    credentials: 'same-origin',
    body: JSON.stringify({
      fcm_token: token,
      device_name: typeof navigator !== 'undefined' ? navigator.userAgent : null,
    }),
  });
}

if (window.Laravel?.isAdmin === true) {
  registerPush().catch(() => {});
}
