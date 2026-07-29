const VERSION = 'presentation-v2';
const SHELL_CACHE = `${VERSION}-shell`;
const DATA_CACHE = `${VERSION}-data`;
const STATIC_ASSETS = [
  '/manifest-presentation.webmanifest',
  '/vendor/chartjs/chart.min.js',
  '/adminlte/plugins/fontawesome-free/css/all.min.css',
  '/images/bri-logo-template.png',
  '/images/danantara-logo-template.png',
];
const MAX_DATA_PERIODS = 3;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE)
      .then((cache) => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith('presentation-') && ![SHELL_CACHE, DATA_CACHE].includes(key))
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

const trimPresentationData = async (cache) => {
  const requests = await cache.keys();
  const retainedPeriods = Array.from(new Set(requests
    .map((request) => new URL(request.url).searchParams.get('periode') || '')
    .filter(Boolean)))
    .sort((left, right) => right.localeCompare(left))
    .slice(0, MAX_DATA_PERIODS);
  const retained = new Set(retainedPeriods);

  await Promise.all(requests
    .filter((request) => {
      const period = new URL(request.url).searchParams.get('periode') || '';
      return period !== '' && !retained.has(period);
    })
    .map((request) => cache.delete(request)));
};

const networkFirstData = async (request) => {
  const cache = await caches.open(DATA_CACHE);
  try {
    const response = await fetch(request);
    if (response.ok) {
      await cache.put(request, response.clone());
      await trimPresentationData(cache);
    }
    return response;
  } catch (error) {
    const cached = await cache.match(request);
    if (cached) return cached;
    throw error;
  }
};

const staleWhileRevalidate = async (request) => {
  const cache = await caches.open(SHELL_CACHE);
  const cached = await cache.match(request);
  const network = fetch(request)
    .then((response) => {
      if (response.ok) cache.put(request, response.clone());
      return response;
    })
    .catch(() => cached);

  return cached || network;
};

const refreshCachedPresentationData = async () => {
  const cache = await caches.open(DATA_CACHE);
  const requests = await cache.keys();

  await Promise.all(requests.map(async (request) => {
    try {
      const response = await fetch(request);
      if (response.ok) {
        await cache.put(request, response.clone());
      }
    } catch (error) {
      // Keep the last valid response when the connection is still unavailable.
    }
  }));
  await trimPresentationData(cache);
};

self.addEventListener('sync', (event) => {
  if (event.tag === 'presentation-data-refresh') {
    event.waitUntil(refreshCachedPresentationData());
  }
});

self.addEventListener('message', (event) => {
  if (event.data?.type === 'REFRESH_PRESENTATION_DATA') {
    event.waitUntil(refreshCachedPresentationData());
  }
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  if (url.pathname.startsWith('/dashboard/presentation-data')
      || url.pathname === '/dashboard/presentation-kts-data') {
    event.respondWith(networkFirstData(event.request));
    return;
  }

  if (url.pathname === '/dashboard/presentation') {
    event.respondWith(
      fetch(event.request)
        .then(async (response) => {
          if (response.ok) {
            const cache = await caches.open(SHELL_CACHE);
            await cache.put(event.request, response.clone());
          }
          return response;
        })
        .catch(async () => {
          const cache = await caches.open(SHELL_CACHE);
          return cache.match(event.request);
        })
    );
    return;
  }

  if (url.pathname.startsWith('/build/')
      || url.pathname.startsWith('/images/')
      || url.pathname.startsWith('/vendor/')
      || url.pathname.startsWith('/adminlte/')) {
    event.respondWith(staleWhileRevalidate(event.request));
  }
});
