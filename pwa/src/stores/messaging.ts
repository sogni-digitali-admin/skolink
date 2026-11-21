/**
 * Messaging store — conversation list, unread badge, realtime lifecycle.
 *
 * Transport strategy:
 *   - Reverb (Echo) when VITE_REVERB_APP_KEY is configured AND the socket
 *     connects: live MessageSent events update the list + badge.
 *   - Otherwise: poll the conversations + unread-count endpoints every 30s.
 *   - If the socket drops mid-session, we fall back to polling until it
 *     reconnects.
 *
 * This store is statically imported (nav badge) so it must stay small:
 * echo/pusher enter only via the dynamic import of services/realtime.
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import * as msgApi from '@/services/messaging'
import { cacheConversations, getCachedConversations } from '@/offline/messaging-cache'
import type { ConversationSummary, MessageSentPayload } from '@/services/types'

const POLL_INTERVAL_MS = 30_000

type RealtimeModule = typeof import('@/services/realtime')
let realtimeModule: RealtimeModule | null = null
async function realtime(): Promise<RealtimeModule> {
  realtimeModule ??= await import('@/services/realtime')
  return realtimeModule
}

let pollTimer: number | null = null
let connectionUnsub: (() => void) | null = null
const channelUnsubs = new Map<string, () => void>()

interface MessagingState {
  conversations: ConversationSummary[]
  conversationsFromCache: boolean
  unreadCount: number
  realtimeActive: boolean
  started: boolean
  /** Thread currently on screen — incoming messages there are read, not badge bumps. */
  openThreadUuid: string | null
}

export const useMessagingStore = defineStore('messaging', {
  state: (): MessagingState => ({
    conversations: [],
    conversationsFromCache: false,
    unreadCount: 0,
    realtimeActive: false,
    started: false,
    openThreadUuid: null,
  }),
  actions: {
    /** Idempotent app-level start (after login / on reload with a session). */
    async start(): Promise<void> {
      if (this.started) return
      this.started = true
      const rt = await realtime()

      connectionUnsub ??= rt.onRealtimeConnection((connected) => {
        this.realtimeActive = connected
        if (connected) {
          this.stopPolling()
          this.subscribeAllChannels()
          // Resync anything missed while the socket was down.
          void this.refreshUnread()
          void this.loadConversations()
        } else {
          this.dropChannels()
          this.startPolling()
        }
      })

      const connected = await rt.startRealtime()
      this.realtimeActive = connected

      await Promise.allSettled([this.loadConversations(), this.refreshUnread()])
      if (connected) this.subscribeAllChannels()
      else this.startPolling()
    },

    stop(): void {
      this.started = false
      this.stopPolling()
      this.dropChannels()
      connectionUnsub?.()
      connectionUnsub = null
      this.openThreadUuid = null
      this.conversations = []
      this.unreadCount = 0
      this.realtimeActive = false
      if (realtimeModule) void realtimeModule.stopRealtime()
    },

    async loadConversations(): Promise<void> {
      try {
        this.conversations = await msgApi.getConversations()
        this.conversationsFromCache = false
        await cacheConversations(this.conversations)
        if (this.realtimeActive) this.subscribeAllChannels()
      } catch {
        // Offline or backend not live yet: show the last snapshot honestly.
        const cached = await getCachedConversations().catch(() => undefined)
        if (cached) {
          this.conversations = cached.conversations
          this.conversationsFromCache = true
        }
      }
    },

    async refreshUnread(): Promise<void> {
      try {
        this.unreadCount = await msgApi.getUnreadCount()
      } catch {
        // Endpoint missing (backend in progress) or offline — keep last value.
      }
    },

    setOpenThread(uuid: string | null): void {
      this.openThreadUuid = uuid
    },

    /** Server-confirmed read: clear the local badge for that conversation. */
    noteThreadRead(uuid: string): void {
      const convo = this.conversations.find((c) => c.uuid === uuid)
      if (convo && convo.unreadCount > 0) {
        this.unreadCount = Math.max(0, this.unreadCount - convo.unreadCount)
        convo.unreadCount = 0
      }
      // The server count is the truth — resync when cheap.
      void this.refreshUnread()
    },

    /** Live MessageSent from a subscribed conversation channel. */
    handleRealtimeMessage(payload: MessageSentPayload): void {
      const auth = useAuthStore()
      const isOwn = payload.senderId === auth.userId
      const convo = this.conversations.find((c) => c.uuid === payload.uuid)
      if (convo) {
        convo.lastMessage = { body: payload.body, createdAt: payload.createdAt, isOwn }
        convo.updatedAt = payload.createdAt
        this.conversations = [...this.conversations].sort((a, b) =>
          b.updatedAt.localeCompare(a.updatedAt),
        )
      } else {
        // Conversation we don't know about yet — refresh the list.
        void this.loadConversations()
      }
      if (isOwn || this.openThreadUuid === payload.uuid) return
      if (convo) convo.unreadCount += 1
      this.unreadCount += 1
    },

    subscribeAllChannels(): void {
      if (!realtimeModule || !this.realtimeActive) return
      for (const convo of this.conversations) {
        if (channelUnsubs.has(convo.uuid)) continue
        const off = realtimeModule.subscribeConversation(convo.uuid, (payload) =>
          this.handleRealtimeMessage(payload),
        )
        channelUnsubs.set(convo.uuid, off)
      }
    },

    /**
     * Thread-level MessageSent subscription (the open conversation screen).
     * Returns a no-op unsubscribe while realtime is down — the view polls then.
     */
    async watchThread(
      uuid: string,
      handler: (payload: MessageSentPayload) => void,
    ): Promise<() => void> {
      const rt = await realtime()
      if (!this.realtimeActive) return () => undefined
      return rt.subscribeConversation(uuid, handler)
    },

    dropChannels(): void {
      for (const off of channelUnsubs.values()) off()
      channelUnsubs.clear()
    },

    startPolling(): void {
      if (pollTimer !== null) return
      pollTimer = window.setInterval(() => {
        if (document.hidden) return
        void this.refreshUnread()
        void this.loadConversations()
      }, POLL_INTERVAL_MS)
    },

    stopPolling(): void {
      if (pollTimer !== null) {
        window.clearInterval(pollTimer)
        pollTimer = null
      }
    },
  },
})
