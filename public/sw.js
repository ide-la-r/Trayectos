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

/* ─── Avisos ─────────────────────────────────────────────────────────────────
   Esto corre con la aplicación cerrada: el navegador despierta al service
   worker sólo para esto. Nada de aquí puede fallar sin enseñar un aviso,
   porque iOS y Android penalizan al que recibe un push y no muestra nada — te
   pueden dejar de mandar los siguientes.
   ────────────────────────────────────────────────────────────────────────── */

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        // Un aviso ilegible sigue siendo un aviso: mejor genérico que ninguno
    }

    const title = payload.title || 'Libro de Trayectos';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: payload.tag || undefined,
            // Con la misma etiqueta el nuevo sustituye al viejo, pero en
            // silencio: sustituir no debe volver a vibrar el móvil.
            renotify: false,
            data: { url: payload.url || '/panel' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = new URL(event.notification.data?.url || '/panel', self.location.origin);

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            // Si la aplicación ya está abierta se reutiliza esa ventana en vez
            // de abrir otra encima
            for (const client of clients) {
                if (new URL(client.url).origin === target.origin && 'focus' in client) {
                    client.navigate(target.href);
                    return client.focus();
                }
            }

            return self.clients.openWindow(target.href);
        }),
    );
});
