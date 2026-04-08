/**
 * TasadorIA — Service Worker v1.0
 * Estrategia: Cache-first para assets estáticos, Network-first para API
 */
const CACHE_NAME   = 'tasadoria-v1';
const CACHE_STATIC = 'tasadoria-static-v1';

// Assets que siempre se cachean (shell de la app)
const STATIC_ASSETS = [
  './',
  './index.php',
  './manifest.json',
  './icons/icon-192.png',
  './icons/icon-512.png',
  // Leaflet (CDN — se cachea en primera visita)
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
];

// ── Install: pre-cachear shell ────────────────────────────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_STATIC)
      .then(cache => cache.addAll(STATIC_ASSETS).catch(() => {}))
      .then(() => self.skipWaiting())
  );
});

// ── Activate: limpiar caches viejas ──────────────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(k => k !== CACHE_NAME && k !== CACHE_STATIC)
          .map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ── Fetch: estrategia por tipo de recurso ─────────────────────────────────────
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // API calls → siempre Network (nunca cachear resultados de tasación)
  if (url.pathname.includes('/api/')) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Overpass API → Network con fallback silencioso
  if (url.hostname.includes('overpass-api.de')) {
    event.respondWith(
      fetch(event.request).catch(() => new Response('[]', { headers: { 'Content-Type': 'application/json' } }))
    );
    return;
  }

  // Leaflet tiles OpenStreetMap → Cache-first (tiles de mapa)
  if (url.hostname.includes('tile.openstreetmap.org')) {
    event.respondWith(
      caches.open(CACHE_NAME).then(cache =>
        cache.match(event.request).then(cached => {
          if (cached) return cached;
          return fetch(event.request).then(resp => {
            cache.put(event.request, resp.clone());
            return resp;
          }).catch(() => cached);
        })
      )
    );
    return;
  }

  // Resto (index.php, assets estáticos) → Network-first con fallback a cache
  event.respondWith(
    fetch(event.request)
      .then(resp => {
        // Solo cachear respuestas exitosas de GET
        if (event.request.method === 'GET' && resp.status === 200) {
          const clone = resp.clone();
          caches.open(CACHE_STATIC).then(c => c.put(event.request, clone));
        }
        return resp;
      })
      .catch(() => caches.match(event.request))
  );
});

// ── Mensaje desde el cliente (forzar update) ──────────────────────────────────
self.addEventListener('message', event => {
  if (event.data === 'skipWaiting') self.skipWaiting();
});
