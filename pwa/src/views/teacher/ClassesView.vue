<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { getTeacherClasses } from '@/services/teacher'
import type { TeacherClass } from '@/services/types'

const { t } = useI18n()
const router = useRouter()

const classes = ref<TeacherClass[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    classes.value = await getTeacherClasses()
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
    <h2 style="margin-top: 0">{{ t('teacher.myClasses') }}</h2>

    <div v-if="loading" class="spinner" role="status" />
    <div v-else-if="error" class="notice-bar bad">
      {{ error }}
      <button class="btn btn-sm btn-secondary" style="margin-left: auto" @click="load">
        {{ t('common.retry') }}
      </button>
    </div>
    <div v-else-if="!classes.length" class="empty-state">{{ t('common.empty') }}</div>

    <div v-for="cls in classes" :key="cls.standardLinkId" class="card">
      <div class="row" style="border-bottom: none; padding: 0">
        <div class="grow">
          <div class="name">{{ cls.className }}<span v-if="cls.section"> {{ cls.section }}</span></div>
          <div v-if="cls.subjectName" class="meta">{{ cls.subjectName }}</div>
        </div>
        <button class="btn btn-sm" @click="router.push({ name: 'teacher-attendance' })">
          {{ t('teacher.takeAttendance') }}
        </button>
      </div>
    </div>
  </div>
</template>
