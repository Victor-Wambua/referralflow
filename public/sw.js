/* ReferralFlow service worker. Caches ONLY the static application shell and /offline.
   Authenticated pages, API responses and documents are never cached (no patient data offline).
   See design/addenda.md A4. */
const VERSION = 'rf-shell-v1';
const SHELL = ['/offline', '/mark.svg', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(VERSION);
    // credentials are omitted so these background fetches never touch the user's session (a session-bound
    // fetch of /offline would otherwise become the "previous URL" that Laravel's back() redirects to).
    const anon = (u) => new Request(u, { credentials: 'omit', cache: 'no-store' });
    await Promise.all(SHELL.map((u) => cache.add(anon(u))));
    try {
      const html = await (await fetch(anon('/offline'))).text();
      const assets = [...html.matchAll(/(?:href|src)="([^"]*\/build\/assets\/[^"]+)"/g)].map((m) => m[1]);
      await Promise.all(assets.map((u) => cache.add(anon(u))));
    } catch (e) { /* offline during install: shell without assets */ }
    self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    for (const k of await caches.keys()) if (k !== VERSION) await caches.delete(k);
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (url.pathname.startsWith('/build/assets/') || SHELL.includes(url.pathname)) {
    event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
      return res;
    })));
    return;
  }
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('/offline')));
  }
});
