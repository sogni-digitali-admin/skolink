<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { createPayment, getChildFees } from '@/services/parent'
import { useSelectedChild } from '@/composables/useSelectedChild'
import { ApiError } from '@/services/http'
import type { FeeAccount } from '@/services/types'

const { t } = useI18n()
const { child } = useSelectedChild()

const account = ref<FeeAccount | null>(null)
const loading = ref(false)
const unavailable = ref(false)
const error = ref<string | null>(null)
const paying = ref(false)
const paymentRef = ref<string | null>(null)

function fmt(amount: number, currency: string): string {
  return `${currency} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}`
}

async function load(studentId: number): Promise<void> {
  loading.value = true
  error.value = null
  unavailable.value = false
  try {
    account.value = await getChildFees(studentId)
  } catch (err) {
    // Fees endpoints are commented out in the current backend; treat 404 as "not available".
    if (err instanceof ApiError && (err.status === 404 || err.status === 405)) unavailable.value = true
    else error.value = err instanceof Error ? err.message : String(err)
  } finally {
    loading.value = false
  }
}

async function pay(): Promise<void> {
  if (!account.value || !child.value) return
  paying.value = true
  error.value = null
  try {
    const res = await createPayment({
      studentId: child.value.id,
      amount: account.value.balanceDue,
      currency: account.value.currency,
      method: 'card',
    })
    if (res.checkoutUrl) {
      window.location.href = res.checkoutUrl
      return
    }
    paymentRef.value = res.reference
  } catch (err) {
    if (err instanceof ApiError && (err.status === 404 || err.status === 405)) unavailable.value = true
    else error.value = err instanceof Error ? err.message : String(err)
  } finally {
    paying.value = false
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
    <h2 style="margin-top: 0">
      {{ t('parent.fees.title') }} — {{ child?.name ?? '' }}
      <span class="beta-tag">{{ t('common.beta') }}</span>
    </h2>

    <div v-if="loading" class="spinner" role="status" />
    <div v-else-if="unavailable" class="empty-state">{{ t('parent.fees.paymentUnavailable') }}</div>
    <div v-else-if="error" class="notice-bar bad">{{ error }}</div>

    <template v-else-if="account">
      <div class="card" style="text-align: center">
        <div class="meta">{{ t('parent.fees.balance') }}</div>
        <div style="font-size: 1.8rem; font-weight: 700">
          {{ fmt(account.balanceDue, account.currency) }}
        </div>
      </div>

      <div v-if="account.lines.length" class="card">
        <div v-for="line in account.lines" :key="String(line.id)" class="row">
          <div class="grow">
            <div class="name">{{ line.label }}</div>
            <div v-if="line.dueDate" class="meta">{{ t('common.dueDate') }}: {{ line.dueDate }}</div>
          </div>
          <div style="text-align: right">
            <div>{{ fmt(line.amount, line.currency) }}</div>
            <span
              :class="['chip', line.status === 'paid' ? 'chip-ok' : line.status === 'overdue' ? 'chip-bad' : 'chip-warn']"
            >
              {{ t(`parent.fees.${line.status}`, line.status) }}
            </span>
          </div>
        </div>
      </div>

      <div v-if="paymentRef" class="notice-bar ok">
        {{ t('parent.fees.paymentStarted', { ref: paymentRef }) }}
      </div>

      <template v-if="account.balanceDue > 0">
        <p class="meta">{{ t('parent.fees.payBetaNote') }}</p>
        <button class="btn" :disabled="paying" @click="pay">
          {{ paying ? t('common.loading') : t('parent.fees.payNow') }}
        </button>
      </template>
      <div v-else class="notice-bar ok">{{ t('parent.fees.noDues') }}</div>
    </template>
  </div>
</template>
