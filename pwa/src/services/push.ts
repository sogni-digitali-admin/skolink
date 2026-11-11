/**
 * Web Push (VAPID) subscription flow — Phase 2 contract.
 * POST/DELETE /api/push-subscriptions with the PushSubscription JSON body.
 */
import { api, ApiError } from './http'
import { PUSH_SUBSCRIPTIONS, VAPID_PUBLIC_KEY } from './endpoints'
import type { PushSubscriptionBody } from './types'

export function isPushSupported(): boolean {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

function urlBase64ToUint8Array(base64: string): Uint8Array {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(b64)
  const out = new Uint8Array(raw.length)
  for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i)
  return out
}

/** [assumed] VAPID public key from backend; falls back to build-time env var. */
export async function getVapidPublicKey(): Promise<string | null> {
  const envKey = import.meta.env.VITE_VAPID_PUBLIC_KEY
  try {
    const res = await api<{ publicKey?: string; public_key?: string }>(VAPID_PUBLIC_KEY)
    const key = res?.publicKey ?? res?.public_key
    if (key) return key
  } catch (err) {
    if (err instanceof ApiError && err.status === 404) return envKey ?? null
    if (envKey) return envKey
    throw err
  }
  return envKey ?? null
}

/** Call AFTER the friendly pre-prompt — this triggers the browser permission dialog. */
export async function subscribeToPush(): Promise<boolean> {
  if (!isPushSupported()) return false
  const permission = await Notification.requestPermission()
  if (permission !== 'granted') return false

  const vapidKey = await getVapidPublicKey()
  if (!vapidKey) throw new ApiError('Push is not configured on the server yet', 412, null)

  const registration = await navigator.serviceWorker.ready
  const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(vapidKey) as BufferSource,
  })

  const body = subscription.toJSON() as PushSubscriptionBody
  await api(PUSH_SUBSCRIPTIONS, { method: 'POST', body })
  return true
}

export async function unsubscribeFromPush(): Promise<void> {
  if (!isPushSupported()) return
  const registration = await navigator.serviceWorker.ready
  const subscription = await registration.pushManager.getSubscription()
  if (!subscription) return
  const endpoint = subscription.endpoint
  await subscription.unsubscribe()
  try {
    await api(PUSH_SUBSCRIPTIONS, { method: 'DELETE', body: { endpoint } })
  } catch {
    // Local unsubscribe succeeded; server cleanup is best effort.
  }
}

export async function isSubscribed(): Promise<boolean> {
  if (!isPushSupported()) return false
  try {
    const registration = await navigator.serviceWorker.ready
    return (await registration.pushManager.getSubscription()) !== null
  } catch {
    return false
  }
}

/** Registers a Background Sync tag so the SW can nudge open clients to flush the outbox. */
export async function registerAttendanceSync(): Promise<void> {
  if (!('serviceWorker' in navigator)) return
  try {
    const registration = await navigator.serviceWorker.ready
    const withSync = registration as ServiceWorkerRegistration & {
      sync?: { register(tag: string): Promise<void> }
    }
    await withSync.sync?.register('attendance-sync')
  } catch {
    // Background Sync unsupported (e.g. iOS) — online-event and manual retry still work.
  }
}
