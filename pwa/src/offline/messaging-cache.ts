/**
 * Read-side cache for messaging: the conversation list and open threads are
 * readable offline from IndexedDB (writes go through the outbox instead).
 */
import { getDb } from './db'
import type { ConversationDetail, ConversationSummary } from '@/services/types'

const LIST_KEY = 'list'

export async function cacheConversations(conversations: ConversationSummary[]): Promise<void> {
  const db = await getDb()
  await db.put('conversations', { key: LIST_KEY, savedAt: Date.now(), conversations })
}

export async function getCachedConversations() {
  const db = await getDb()
  return db.get('conversations', LIST_KEY)
}

export async function cacheThread(thread: ConversationDetail): Promise<void> {
  const db = await getDb()
  await db.put('threads', { key: thread.uuid, savedAt: Date.now(), thread })
}

export async function getCachedThread(uuid: string) {
  const db = await getDb()
  return db.get('threads', uuid)
}
