'use strict';

const CACHE_NAME = 'digiwtal-v1';
const OFFLINE_URL = '/admin/dashboard';

// Beim Install: Admin-Shell cachen (nur statische Assets, keine PHP-Seiten)
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            // Nur den Offline-Fallback cachen – keine PHP-Seiten (Auth-State)
            return cache.add(OFFLINE_URL).catch(function() {
                // Schlägt fehl wenn nicht eingeloggt – ignorieren
            });
        })
    );
    self.skipWaiting();
});

// Beim Activate: Alte Caches löschen
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(key) { return key !== CACHE_NAME; })
                    .map(function(key) { return caches.delete(key); })
            );
        })
    );
    self.clients.claim();
});

// Fetch: Network-first für PHP-Seiten, Cache-first für statische Assets
self.addEventListener('fetch', function(event) {
    var url = new URL(event.request.url);

    // Nur eigene Domain
    if (url.origin !== location.origin) return;

    // POST-Requests nie cachen
    if (event.request.method !== 'GET') return;

    // PHP-Admin-Seiten: Network-first
    if (url.pathname.startsWith('/admin/')) {
        event.respondWith(
            fetch(event.request).catch(function() {
                return caches.match(OFFLINE_URL).then(function(cached) {
                    if (cached) return cached;
                    return new Response(
                        '<html><body style="font-family:system-ui;text-align:center;padding:40px">' +
                        '<h1>Offline</h1><p>Bitte Internetverbindung prüfen.</p></body></html>',
                        { headers: { 'Content-Type': 'text/html' } }
                    );
                });
            })
        );
        return;
    }

    // ping.php: immer Network
    if (url.pathname === '/ping.php') return;
});

// Push Notifications (Schritt 6c – Platzhalter)
self.addEventListener('push', function(event) {
    if (!event.data) return;
    var data = event.data.json();
    event.waitUntil(
        self.registration.showNotification(data.title || 'DIGIWTAL', {
            body: data.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: data.tag || 'digiwtal-notification',
            data: { url: data.url || '/admin/dashboard' }
        })
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    var targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/admin/dashboard';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (var i = 0; i < clientList.length; i++) {
                if (clientList[i].url === targetUrl && 'focus' in clientList[i]) {
                    return clientList[i].focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(targetUrl);
        })
    );
});
