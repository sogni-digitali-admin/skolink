<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAttendanceRoster } from '@/services/teacher'
import { cacheRoster, getCachedRoster, enqueue } from '@/offline/outbox'
import { useSyncStore } from '@/stores/sync'
import { useInstallStore } from '@/stores/install'
import { registerAttendanceSync } from '@/services/push'
import { ApiError } from '@/services/http'
import type { AttendanceRoster, AttendanceSubmitPayload } from '@/services/types'

const ROSTER_KEY = 'attendance-roster'

const { t } = useI18n()
const sync = useSyncStore()
const install = useInstallStore()

const roster = ref<AttendanceRoster | null>(null)
const rosterFromCacheAt = ref<number | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const today = new Date().toISOString().slice(0, 10)
const date = ref(today)
const session = ref<'morning' | 'afternoon'>('morning')
const absentIds = ref<Set<number>>(new Set())
const reasons = ref<Record<number, number | null>>({})
const remarks = ref<Record<number, string>>({})
const submitting = ref(false)
const feedback = ref<'saved-offline' | 'sent' | null>(null)

const absentCount = computed(() => absentIds.value.size)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const fresh = await getAttendanceRoster()
    roster.value = fresh
    rosterFromCacheAt.value = null
    await cacheRoster(ROSTER_KEY, fresh)
  } catch (err) {
    if (err instanceof ApiError && err.isNetwork) {
      const cached = await getCachedRoster(ROSTER_KEY)
      if (cached) {
        roster.value = cached.roster
        rosterFromCacheAt.value = cached.savedAt
      } else {
        error.value = t('common.error')
      }
    } else {
      error.value = err instanceof Error ? err.message : String(err)
    }
  } finally {
    loading.value = false
  }
}

function toggleAbsent(studentId: number): void {
  const next = new Set(absentIds.value)
  if (next.has(studentId)) next.delete(studentId)
  else next.add(studentId)
  absentIds.value = next
}

async function submit(): Promise<void> {
  if (!roster.value || submitting.value) return
  submitting.value = true
  feedback.value = null
  error.value = null

  const classId = roster.value.classes[0]?.standardLinkId ?? 0
  const payload: AttendanceSubmitPayload = {
    Standardlinkid: classId,
    Date: date.value,
    Session: session.value,
    AbsentList: [...absentIds.value].map((student_id) => ({
      student_id,
      reasonId: reasons.value[student_id] ?? null,
      remarks: remarks.value[student_id] ?? '',
    })),
  }

  const className = roster.value.classes[0]?.className ?? ''
  const label = `${className} — ${date.value}, ${session.value}, ${absentCount.value} absent`

  // Write to the outbox FIRST: marks are never lost, even offline.
  await enqueue(payload, label)
  await sync.refreshPending()
  await registerAttendanceSync()
  if (sync.online) await sync.flush()
  await sync.refreshPending()

  feedback.value = sync.pendingCount === 0 ? 'sent' : 'saved-offline'
  absentIds.value = new Set()
  reasons.value = {}
  remarks.value = {}
  submitting.value = false
  install.markValueMoment()
}

onMounted(load)
</script>

<template>
  <div>
    <h2 style="margin-top: 0">{{ t('teacher.takeAttendance') }}</h2>

    <div v-if="loading" class="spinner" role="status" />
    <div v-else-if="error" class="notice-bar bad">
      {{ error }}
      <button class="btn btn-sm btn-secondary" style="margin-left: auto" @click="load">
        {{ t('common.retry') }}
      </button>
    </div>

    <template v-else-if="roster">
      <div v-if="rosterFromCacheAt" class="notice-bar warn">
        {{ t('teacher.rosterOffline', { date: new Date(rosterFromCacheAt).toLocaleString() }) }}
      </div>

      <div class="card">
        <div class="grid2">
          <div class="field">
            <label for="att-date">{{ t('common.date') }}</label>
            <input id="att-date" v-model="date" type="date" />
          </div>
          <div class="field">
            <label for="att-session">{{ t('common.session') }}</label>
            <select id="att-session" v-model="session">
              <option value="morning">{{ t('common.morning') }}</option>
              <option value="afternoon">{{ t('common.afternoon') }}</option>
            </select>
          </div>
        </div>
        <p class="meta" style="margin: 0">
          {{ absentCount === 0 ? t('teacher.allPresent') : t('teacher.absentCount', absentCount) }}
          · {{ t('teacher.markAbsent') }}
        </p>
      </div>

      <div class="card">
        <div v-for="student in roster.students" :key="student.id" style="border-bottom: 1px solid var(--line)">
          <div
            class="row"
            role="button"
            tabindex="0"
            :aria-pressed="absentIds.has(student.id)"
            @click="toggleAbsent(student.id)"
            @keydown.enter="toggleAbsent(student.id)"
          >
            <div class="avatar">{{ student.name.charAt(0).toUpperCase() }}</div>
            <div class="grow">
              <div class="name">{{ student.name }}</div>
              <div v-if="student.rollNo" class="meta">№ {{ student.rollNo }}</div>
            </div>
            <span :class="['chip', absentIds.has(student.id) ? 'chip-bad' : 'chip-ok']">
              {{ absentIds.has(student.id) ? t('parent.absent') : t('parent.present') }}
            </span>
          </div>
          <div v-if="absentIds.has(student.id)" style="padding: 0 0 12px 54px">
            <div class="field" style="margin-bottom: 8px">
              <select
                :value="reasons[student.id] ?? ''"
                @change="reasons[student.id] = Number(($event.target as HTMLSelectElement).value) || null"
              >
                <option value="">{{ t('common.reason') }} ({{ t('common.optional') }})</option>
                <option v-for="r in roster.absentReasons" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
            </div>
            <input
              :value="remarks[student.id] ?? ''"
              :placeholder="`${t('common.remarks')} (${t('common.optional')})`"
              style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px"
              @input="remarks[student.id] = ($event.target as HTMLInputElement).value"
            />
          </div>
        </div>
      </div>

      <div v-if="feedback === 'sent'" class="notice-bar ok">✅ {{ t('teacher.attendanceSent') }}</div>
      <div v-else-if="feedback === 'saved-offline'" class="notice-bar warn">
        🕓 {{ t('teacher.attendanceSaved') }} — {{ t('sync.pending', sync.pendingCount) }}
      </div>

      <button class="btn" :disabled="submitting" @click="submit">
        {{ submitting ? t('common.loading') : t('teacher.submitSheet') }}
      </button>
    </template>
  </div>
</template>
