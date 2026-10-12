/**
 * SIGAP Service Worker
 * Caching aset statis (CSS, JS, Fonts, Icons) untuk performa cepat di lapangan
 */
const CACHE_NAME = 'sigap-cache-v1';
const ASSETS_TO_CACHE = [
  './assets/css/style.css',
  './assets/js/signature.js',
  './assets/icons/icon.svg',
  './manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE).catch(() => {});
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Hanya cache GET untuk CSS, JS, dan Gambar statis
  const url = new URL(event.request.url);
  const isStatic = url.pathname.includes('/assets/') || url.pathname.endsWith('.css') || url.pathname.endsWith('.js');

  if (event.request.method === 'GET' && isStatic) {
    event.respondWith(
      caches.match(event.request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return fetch(event.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseToCache);
            });
          }
          return networkResponse;
        });
      })
    );
  }
});
