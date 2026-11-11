<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getChildAttendance } from '@/services/parent'
import { useSelectedChild } from '@/composables/useSelectedChild'
import { useInstallStore } from '@/stores/install'
import { ApiError } from '@/services/http'
import type { AttendanceDay } from '@/services/types'

const { t } = useI18n()
const { child } = useSelectedChild()
const install = useInstallStore()

const days = ref<AttendanceDay[]>([])
const loading = ref(false)
const offlineData = ref(false)
const error = ref<string | null>(null)

function statusChip(status: string): string {
  switch (status) {
    case 'absent':
      return 'chip-bad'
    case 'late':
      return 'chip-warn'
    case 'leave':
      return 'chip-soft'
    default:
      return 'chip-ok'
  }
}

async function load(studentId: number): Promise<void> {
  loading.value = true
  error.value = null
  offlineData.value = false
  try {
    days.value = await getChildAttendance(studentId)
    install.markValueMoment() // first value moment → install prompt may show
  } catch (err) {
    if (err instanceof ApiError && err.isNetwork) offlineData.value = true
    else error.value = err instanceof Error ? err.message : String(err)
  } finally {
    loading.value = false
  }
}

watch(
  child,
  (c) => {
    if (c) void load(c.id)
  },
  { immediate: true },
)
</script>

<template>
  <div>
    <h2 style="margin-top: 0">{{ t('nav.attendance') }} — {{ child?.name ?? '' }}</h2>
    <div v-if="offlineData" class="notice-bar warn">{{ t('common.offlineHint') }}</div>
    <div v-if="loading" class="spinner" role="status" />
    <div v-else-if="error" class="notice-bar bad">{{ error }}</div>
    <div v-else-if="!days.length" class="empty-state">{{ t('common.empty') }}</div>
    <div v-else class="card">
      <div v-for="(day, i) in days" :key="i" class="row">
        <div class="grow">
          <div class="name">{{ day.date }}</div>
          <div v-if="day.session" class="meta">{{ day.session }}</div>
          <div v-if="day.reason || day.remarks" class="meta">
            {{ day.reason }} {{ day.remarks }}
          </div>
        </div>
        <span :class="['chip', statusChip(day.status)]">{{ t(`parent.${day.status}`, day.status) }}</span>
      </div>
    </div>
  </div>
</template>
