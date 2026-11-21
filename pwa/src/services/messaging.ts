/**
 * Messaging service — parent/teacher 1:1 conversations.
 * Implements the Phase contract exactly; the backend is built in parallel,
 * so every reader tolerates the legacy envelope shapes ({data: ...}) too.
 */
import { api } from './http'
import * as EP from './endpoints'
import type {
  ConversationContact,
  ConversationDetail,
  ConversationMessage,
  ConversationParticipant,
  ConversationSummary,
} from './types'

function unwrap<T>(raw: unknown): T {
  if (raw && typeof raw === 'object' && 'data' in (raw as Record<string, unknown>)) {
    return (raw as { data: T }).data
  }
  return raw as T
}

function unwrapList<T>(raw: unknown): T[] {
  const val = unwrap<unknown>(raw)
  return Array.isArray(val) ? (val as T[]) : []
}

function asParticipant(raw: unknown): ConversationParticipant {
  const obj = (raw && typeof raw === 'object' ? raw : {}) as Record<string, unknown>
  return {
    id: Number(obj.id ?? 0),
    name: String(obj.name ?? ''),
    role: String(obj.role ?? ''),
    avatarUrl: (obj.avatarUrl ?? obj.avatar_url) as string | undefined,
  }
}

function asMessage(raw: unknown): ConversationMessage {
  const obj = (raw && typeof raw === 'object' ? raw : {}) as Record<string, unknown>
  return {
    id: Number(obj.id ?? 0),
    body: String(obj.body ?? ''),
    isOwn: Boolean(obj.isOwn ?? obj.is_own),
    createdAt: String(obj.createdAt ?? obj.created_at ?? ''),
    readAt: (obj.readAt ?? obj.read_at ?? null) as string | null,
  }
}

function asSummary(raw: unknown): ConversationSummary {
  const obj = (raw && typeof raw === 'object' ? raw : {}) as Record<string, unknown>
  const last = (obj.lastMessage ?? obj.last_message) as Record<string, unknown> | undefined
  return {
    uuid: String(obj.uuid ?? ''),
    otherParticipant: asParticipant(obj.otherParticipant ?? obj.other_participant),
    lastMessage:
      last && typeof last === 'object'
        ? {
            body: String(last.body ?? ''),
            createdAt: String(last.createdAt ?? last.created_at ?? ''),
            isOwn: Boolean(last.isOwn ?? last.is_own),
          }
        : undefined,
    unreadCount: Number(obj.unreadCount ?? obj.unread_count ?? 0),
    updatedAt: String(obj.updatedAt ?? obj.updated_at ?? ''),
  }
}

export async function getConversations(): Promise<ConversationSummary[]> {
  const raw = await api<unknown>(EP.CONVERSATIONS)
  return unwrapList<unknown>(raw).map(asSummary)
}

export async function getContacts(): Promise<ConversationContact[]> {
  const raw = await api<unknown>(EP.CONVERSATION_CONTACTS)
  return unwrapList<unknown>(raw).map((c) => {
    const obj = (c && typeof c === 'object' ? c : {}) as Record<string, unknown>
    return {
      id: Number(obj.id ?? 0),
      name: String(obj.name ?? ''),
      role: String(obj.role ?? ''),
      childName: (obj.childName ?? obj.child_name) as string | undefined,
    }
  })
}

/** Create (or fetch the existing) 1:1 conversation with a user. Returns its uuid. */
export async function createConversation(userId: number): Promise<string> {
  const raw = await api<unknown>(EP.CONVERSATIONS, { method: 'POST', body: { user_id: userId } })
  const obj = unwrap<Record<string, unknown>>(raw) ?? {}
  return String(obj.uuid ?? '')
}

export async function getConversation(uuid: string): Promise<ConversationDetail> {
  const raw = await api<unknown>(EP.CONVERSATION(uuid))
  const obj = unwrap<Record<string, unknown>>(raw) ?? {}
  return {
    uuid: String(obj.uuid ?? uuid),
    otherParticipant: asParticipant(obj.otherParticipant ?? obj.other_participant),
    messages: Array.isArray(obj.messages) ? obj.messages.map(asMessage) : [],
  }
}

export async function sendMessage(uuid: string, body: string): Promise<ConversationMessage> {
  const raw = await api<unknown>(EP.CONVERSATION_MESSAGES(uuid), { method: 'POST', body: { body } })
  return asMessage(unwrap<unknown>(raw))
}

export async function markConversationRead(uuid: string): Promise<void> {
  await api<unknown>(EP.CONVERSATION_READ(uuid), { method: 'POST' })
}

export async function getUnreadCount(): Promise<number> {
  const raw = await api<unknown>(EP.CONVERSATIONS_UNREAD_COUNT)
  const obj = unwrap<Record<string, unknown>>(raw) ?? {}
  return Number(obj.count ?? 0)
}
