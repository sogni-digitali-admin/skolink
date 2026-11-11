<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getClassNotices, getSchoolNotices } from '@/services/parent'
import { useSelectedChild } from '@/composables/useSelectedChild'
import type { NoticeItem } from '@/services/types'

const { t } = useI18n()
const { child } = useSelectedChild()

const tab = ref<'school' | 'class'>('school')
const items = ref<NoticeItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const openId = ref<number | null>(null)

async function load(studentId: number): Promise<void> {
  loading.value = true
  error.value = null
  try {
    items.value = tab.value === 'school' ? await getSchoolNotices() : await getClassNotices(studentId)
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
    <h2 style="margin-top: 0">{{ t('parent.notices') }}</h2>
    <div class="role-tabs">
      <button class="role-tab" :class="{ active: tab === 'school' }" @click="tab = 'school'">
        {{ t('parent.schoolNotices') }}
      </button>
      <button class="role-tab" :class="{ active: tab === 'class' }" @click="tab = 'class'">
        {{ t('parent.classNotices') }}
      </button>
    </div>

    <div v-if="loading" class="spinner" role="status" />
    <div v-else-if="error" class="notice-bar bad">{{ error }}</div>
    <div v-else-if="!items.length" class="empty-state">{{ t('common.empty') }}</div>
    <div v-for="notice in items" :key="notice.id" class="card">
      <div role="button" tabindex="0" @click="openId = openId === notice.id ? null : notice.id">
        <h3>{{ notice.title }}</h3>
        <div v-if="notice.date" class="meta">{{ notice.date }}</div>
      </div>
      <p v-if="openId === notice.id && notice.body" style="margin-bottom: 0">{{ notice.body }}</p>
    </div>
  </div>
</template>
