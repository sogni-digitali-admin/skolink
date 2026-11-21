<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getConversation,
  markConversationRead,
  sendMessage,
} from '@/services/messaging'
import { ApiError } from '@/services/http'
import { cacheThread, getCachedThread } from '@/offline/messaging-cache'
import { enqueueMessage } from '@/offline/outbox'
import { useAuthStore } from '@/stores/auth'
import { useMessagingStore } from '@/stores/messaging'
import { useSyncStore } from '@/stores/sync'
import { dateLabel, dayKind, sameDay, timeLabel } from '@/utils/time'
import type { ConversationMessage, ConversationParticipant, MessageSentPayload } from '@/services/types'

interface ThreadMessage extends ConversationMessage {
  tempId?: string
  pending?: boolean
}

type DisplayItem =
  | { type: 'day'; key: string; label: string }
  | { type: 'msg'; key: string; msg: ThreadMessage }

const props = defineProps<{ uuid: string }>()

const { t, locale } = useI18n()
const auth = useAuthStore()
const messaging = useMessagingStore()
const sync = useSyncStore()

const basePath = computed(() => (auth.role === 'teacher' ? '/t/messages' : '/p/messages'))

const other = ref<ConversationParticipant | null>(null)
const messages = ref<ThreadMessage[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const fromCache = ref(false)
const body = ref('')
const sending = ref(false)
const queuedNote = ref(false)

let threadPoll: number | null = null
let realtimeUnsub: (() => void) | null = null

function scrollToBottom(smooth = false): void {
  void nextTick(() => {
    window.scrollTo({ top: document.documentElement.scrollHeight, behavior: smooth ? 'smooth' : 'auto' })
  })
}

function roleLabel(role: string): string {
  const key = `messages.role.${role}`
  return t(key) === key ? role : t(key)
}

/** Merge the server truth with still-unconfirmed optimistic bubbles. */
function reconcile(serverMessages: ConversationMessage[]): void {
  const kept: ThreadMessage[] = serverMessages.map((m) => ({ ...m }))
  for (const temp of messages.value) {
    if (!temp.pending) continue
    const matched = serverMessages.some(
      (m) => m.isOwn && m.body === temp.body && Math.abs(new Date(m.createdAt).getTime() - new Date(temp.createdAt).getTime()) < 60_000,
    )
    if (!matched) kept.push(temp)
  }
  messages.value = kept
}

async function load(silent = false): Promise<void> {
  if (!silent) {
    loading.value = true
    error.value = null
  }
  try {
    const detail = await getConversation(props.uuid)
    other.value = detail.otherParticipant
    reconcile(detail.messages)
    fromCache.value = false
    await cacheThread(detail)
    if (!silent) scrollToBottom()
    markRead()
  } catch (err) {
    if (err instanceof ApiError && err.isNetwork) {
      // Offline: open the last cached snapshot of this thread.
      const cached = await getCachedThread(props.uuid).catch(() => undefined)
      if (cached) {
        other.value = cached.thread.otherParticipant
        reconcile(cached.thread.messages)
        fromCache.value = true
        if (!silent) scrollToBottom()
      } else if (!silent) {
        error.value = err.message
      }
    } else if (!silent) {
      error.value = err instanceof Error ? err.message : String(err)
    }
  } finally {
    loading.value = false
  }
}

function markRead(): void {
  void markConversationRead(props.uuid)
    .then(() => messaging.noteThreadRead(props.uuid))
    .catch(() => undefined)
}

function onRealtimeMessage(payload: MessageSentPayload): void {
  if (payload.uuid !== props.uuid) return
  const isOwn = payload.senderId === auth.userId
  if (messages.value.some((m) => m.id === payload.id)) return
  if (isOwn) {
    // Reconcile our own optimistic bubble instead of duplicating it.
    const temp = messages.value.find((m) => m.pending && m.body === payload.body)
    if (temp) {
      temp.id = payload.id
      temp.createdAt = payload.createdAt
      temp.pending = false
      return
    }
  }
  messages.value.push({
    id: payload.id,
    body: payload.body,
    isOwn,
    createdAt: payload.createdAt,
  })
  if (!isOwn) markRead()
  scrollToBottom(true)
}

async function subscribeThread(): Promise<void> {
  realtimeUnsub ??= await messaging.watchThread(props.uuid, onRealtimeMessage)
}

function startThreadPolling(): void {
  if (threadPoll !== null) return
  threadPoll = window.setInterval(() => {
    if (!document.hidden && !messaging.realtimeActive) void load(true)
  }, 30_000)
}

function stopThreadPolling(): void {
  if (threadPoll !== null) {
    window.clearInterval(threadPoll)
    threadPoll = null
  }
}

async function send(): Promise<void> {
  const text = body.value.trim()
  if (!text || sending.value) return
  sending.value = true
  body.value = ''
  queuedNote.value = false

  const temp: ThreadMessage = {
    id: -Date.now(),
    tempId: `tmp-${Date.now()}`,
    body: text,
    isOwn: true,
    createdAt: new Date().toISOString(),
    pending: true,
  }
  messages.value.push(temp)
  scrollToBottom(true)

  const queue = async (): Promise<void> => {
    await enqueueMessage(
      { conversationUuid: props.uuid, body: text, tempId: temp.tempId as string },
      t('messages.outboxLabel', { name: other.value?.name ?? '' }),
    )
    queuedNote.value = true
    await sync.refreshPending()
  }

  try {
    if (!navigator.onLine) throw new ApiError('offline', 0, null, true)
    const saved = await sendMessage(props.uuid, text)
    const idx = messages.value.findIndex((m) => m.tempId === temp.tempId)
    if (idx !== -1) messages.value[idx] = { ...saved }
  } catch (err) {
    if (err instanceof ApiError && err.isNetwork) {
      // Offline (or unreachable): queue honestly in the outbox.
      await queue()
    } else {
      const idx = messages.value.findIndex((m) => m.tempId === temp.tempId)
      if (idx !== -1) messages.value.splice(idx, 1)
      body.value = text
      error.value = err instanceof Error ? err.message : String(err)
    }
  } finally {
    sending.value = false
  }
}

const displayItems = computed<DisplayItem[]>(() => {
  const items: DisplayItem[] = []
  let prev: ThreadMessage | null = null
  for (const msg of messages.value) {
    if (!prev || !sameDay(prev.createdAt, msg.createdAt)) {
      const kind = dayKind(msg.createdAt)
      const label =
        kind === 'today'
          ? t('common.today')
          : kind === 'yesterday'
            ? t('messages.yesterday')
            : dateLabel(msg.createdAt, locale.value)
      items.push({ type: 'day', key: `day-${msg.createdAt.slice(0, 10)}-${msg.id}`, label })
    }
    items.push({ type: 'msg', key: String(msg.tempId ?? msg.id), msg })
    prev = msg
  }
  return items
})

function receipt(msg: ThreadMessage): string {
  if (msg.pending) return t('messages.sending')
  return msg.readAt ? '✓✓' : '✓'
}

watch(
  () => messaging.realtimeActive,
  (active) => {
    if (active) {
      stopThreadPolling()
      realtimeUnsub?.()
      realtimeUnsub = null
      void subscribeThread()
    } else {
      realtimeUnsub?.()
      realtimeUnsub = null
      startThreadPolling()
    }
  },
)

// Queued messages flushed while we were looking at the thread → resync.
watch(
  () => sync.lastSyncAt,
  () => {
    queuedNote.value = false
    void load(true)
  },
)

onMounted(async () => {
  messaging.setOpenThread(props.uuid)
  await messaging.start()
  await load()
  await subscribeThread()
  if (!messaging.realtimeActive) startThreadPolling()
})

onUnmounted(() => {
  messaging.setOpenThread(null)
  realtimeUnsub?.()
  realtimeUnsub = null
  stopThreadPolling()
})
</script>

<template>
  <div>
    <div class="chat-thread-header">
      <router-link :to="basePath" class="chat-back" :aria-label="t('common.back')">‹</router-link>
      <div class="grow">
        <div class="name">{{ other?.name ?? '…' }}</div>
        <div v-if="other" class="meta">{{ roleLabel(other.role) }}</div>
      </div>
    </div>

    <div v-if="fromCache" class="notice-bar warn">{{ t('common.offlineHint') }}</div>
    <div v-if="queuedNote" class="notice-bar warn">{{ t('messages.queuedOffline') }}</div>
    <div v-if="error" class="notice-bar bad">{{ error }}</div>

    <div v-if="loading" class="spinner" role="status" />
    <template v-else>
      <div v-if="!messages.length" class="empty-state">{{ t('messages.startConversation') }}</div>
      <template v-for="item in displayItems" :key="item.key">
        <div v-if="item.type === 'day'" class="chat-day">{{ item.label }}</div>
        <div v-else class="chat-bubble-row" :class="{ own: item.msg.isOwn }">
          <div class="bubble" :class="{ pending: item.msg.pending }">
            <div class="btext">{{ item.msg.body }}</div>
            <div class="bmeta">
              <span>{{ timeLabel(item.msg.createdAt, locale) }}</span>
              <span v-if="item.msg.isOwn" class="receipt">{{ receipt(item.msg) }}</span>
            </div>
          </div>
        </div>
      </template>
    </template>

    <form class="composer" @submit.prevent="send">
      <input
        v-model="body"
        type="text"
        :placeholder="t('messages.typePlaceholder')"
        :aria-label="t('messages.typePlaceholder')"
        autocomplete="off"
        @keydown.enter.exact.prevent="send"
      />
      <button class="btn btn-sm composer-send" type="submit" :disabled="!body.trim() || sending">
        {{ t('messages.send') }}
      </button>
    </form>
  </div>
</template>
