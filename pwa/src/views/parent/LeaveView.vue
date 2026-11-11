<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getLeaves, getLeaveTypes, submitLeave } from '@/services/parent'
import { useSelectedChild } from '@/composables/useSelectedChild'
import type { LeaveItem, LeaveType } from '@/services/types'

const { t } = useI18n()
const { child } = useSelectedChild()

const leaveTypes = ref<LeaveType[]>([])
const history = ref<LeaveItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const leaveTypeId = ref<number | null>(null)
const fromDate = ref('')
const toDate = ref('')
const reason = ref('')
const sending = ref(false)
const sent = ref(false)

function statusChip(status?: string): string {
  switch ((status ?? '').toLowerCase()) {
    case 'approved':
      return 'chip-ok'
    case 'rejected':
      return 'chip-bad'
    default:
      return 'chip-warn'
  }
}

async function load(studentId: number): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [types, leaves] = await Promise.all([getLeaveTypes(), getLeaves(studentId)])
    leaveTypes.value = types
    history.value = leaves
  } catch (err) {
    error.value = err instanceof Error ? err.message : String(err)
  } finally {
    loading.value = false
  }
}

async function submit(): Promise<void> {
  if (!child.value || sending.value) return
  sending.value = true
  error.value = null
  sent.value = false
  try {
    await submitLeave(child.value.id, {
      leaveTypeId: leaveTypeId.value,
      fromDate: fromDate.value,
      toDate: toDate.value,
      reason: reason.value,
    })
    sent.value = true
    reason.value = ''
    await load(child.value.id)
  } catch (err) {
    error.value = err instanceof Error ? err.message : String(err)
  } finally {
    sending.value = false
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
    <h2 style="margin-top: 0">{{ t('parent.leave.title') }} — {{ child?.name ?? '' }}</h2>

    <div class="card">
      <h3>{{ t('parent.leave.new') }}</h3>
      <form @submit.prevent="submit">
        <div class="field">
          <label for="leave-type">{{ t('parent.leave.type') }}</label>
          <select id="leave-type" v-model="leaveTypeId">
            <option :value="null">—</option>
            <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }}</option>
          </select>
        </div>
        <div class="grid2">
          <div class="field">
            <label for="leave-from">{{ t('parent.leave.from') }}</label>
            <input id="leave-from" v-model="fromDate" type="date" required />
          </div>
          <div class="field">
            <label for="leave-to">{{ t('parent.leave.to') }}</label>
            <input id="leave-to" v-model="toDate" type="date" required />
          </div>
        </div>
        <div class="field">
          <label for="leave-reason">{{ t('parent.leave.reasonLabel') }}</label>
          <textarea
            id="leave-reason"
            v-model="reason"
            rows="3"
            :placeholder="t('parent.leave.reasonPlaceholder')"
            required
          />
        </div>
        <button class="btn" type="submit" :disabled="sending">{{ t('common.submit') }}</button>
      </form>
      <div v-if="sent" class="notice-bar ok" style="margin-top: 10px">
        {{ t('parent.leave.submitted') }}
      </div>
      <div v-if="error" class="notice-bar bad" style="margin-top: 10px">{{ error }}</div>
    </div>

    <h3>{{ t('parent.leave.history') }}</h3>
    <div v-if="loading" class="spinner" role="status" />
    <div v-else-if="!history.length" class="empty-state">{{ t('common.empty') }}</div>
    <div v-for="leave in history" :key="leave.id" class="card">
      <div class="row" style="border-bottom: none; padding: 0">
        <div class="grow">
          <div class="name">{{ leave.fromDate }} → {{ leave.toDate }}</div>
          <div v-if="leave.reason" class="meta">{{ leave.reason }}</div>
        </div>
        <span :class="['chip', statusChip(leave.status)]">
          {{ t(`parent.leave.${(leave.status ?? 'pending').toLowerCase()}`, leave.status ?? '') }}
        </span>
      </div>
    </div>
  </div>
</template>
