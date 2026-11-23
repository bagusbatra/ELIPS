// ============================================================
// ELIPS LMS - sw.js
// Service worker PWA: network-first, tanpa cache halaman aplikasi
// dan tanpa cache API. Cache hanya dipakai sebagai fallback offline
// untuk aset statis dan halaman offline.html.
//
// Naikkan CACHE_VERSION saat strategi cache berubah. Perubahan file
// tetap langsung terlihat karena semua permintaan selalu mencoba
// jaringan terlebih dahulu.
// ============================================================

'use strict';

const CACHE_VERSION = 'v1';
const STATIC_CACHE = 'elips-static-' + CACHE_VERSION;
const OFFLINE_URL = './offline.html';

// Hanya halaman offline dan ikon yang disiapkan saat instalasi.
// Halaman aplikasi tidak pernah dimasukkan ke cache.
const PRECACHE_URLS = [
    OFFLINE_URL,
    './assets/icons/icon-192.png',
    './assets/icons/icon-512.png'
];

const CACHEABLE_EXTENSION = /\.(?:css|js|mjs|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|webmanifest|json)$/i;

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(STATIC_CACHE).then(function (cache) {
            return Promise.all(PRECACHE_URLS.map(function (url) {
                return cache.add(url).catch(function () {
                    // Satu aset gagal tidak boleh membatalkan instalasi.
                });
            }));
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (names) {
            return Promise.all(names.map(function (name) {
                if (name.indexOf('elips-') === 0 && name !== STATIC_CACHE) {
                    return caches.delete(name);
                }
                return Promise.resolve();
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('message', function (event) {
    const data = event.data || {};
    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

function offlineFallback() {
    return caches.match(OFFLINE_URL).then(function (cached) {
        if (cached) return cached;
        return new Response(
            'Anda sedang offline. Sambungkan internet lalu muat ulang halaman ini.',
            { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } }
        );
    });
}

self.addEventListener('fetch', function (event) {
    const request = event.request;
    if (!request || request.method !== 'GET') return;

    let url;
    try {
        url = new URL(request.url);
    } catch (err) {
        return;
    }

    // Permintaan lintas origin (font/CDN) diteruskan langsung ke jaringan.
    if (url.origin !== self.location.origin) return;

    // API tidak pernah disentuh service worker: selalu jaringan, tanpa cache.
    if (url.pathname.indexOf('/api/') !== -1) return;

    // Navigasi halaman: network-first. Bila offline, tampilkan offline.html.
    // Halaman aplikasi tidak pernah disimpan sehingga versi baru langsung terlihat.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function () {
                return offlineFallback();
            })
        );
        return;
    }

    // Aset statis: network-first dengan cache hanya sebagai fallback offline.
    if (!CACHEABLE_EXTENSION.test(url.pathname)) return;

    event.respondWith(
        fetch(request).then(function (response) {
            if (response && response.ok && response.type === 'basic') {
                const copy = response.clone();
                caches.open(STATIC_CACHE).then(function (cache) {
                    cache.put(request, copy).catch(function () {});
                }).catch(function () {});
            }
            return response;
        }).catch(function () {
            return caches.match(request).then(function (cached) {
                return cached || new Response('', { status: 504, statusText: 'Offline' });
            });
        })
    );
});
