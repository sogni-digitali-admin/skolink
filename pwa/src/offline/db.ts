import { openDB, type DBSchema, type IDBPDatabase } from 'idb'
import type { AttendanceSubmitPayload, AttendanceRoster } from '@/services/types'

export type OutboxStatus = 'pending_sync' | 'failed'

export interface OutboxRecord {
  id?: number
  kind: 'attendance'
  createdAt: number
  status: OutboxStatus
  attempts: number
  lastError?: string
  /** human-readable summary for honest sync UI ("Grade 3A — 11 Nov 2026, morning, 2 absent") */
  label: string
  payload: AttendanceSubmitPayload
}

interface RosterCacheEntry {
  key: string
  savedAt: number
  roster: AttendanceRoster
}

interface GegoDB extends DBSchema {
  outbox: { key: number; value: OutboxRecord; indexes: { byStatus: OutboxStatus } }
  roster: { key: string; value: RosterCacheEntry }
}

let dbPromise: Promise<IDBPDatabase<GegoDB>> | null = null

export function getDb(): Promise<IDBPDatabase<GegoDB>> {
  if (!dbPromise) {
    dbPromise = openDB<GegoDB>('gego-pwa', 1, {
      upgrade(db) {
        const outbox = db.createObjectStore('outbox', { keyPath: 'id', autoIncrement: true })
        outbox.createIndex('byStatus', 'status')
        db.createObjectStore('roster', { keyPath: 'key' })
      },
    })
  }
  return dbPromise
}
