const CACHE_NAME = 'timecase-v22';
const urlsToCache = [
  '/styles/style.css',
  '/bootstrap/css/bootstrap.min.css',
  '/bootstrap/css/bootstrap-responsive.min.css',
  '/bootstrap/css/font-awesome.css',
  '/bootstrap/css/bootstrap-combobox.css',
  '/bootstrap/css/bootstrap-datepicker.css',
  '/scripts/libs/jquery-1.8.2.min.js',
  '/scripts/libs/underscore-min.js',
  '/scripts/libs/backbone.js',
  // '/scripts/app.js',
  // '/scripts/model.js',
  // '/scripts/view.js',
  // '/scripts/timecase.js'
];

// Install event - cache resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache).catch(err => {
          console.log('Some files failed to cache, app will work online', err);
          return Promise.resolve();
        });
      })
  );
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  // Skip API calls - always go to network
  if (event.request.url.includes('/api/')) {
    event.respondWith(
      fetch(event.request)
        .catch(() => new Response('Offline - API not available', { status: 503 }))
    );
    return;
  }

  // Skip HTML page requests - always go to network (dynamic navigation)
  // This includes requests like /timeentries, /reports, /customers, etc.
  if (event.request.mode === 'navigate' || event.request.destination === 'document') {
    event.respondWith(
      fetch(event.request)
        .catch(() => new Response('Offline - page not available', { status: 503 }))
    );
    return;
  }

  // Skip PHP template files - always go to network
  if (event.request.url.endsWith('.tpl.php') || event.request.url.includes('.tpl.php?')) {
    event.respondWith(
      fetch(event.request)
        .catch(() => new Response('Offline - template not available', { status: 503 }))
    );
    return;
  }

  // For static assets, use cache-first strategy
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request)
          .then(response => {
            if (!response || response.status !== 200 || response.type === 'error') {
              return response;
            }
            // Clone the response
            const responseToCache = response.clone();
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });
            return response;
          })
          .catch(() => new Response('Offline - resource not available', { status: 503 }));
      })
  );
});
