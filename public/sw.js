/**
 * Service worker de la PWA.
 *
 * Reglas: los assets con hash se cachean para siempre (cache-first), la
 * navegación va a la red y cae en /offline si no hay cobertura (network-first),
 * y las peticiones a la API nunca se cachean — un saldo cacheado es un saldo
 * equivocado.
 *
 * Aviso sobre iOS: Safari borra este almacenamiento tras unos 7 días sin usar
 * la aplicación. El servidor es siempre la única fuente de verdad.
 */
const VERSION = 'v1';
const SHELL_CACHE = `shell-${VERSION}`;
const ASSET_CACHE = `assets-${VERSION}`;
const OFFLINE_URL = '/offline';

const SHELL_FILES = [OFFLINE_URL, '/manifest.webmanifest', '/icons/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL_FILES))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== SHELL_CACHE && key !== ASSET_CACHE)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Nunca cachear datos: saldos, estimaciones y búsquedas siempre frescos
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/internal/')) {
        return;
    }

    // Assets compilados por Vite: llevan hash en el nombre, cache-first sin miedo
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        const copy = response.clone();
                        caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));
                        return response;
                    }),
            ),
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL)),
            ),
        );
    }
});
