<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { getTeacherHomeworks } from '@/services/teacher'
import type { HomeworkItem } from '@/services/types'

const { t } = useI18n()
const router = useRouter()

const items = ref<HomeworkItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    items.value = await getTeacherHomeworks()
  } catch (err) {
    error.value = err instanceof Error ? err.message : String(err)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div style="display: flex; align-items: center; justify-content: space-between">
      <h2 style="margin: 0">{{ t('teacher.homework') }}</h2>
      <button class="btn btn-sm" style="width: auto" @click="router.push({ name: 'teacher-homework-assign' })">
        ＋ {{ t('teacher.assignHomework') }}
      </button>
    </div>

    <div v-if="loading" class="spinner" role="status" />
    <div v-else-if="error" class="notice-bar bad">{{ error }}</div>
    <div v-else-if="!items.length" class="empty-state">{{ t('common.empty') }}</div>

    <div v-for="hw in items" :key="hw.id" class="card">
      <h3>{{ hw.title }}</h3>
      <div class="meta">
        <span v-if="hw.subject">{{ hw.subject }}</span>
        <span v-if="hw.dueDate"> · {{ t('common.dueDate') }}: {{ hw.dueDate }}</span>
      </div>
      <button
        class="btn btn-sm btn-secondary"
        style="margin-top: 8px"
        @click="router.push({ name: 'teacher-homework-grade', params: { id: hw.id } })"
      >
        {{ t('teacher.gradeSubmissions') }}
      </button>
    </div>
  </div>
</template>
