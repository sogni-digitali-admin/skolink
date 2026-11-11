<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getChildHomework } from '@/services/parent'
import { useSelectedChild } from '@/composables/useSelectedChild'
import type { HomeworkItem } from '@/services/types'

const { t } = useI18n()
const { child } = useSelectedChild()

const tab = ref<'pending' | 'finished'>('pending')
const items = ref<HomeworkItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

async function load(studentId: number): Promise<void> {
  loading.value = true
  error.value = null
  try {
    items.value = await getChildHomework(studentId, tab.value)
  } catch (err) {
    error.value = err instanceof Error ? err.message : String(err)
    items.value = []
  } finally {
    loading.value = false
  }
}

watch(
  [child, tab],
  ([c]) => {
    if (c) void load(c.id)
  },
  { immediate: true },
)
</script>

<template>
  <div>
    <h2 style="margin-top: 0">{{ t('nav.homework') }} — {{ child?.name ?? '' }}</h2>
    <div class="role-tabs">
      <button class="role-tab" :class="{ active: tab === 'pending' }" @click="tab = 'pending'">
        {{ t('parent.homeworkPending') }}
      </button>
      <button class="role-tab" :class="{ active: tab === 'finished' }" @click="tab = 'finished'">
        {{ t('parent.homeworkDone') }}
      </button>
    </div>

    <div v-if="loading" class="spinner" role="status" />
    <div v-else-if="error" class="notice-bar bad">{{ error }}</div>
    <div v-else-if="!items.length" class="empty-state">{{ t('parent.noHomework') }}</div>
    <div v-for="hw in items" :key="hw.id" class="card">
      <h3>{{ hw.title }}</h3>
      <div class="meta">
        <span v-if="hw.subject">{{ hw.subject }}</span>
        <span v-if="hw.dueDate"> · {{ t('common.dueDate') }}: {{ hw.dueDate }}</span>
      </div>
      <p v-if="hw.description" style="margin-bottom: 0">{{ hw.description }}</p>
    </div>
  </div>
</template>
