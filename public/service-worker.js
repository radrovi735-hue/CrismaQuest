/* CrismaQuest PWA: installation shell only.
   Intentionally does not cache authenticated pages, API responses or game state. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});
