/// <reference lib="webworker" />
/**
 * Custom service worker (vite-plugin-pwa injectManifest).
 * - Precaches the app shell (build output injected as __WB_MANIFEST).
 * - Runtime stale-while-revalidate for GET /api/* with a 5-minute TTL.
 * - Background Sync: on `attendance-sync`, nudges open clients to flush the
 *   IndexedDB outbox (clients hold the Sanctum token, so the POST happens
 *   in the page, not the SW).
 * - Web Push: displays notifications, focuses/opens the app on click.
 */
import { precacheAndRoute, cleanupOutdatedCaches, createHandlerBoundToURL } from 'workbox-precaching'
import { registerRoute, NavigationRoute } from 'workbox-routing'
import { StaleWhileRevalidate } from 'workbox-strategies'
import { ExpirationPlugin } from 'workbox-expiration'
import { clientsClaim } from 'workbox-core'

declare const self: ServiceWorkerGlobalScope

interface SyncEventLike extends ExtendableEvent {
  tag: string
}

self.skipWaiting()
clientsClaim()
cleanupOutdatedCaches()

const manifest = (
  self as unknown as { __WB_MANIFEST: Array<string | { url: string; revision: string | null }> }
).__WB_MANIFEST
precacheAndRoute(manifest)

// SPA fallback: navigations serve the precached index.html.
registerRoute(new NavigationRoute(createHandlerBoundToURL('index.html')))

// API GETs: fresh-when-possible, cached-when-offline, 5 min TTL.
registerRoute(
  ({ request, url }) => request.method === 'GET' && url.pathname.startsWith('/api/'),
  new StaleWhileRevalidate({
    cacheName: 'api-get',
    plugins: [new ExpirationPlugin({ maxEntries: 120, maxAgeSeconds: 300 })],
  }),
)

self.addEventListener('sync', (event: Event) => {
  const syncEvent = event as unknown as SyncEventLike
  if (syncEvent.tag === 'attendance-sync') {
    syncEvent.waitUntil(notifyClientsToSync())
  }
})

async function notifyClientsToSync(): Promise<void> {
  const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
  for (const client of clients) {
    client.postMessage({ type: 'SYNC_ATTENDANCE' })
  }
}

interface PushPayload {
  title?: string
  body?: string
  url?: string
  icon?: string
}

self.addEventListener('push', (event: Event) => {
  const pushEvent = event as unknown as ExtendableEvent & { data?: { json(): unknown } }
  let payload: PushPayload = {}
  try {
    payload = (pushEvent.data?.json() ?? {}) as PushPayload
  } catch {
    payload = {}
  }
  const title = payload.title || 'GegoK12'
  pushEvent.waitUntil(
    self.registration.showNotification(title, {
      body: payload.body ?? '',
      icon: payload.icon ?? '/icons/icon-192.png',
      badge: '/icons/icon-192.png',
      data: { url: payload.url ?? '/' },
    }),
  )
})

self.addEventListener('notificationclick', (event: Event) => {
  const click = event as unknown as ExtendableEvent & {
    notification: { close(): void; data?: { url?: string } }
  }
  click.notification.close()
  const url = click.notification.data?.url ?? '/'
  click.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (clients) => {
      for (const client of clients as WindowClient[]) {
        await client.navigate(url)
        return client.focus()
      }
      return self.clients.openWindow(url)
    }),
  )
})
