<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSyncStore } from '@/stores/sync'
import { listOutbox, removeRecord, type OutboxRecord } from '@/offline/outbox'

const { t } = useI18n()
const sync = useSyncStore()

const records = ref<OutboxRecord[]>([])
const expanded = ref(false)

async function refresh(): Promise<void> {
  records.value = await listOutbox()
  await sync.refreshPending()
}

const failed = computed(() => records.value.filter((r) => r.status === 'failed'))
const pending = computed(() => records.value.filter((r) => r.status === 'pending_sync'))

async function discard(id: number | undefined): Promise<void> {
  if (id === undefined) return
  await removeRecord(id)
  await refresh()
}

function formatDate(ts: number): string {
  return new Date(ts).toLocaleString()
}

onMounted(refresh)
defineExpose({ refresh })
</script>

<template>
  <div>
    <div v-if="!sync.online" class="notice-bar warn" role="status">
      📡 {{ t('common.offline') }} — {{ t('common.offlineHint') }}
    </div>
    <div v-else-if="sync.syncing" class="notice-bar warn" role="status">
      ⏳ {{ t('sync.syncing') }}
    </div>
    <div
      v-else-if="pending.length > 0"
      class="notice-bar warn"
      role="button"
      tabindex="0"
      @click="expanded = !expanded"
    >
      🕓 {{ t('sync.pending', pending.length) }}
      <button class="btn btn-sm btn-secondary" style="margin-left: auto" @click.stop="sync.flush()">
        {{ t('sync.retryNow') }}
      </button>
    </div>
    <div
      v-if="failed.length > 0"
      class="notice-bar bad"
      role="button"
      tabindex="0"
      @click="expanded = !expanded"
    >
      ⚠️ {{ t('sync.failed', failed.length) }}
    </div>

    <div v-if="expanded && records.length" class="card">
      <h3>{{ t('sync.pendingItems') }}</h3>
      <div v-for="r in records" :key="r.id" class="row">
        <div class="grow">
          <div class="name">{{ r.label }}</div>
          <div class="meta">
            {{ formatDate(r.createdAt) }} ·
            <span :class="['chip', r.status === 'failed' ? 'chip-bad' : 'chip-warn']">
              {{ r.status === 'failed' ? 'failed' : 'pending_sync' }}
            </span>
          </div>
          <div v-if="r.lastError" class="meta">{{ t('sync.lastError', { msg: r.lastError }) }}</div>
        </div>
        <button
          v-if="r.status === 'failed'"
          class="btn btn-sm btn-danger"
          @click="discard(r.id)"
        >
          {{ t('sync.deleteFailed') }}
        </button>
      </div>
    </div>
  </div>
</template>
