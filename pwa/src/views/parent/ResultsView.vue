<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getChildResults } from '@/services/parent'
import { useSelectedChild } from '@/composables/useSelectedChild'
import { useInstallStore } from '@/stores/install'
import { ApiError } from '@/services/http'
import { gradeBand } from '@/utils/psac'
import type { PsacResult } from '@/services/types'

const { t } = useI18n()
const { child } = useSelectedChild()
const install = useInstallStore()

const result = ref<PsacResult | null>(null)
const loading = ref(false)
const notPublished = ref(false)
const error = ref<string | null>(null)

async function load(studentId: number): Promise<void> {
  loading.value = true
  error.value = null
  notPublished.value = false
  result.value = null
  try {
    const res = await getChildResults(studentId)
    if (res) {
      result.value = res
      install.markValueMoment()
    } else {
      notPublished.value = true
    }
  } catch (err) {
    if (err instanceof ApiError && err.status === 404) notPublished.value = true
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
    <h2 style="margin-top: 0">{{ t('parent.psac.title') }} — {{ child?.name ?? '' }}</h2>

    <div v-if="loading" class="spinner" role="status" />
    <div v-else-if="notPublished" class="empty-state">{{ t('parent.psac.notPublished') }}</div>
    <div v-else-if="error" class="notice-bar bad">{{ error }}</div>

    <template v-else-if="result">
      <div class="card">
        <h3>{{ result.examName }} · {{ result.year }}</h3>
        <div v-if="result.overallGrade" class="row" style="border-bottom: none">
          <span :class="['grade-chip', `grade-${gradeBand(result.overallGrade)}`]">
            {{ result.overallGrade }}
          </span>
          <div class="grow">
            <div class="name">{{ t('parent.psac.overall') }}</div>
            <div class="meta">{{ t(`parent.psac.${gradeBand(result.overallGrade)}`) }}</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div v-for="s in result.subjects" :key="s.subject" class="row">
          <span :class="['grade-chip', `grade-${gradeBand(s.grade)}`]">{{ s.grade }}</span>
          <div class="grow">
            <div class="name">{{ s.subject }}</div>
            <div class="meta">{{ t(`parent.psac.${gradeBand(s.grade)}`) }}</div>
          </div>
        </div>
      </div>

      <p class="meta" style="text-align: center">{{ t('parent.psac.legend') }}</p>
    </template>
  </div>
</template>
