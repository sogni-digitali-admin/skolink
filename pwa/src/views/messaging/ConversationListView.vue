<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { createConversation, getContacts } from '@/services/messaging'
import { useAuthStore } from '@/stores/auth'
import { useMessagingStore } from '@/stores/messaging'
import { listTimeLabel } from '@/utils/time'
import type { ConversationContact } from '@/services/types'

const { t, locale } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const messaging = useMessagingStore()

const basePath = computed(() => (auth.role === 'teacher' ? '/t/messages' : '/p/messages'))

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

function roleLabel(role: string): string {
  const key = `messages.role.${role}`
  return t(key) === key ? role : t(key)
}

// ---------- new-chat contact picker ----------
const pickerOpen = ref(false)
const contacts = ref<ConversationContact[]>([])
const contactsLoading = ref(false)
const contactsError = ref<string | null>(null)
const starting = ref<number | null>(null)

interface ContactGroup {
  key: string
  label: string
  contacts: ConversationContact[]
}

/**
 * Grouped per the contract's childName hint:
 *   parent  → "Teachers of {child}" per child, plus a staff section
 *   teacher → "Parents of {child}" per child, plus a staff section
 */
const contactGroups = computed<ContactGroup[]>(() => {
  const groups = new Map<string, ConversationContact[]>()
  const staff: ConversationContact[] = []
  for (const contact of contacts.value) {
    if (contact.childName) {
      const list = groups.get(contact.childName) ?? []
      list.push(contact)
      groups.set(contact.childName, list)
    } else {
      staff.push(contact)
    }
  }
  const result: ContactGroup[] = [...groups.entries()].map(([child, list]) => ({
    key: child,
    label:
      auth.role === 'teacher'
        ? t('messages.parentsOf', { child })
        : t('messages.teachersOf', { child }),
    contacts: list,
  }))
  if (staff.length) result.push({ key: '_staff', label: t('messages.staff'), contacts: staff })
  return result
})

async function openPicker(): Promise<void> {
  pickerOpen.value = true
  if (contacts.value.length || contactsLoading.value) return
  contactsLoading.value = true
  contactsError.value = null
  try {
    contacts.value = await getContacts()
  } catch (err) {
    contactsError.value = err instanceof Error ? err.message : String(err)
  } finally {
    contactsLoading.value = false
  }
}

async function startChat(contact: ConversationContact): Promise<void> {
  if (starting.value !== null) return
  starting.value = contact.id
  contactsError.value = null
  try {
    const uuid = await createConversation(contact.id)
    pickerOpen.value = false
    await router.push(`${basePath.value}/${uuid}`)
  } catch (err) {
    contactsError.value = err instanceof Error ? err.message : String(err)
  } finally {
    starting.value = null
  }
}

onMounted(() => {
  void messaging.start()
  void messaging.loadConversations()
})
</script>

<template>
  <div>
    <div class="chat-list-header">
      <h2 style="margin: 0">{{ t('messages.title') }}</h2>
      <button class="btn btn-sm" @click="openPicker">＋ {{ t('messages.newChat') }}</button>
    </div>

    <div v-if="messaging.conversationsFromCache" class="notice-bar warn">
      {{ t('common.offlineHint') }}
    </div>

    <div v-if="!messaging.conversations.length" class="empty-state">
      {{ t('messages.empty') }}
    </div>

    <div class="card" v-else style="padding: 4px 16px">
      <router-link
        v-for="convo in messaging.conversations"
        :key="convo.uuid"
        :to="`${basePath}/${convo.uuid}`"
        class="row chat-row-link"
      >
        <span class="avatar" aria-hidden="true">
          <img v-if="convo.otherParticipant.avatarUrl" :src="convo.otherParticipant.avatarUrl" alt="" />
          <template v-else>{{ initials(convo.otherParticipant.name) }}</template>
        </span>
        <span class="grow">
          <span class="name">{{ convo.otherParticipant.name }}</span>
          <span class="meta"> · {{ roleLabel(convo.otherParticipant.role) }}</span>
          <div class="meta chat-preview">
            <template v-if="convo.lastMessage">
              <template v-if="convo.lastMessage.isOwn">{{ t('messages.you') }}: </template>
              {{ convo.lastMessage.body }}
            </template>
            <template v-else>{{ t('messages.noMessages') }}</template>
          </div>
        </span>
        <span class="chat-side">
          <span v-if="convo.lastMessage" class="meta">
            {{ listTimeLabel(convo.lastMessage.createdAt, locale, t('messages.yesterday')) }}
          </span>
          <span v-if="convo.unreadCount > 0" class="unread-pill">{{ convo.unreadCount }}</span>
        </span>
      </router-link>
    </div>

    <!-- new chat picker -->
    <div v-if="pickerOpen" class="modal-backdrop" @click.self="pickerOpen = false">
      <div class="modal" role="dialog" :aria-label="t('messages.contactsTitle')">
        <h2>{{ t('messages.contactsTitle') }}</h2>
        <div v-if="contactsLoading" class="spinner" role="status" />
        <div v-else-if="contactsError" class="notice-bar bad">{{ contactsError }}</div>
        <div v-else-if="!contacts.length" class="empty-state">{{ t('common.empty') }}</div>
        <template v-else>
          <div v-for="group in contactGroups" :key="group.key" class="contact-group">
            <div class="contact-group-label">{{ group.label }}</div>
            <button
              v-for="contact in group.contacts"
              :key="contact.id"
              class="row contact-row"
              :disabled="starting !== null"
              @click="startChat(contact)"
            >
              <span class="avatar" aria-hidden="true">{{ initials(contact.name) }}</span>
              <span class="grow" style="text-align: left">
                <span class="name">{{ contact.name }}</span>
                <div class="meta">{{ roleLabel(contact.role) }}</div>
              </span>
              <span v-if="starting === contact.id" class="spinner" style="margin: 0" role="status" />
            </button>
          </div>
        </template>
        <button class="btn btn-ghost" style="margin-top: 12px" @click="pickerOpen = false">
          {{ t('common.cancel') }}
        </button>
      </div>
    </div>
  </div>
</template>
