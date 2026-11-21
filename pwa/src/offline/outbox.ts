/**
 * Offline outbox (attendance is the Phase 5 flagship; chat messages reuse it).
 *
 * Records are written to IndexedDB FIRST with status `pending_sync`, then
 * flushed to the API. Flush is attempted:
 *   1. immediately after enqueue (when online),
 *   2. on the browser `online` event,
 *   3. via Background Sync (SW posts SYNC_ATTENDANCE to open clients),
 *   4. manually from the UI ("Retry now").
 *
 * A record only leaves `pending_sync` when the server accepts it. Permanent
 * server rejections (4xx) become `failed` with the server message — never
 * silently dropped.
 */
import { getDb, isMessagePayload, type MessageOutboxPayload, type OutboxRecord } from './db'
import { submitAttendance } from '@/services/teacher'
import { sendMessage } from '@/services/messaging'
import { ApiError } from '@/services/http'
import type { AttendanceSubmitPayload } from '@/services/types'

export type { OutboxRecord }

export async function enqueue(
  payload: AttendanceSubmitPayload,
  label: string,
): Promise<number> {
  const db = await getDb()
  const record: OutboxRecord = {
    kind: 'attendance',
    createdAt: Date.now(),
    status: 'pending_sync',
    attempts: 0,
    label,
    payload,
  }
  return db.put('outbox', record)
}

/** Queue a chat message composed while offline. */
export async function enqueueMessage(payload: MessageOutboxPayload, label: string): Promise<number> {
  const db = await getDb()
  const record: OutboxRecord = {
    kind: 'message',
    createdAt: Date.now(),
    status: 'pending_sync',
    attempts: 0,
    label,
    payload,
  }
  return db.put('outbox', record)
}

export async function listOutbox(): Promise<OutboxRecord[]> {
  const db = await getDb()
  const all = await db.getAll('outbox')
  return all.sort((a, b) => b.createdAt - a.createdAt)
}

export async function countPending(): Promise<number> {
  const db = await getDb()
  const pending = await db.getAllFromIndex('outbox', 'byStatus', 'pending_sync')
  return pending.length
}

export async function removeRecord(id: number): Promise<void> {
  const db = await getDb()
  await db.delete('outbox', id)
}

export interface FlushResult {
  synced: number
  failed: number
  remaining: number
}

async function deliver(record: OutboxRecord): Promise<void> {
  if (record.kind === 'message' && isMessagePayload(record.payload)) {
    await sendMessage(record.payload.conversationUuid, record.payload.body)
    return
  }
  await submitAttendance(record.payload as AttendanceSubmitPayload)
}

/** Try to push every pending record. Safe to call repeatedly/concurrently-ish. */
export async function flushOutbox(): Promise<FlushResult> {
  const db = await getDb()
  const pending = await db.getAllFromIndex('outbox', 'byStatus', 'pending_sync')
  let synced = 0
  let failed = 0

  for (const record of pending) {
    if (record.id === undefined) continue
    record.attempts += 1
    try {
      await deliver(record)
      await db.delete('outbox', record.id)
      synced += 1
    } catch (err) {
      if (err instanceof ApiError && err.status >= 400 && err.status < 500 && err.status !== 401) {
        // Server refused it — retrying won't help. Surface honestly.
        record.status = 'failed'
        record.lastError = err.message
        failed += 1
      } else if (err instanceof ApiError && err.status === 401) {
        // Session expired — stop; the 401 handler already logged the user out.
        record.lastError = 'Session expired'
        await db.put('outbox', record)
        break
      } else {
        // Network down or server error — keep pending_sync for the next flush.
        record.lastError = err instanceof Error ? err.message : 'Unknown error'
      }
      await db.put('outbox', record)
    }
  }

  return { synced, failed, remaining: await countPending() }
}

// ---------- roster cache (attendance sheet must load offline) ----------

export async function cacheRoster(key: string, roster: import('@/services/types').AttendanceRoster): Promise<void> {
  const db = await getDb()
  await db.put('roster', { key, savedAt: Date.now(), roster })
}

export async function getCachedRoster(key: string) {
  const db = await getDb()
  return db.get('roster', key)
}
