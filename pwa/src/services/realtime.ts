/**
 * Realtime layer for messaging — Laravel Reverb (pusher protocol) via Echo.
 *
 * This module is DYNAMICALLY imported by the messaging store so echo/pusher
 * code-splits out of the initial bundle.
 *
 * Degradation contract:
 *   - If VITE_REVERB_APP_KEY is absent (not configured) or the socket cannot
 *     connect, `startRealtime()` resolves false and the store polls instead.
 *   - Auth uses the Sanctum bearer token against POST /broadcasting/auth.
 *   - Token lifecycle: the Sanctum token is fixed for the session, but
 *     `restartRealtime()` re-creates the connection with the current token —
 *     call it after login, and `stopRealtime()` on logout.
 */
import { apiBaseUrl } from './http'
import { getToken } from './token'
import { BROADCASTING_AUTH } from './endpoints'
import type { MessageSentPayload } from './types'
import type Echo from 'laravel-echo'
import type { ConnectionStatus } from 'laravel-echo'

type ReverbEcho = Echo<'reverb'>

export function realtimeConfigured(): boolean {
  return Boolean(import.meta.env.VITE_REVERB_APP_KEY)
}

let echo: ReverbEcho | null = null
let starting: Promise<boolean> | null = null
const connectionListeners = new Set<(connected: boolean) => void>()

function emitConnection(connected: boolean): void {
  for (const cb of connectionListeners) cb(connected)
}

/** Subscribe to connection up/down transitions. Returns an unsubscribe fn. */
export function onRealtimeConnection(cb: (connected: boolean) => void): () => void {
  connectionListeners.add(cb)
  return () => connectionListeners.delete(cb)
}

export function realtimeConnected(): boolean {
  return echo !== null && echo.connectionStatus() === 'connected'
}

async function createEcho(): Promise<ReverbEcho> {
  // Echo 2.x does not bundle the pusher-js client — pass it explicitly or the
  // reverb connector throws "Pusher client not found" at connect time.
  const [{ default: EchoClass }, { default: PusherClass }] = await Promise.all([
    import('laravel-echo'),
    import('pusher-js'),
  ])
  const scheme = (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'https'
  const defaultPort = scheme === 'https' ? 443 : 80
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? defaultPort)
  const instance = new EchoClass<'reverb'>({
    broadcaster: 'reverb',
    Pusher: PusherClass,
    key: import.meta.env.VITE_REVERB_APP_KEY as string,
    wsHost: (import.meta.env.VITE_REVERB_HOST as string | undefined) ?? window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${apiBaseUrl()}${BROADCASTING_AUTH}`,
    bearerToken: getToken(),
  })
  instance.connector.onConnectionChange((status: ConnectionStatus) => {
    emitConnection(status === 'connected')
  })
  return instance
}

/**
 * Connect to Reverb. Resolves true when the socket is up, false when realtime
 * is unavailable (missing config or connect failure) — callers then poll.
 */
export function startRealtime(): Promise<boolean> {
  if (!realtimeConfigured()) return Promise.resolve(false)
  if (echo) return Promise.resolve(realtimeConnected())
  starting ??= createEcho()
    .then((instance) => {
      echo = instance
      return new Promise<boolean>((resolve) => {
        const off = onRealtimeConnection((connected) => {
          if (connected) {
            off()
            resolve(true)
          }
        })
        // If the socket never comes up, give up and let the caller poll.
        window.setTimeout(() => {
          if (!realtimeConnected()) {
            off()
            stopRealtime()
            resolve(false)
          }
        }, 8000)
      })
    })
    .catch(() => {
      stopRealtime()
      return false
    })
    .finally(() => {
      starting = null
    })
  return starting
}

export function stopRealtime(): void {
  echo?.disconnect()
  echo = null
  emitConnection(false)
}

/** Re-create the connection with the current bearer token (login / token change). */
export async function restartRealtime(): Promise<boolean> {
  stopRealtime()
  return startRealtime()
}

export type MessageHandler = (payload: MessageSentPayload) => void

/**
 * Listen for `MessageSent` on PrivateChannel `conversation.{uuid}`.
 * No-op (returns a no-op unsubscribe) when realtime is down.
 */
export function subscribeConversation(uuid: string, handler: MessageHandler): () => void {
  if (!echo) return () => undefined
  const channelName = `conversation.${uuid}`
  echo.private(channelName).listen('MessageSent', (payload: MessageSentPayload) => {
    handler(payload)
  })
  return () => echo?.leave(channelName)
}
