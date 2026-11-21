import { openDB, type DBSchema, type IDBPDatabase } from 'idb'
import type {
  AttendanceSubmitPayload,
  AttendanceRoster,
  ConversationDetail,
  ConversationSummary,
} from '@/services/types'

export type OutboxStatus = 'pending_sync' | 'failed'

/** Offline-composed chat message waiting for connectivity. */
export interface MessageOutboxPayload {
  conversationUuid: string
  body: string
  /** Client-side id of the optimistic bubble this record reconciles. */
  tempId: string
}

export type OutboxPayload = AttendanceSubmitPayload | MessageOutboxPayload

export interface OutboxRecord {
  id?: number
  kind: 'attendance' | 'message'
  createdAt: number
  status: OutboxStatus
  attempts: number
  lastError?: string
  /** human-readable summary for honest sync UI ("Grade 3A — 11 Nov 2026, morning, 2 absent") */
  label: string
  payload: OutboxPayload
}

export function isMessagePayload(payload: OutboxPayload): payload is MessageOutboxPayload {
  return typeof (payload as MessageOutboxPayload).conversationUuid === 'string'
}

interface RosterCacheEntry {
  key: string
  savedAt: number
  roster: AttendanceRoster
}

export interface ConversationsCacheEntry {
  key: string
  savedAt: number
  conversations: ConversationSummary[]
}

export interface ThreadCacheEntry {
  key: string
  savedAt: number
  thread: ConversationDetail
}

interface GegoDB extends DBSchema {
  outbox: { key: number; value: OutboxRecord; indexes: { byStatus: OutboxStatus } }
  roster: { key: string; value: RosterCacheEntry }
  conversations: { key: string; value: ConversationsCacheEntry }
  threads: { key: string; value: ThreadCacheEntry }
}

let dbPromise: Promise<IDBPDatabase<GegoDB>> | null = null

export function getDb(): Promise<IDBPDatabase<GegoDB>> {
  if (!dbPromise) {
    dbPromise = openDB<GegoDB>('gego-pwa', 2, {
      upgrade(db, oldVersion) {
        if (oldVersion < 1) {
          const outbox = db.createObjectStore('outbox', { keyPath: 'id', autoIncrement: true })
          outbox.createIndex('byStatus', 'status')
          db.createObjectStore('roster', { keyPath: 'key' })
        }
        if (oldVersion < 2) {
          db.createObjectStore('conversations', { keyPath: 'key' })
          db.createObjectStore('threads', { keyPath: 'key' })
        }
      },
    })
  }
  return dbPromise
}
