<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { getHomeworkSubmissions, gradeSubmission } from '@/services/teacher'
import type { SubmissionItem } from '@/services/types'

const { t } = useI18n()
const route = useRoute()
const homeworkId = Number(route.params.id)

const submissions = ref<SubmissionItem[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const grades = ref<Record<number, string>>({})
const remarksMap = ref<Record<number, string>>({})
const savedIds = ref<Set<number>>(new Set())

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    submissions.value = await getHomeworkSubmissions(homeworkId)
    for (const s of submissions.value) {
      grades.value[s.id] = s.grade != null ? String(s.grade) : ''
      remarksMap.value[s.id] = s.remarks ?? ''
    }
  } catch (err) {
    error.value = err instanceof Error ? err.message : String(err)
  } finally {
    loading.value = false
  }
}

async function saveGrade(submissionId: number): Promise<void> {
  error.value = null
  try {
    await gradeSubmission(submissionId, grades.value[submissionId] ?? '', remarksMap.value[submissionId] ?? '')
    savedIds.value = new Set([...savedIds.value, submissionId])
  } catch (err) {
    error.value = err instanceof Error ? err.message : String(err)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <h2 style="margin-top: 0">{{ t('teacher.gradeSubmissions') }} #{{ homeworkId }}</h2>

    <div v-if="loading" class="spinner" role="status" />
    <div v-else-if="error" class="notice-bar bad">{{ error }}</div>
    <div v-else-if="!submissions.length" class="empty-state">{{ t('common.empty') }}</div>

    <div v-for="sub in submissions" :key="sub.id" class="card">
      <div class="row" style="border-bottom: none; padding: 0 0 8px">
        <div class="grow">
          <div class="name">{{ sub.studentName }}</div>
          <div v-if="sub.submittedAt" class="meta">{{ sub.submittedAt }}</div>
        </div>
        <span v-if="savedIds.has(sub.id)" class="chip chip-ok">{{ t('teacher.graded') }}</span>
      </div>
      <p v-if="sub.answer" class="meta" style="white-space: pre-wrap">{{ sub.answer }}</p>
      <div class="grid2">
        <div class="field">
          <label :for="`grade-${sub.id}`">{{ t('teacher.grade') }}</label>
          <input :id="`grade-${sub.id}`" v-model="grades[sub.id]" />
        </div>
        <div class="field">
          <label :for="`remarks-${sub.id}`">{{ t('common.remarks') }}</label>
          <input :id="`remarks-${sub.id}`" v-model="remarksMap[sub.id]" />
        </div>
      </div>
      <button class="btn btn-sm btn-secondary" @click="saveGrade(sub.id)">
        {{ t('teacher.saveGrade') }}
      </button>
    </div>
  </div>
</template>
