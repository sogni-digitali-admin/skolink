<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PushPrePrompt from '@/components/PushPrePrompt.vue'
import {
  isPushSupported,
  isSubscribed,
  subscribeToPush,
  unsubscribeFromPush,
} from '@/services/push'
import { ApiError } from '@/services/http'

const { t } = useI18n()

const supported = ref(false)
const subscribed = ref(false)
const busy = ref(false)
const showPrePrompt = ref(false)
const feedback = ref<'denied' | 'notConfigured' | 'error' | null>(null)

async function enable(): Promise<void> {
  showPrePrompt.value = false
  busy.value = true
  feedback.value = null
  try {
    const ok = await subscribeToPush()
    subscribed.value = ok
    if (!ok && Notification.permission === 'denied') feedback.value = 'denied'
  } catch (err) {
    feedback.value = err instanceof ApiError && err.status === 412 ? 'notConfigured' : 'error'
  } finally {
    busy.value = false
  }
}

async function disable(): Promise<void> {
  busy.value = true
  try {
    await unsubscribeFromPush()
    subscribed.value = false
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  supported.value = isPushSupported()
  subscribed.value = await isSubscribed()
})
</script>

<template>
  <div class="card">
    <h3>🔔 {{ t('push.title') }}</h3>
    <p v-if="!supported" class="meta">{{ t('push.unsupported') }}</p>
    <template v-else>
      <p v-if="subscribed" class="meta">{{ t('push.enabled') }}</p>
      <p v-else-if="feedback === 'denied'" class="meta">{{ t('push.denied') }}</p>
      <p v-else-if="feedback === 'notConfigured'" class="meta">{{ t('push.notConfigured') }}</p>
      <p v-else-if="feedback === 'error'" class="meta">{{ t('common.error') }}</p>
      <button v-if="!subscribed" class="btn btn-secondary" :disabled="busy" @click="showPrePrompt = true">
        {{ t('push.enable') }}
      </button>
      <button v-else class="btn btn-ghost" :disabled="busy" @click="disable">
        {{ t('push.disable') }}
      </button>
    </template>
    <PushPrePrompt v-if="showPrePrompt" @confirm="enable" @cancel="showPrePrompt = false" />
  </div>
</template>
