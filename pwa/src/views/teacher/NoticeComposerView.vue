<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { createClassNotice, getTeacherClassNotices, getTeacherClasses } from '@/services/teacher'
import { useAuthStore } from '@/stores/auth'
import { ApiError } from '@/services/http'
import type { NoticeItem, TeacherClass } from '@/services/types'

const { t } = useI18n()
const auth = useAuthStore()

const classes = ref<TeacherClass[]>([])
const notices = ref<NoticeItem[]>([])
const standardLinkId = ref<number | null>(null)
const title = ref('')
const body = ref('')
const sending = ref(false)
const sent = ref(false)
const unsupported = ref(false)
const error = ref<string | null>(null)

async function loadAll(): Promise<void> {
  error.value = null
  try {
    classes.value = await getTeacherClasses()
  } catch {
    classes.value = []
  }
  try {
    notices.value = await getTeacherClassNotices(auth.userId)
  } catch (err) {
    error.value = err instanceof Error ? err.message : String(err)
  }
}

async function submit(): Promise<void> {
  if (!standardLinkId.value || sending.value) return
  sending.value = true
  error.value = null
  sent.value = false
  unsupported.value = false
  try {
    await createClassNotice({ standardLinkId: standardLinkId.value, title: title.value, body: body.value })
    sent.value = true
    title.value = ''
    body.value = ''
  } catch (err) {
    // Teacher notice creation does not exist in the backend yet (assumed endpoint).
    if (err instanceof ApiError && (err.status === 404 || err.status === 405)) unsupported.value = true
    else error.value = err instanceof Error ? err.message : String(err)
  } finally {
    sending.value = false
  }
}

onMounted(loadAll)
</script>

<template>
  <div>
    <h2 style="margin-top: 0">{{ t('teacher.notices') }}</h2>

    <div class="card">
      <h3>{{ t('teacher.composeNotice') }}</h3>
      <form @submit.prevent="submit">
        <div class="field">
          <label for="notice-class">{{ t('teacher.classField') }}</label>
          <select id="notice-class" v-model="standardLinkId" required>
            <option :value="null" disabled>—</option>
            <option v-for="c in classes" :key="c.standardLinkId" :value="c.standardLinkId">
              {{ c.className }}<template v-if="c.section"> {{ c.section }}</template>
            </option>
          </select>
        </div>
        <div class="field">
          <label for="notice-title">{{ t('teacher.titleField') }}</label>
          <input id="notice-title" v-model="title" required />
        </div>
        <div class="field">
          <label for="notice-body">{{ t('teacher.description') }}</label>
          <textarea id="notice-body" v-model="body" rows="4" required />
        </div>
        <div v-if="unsupported" class="notice-bar warn">{{ t('teacher.noticeUnsupported') }}</div>
        <div v-else-if="error" class="notice-bar bad">{{ error }}</div>
        <div v-if="sent" class="notice-bar ok">{{ t('teacher.noticeSent') }}</div>
        <button class="btn" type="submit" :disabled="sending">{{ t('common.submit') }}</button>
      </form>
    </div>

    <div v-for="notice in notices" :key="notice.id" class="card">
      <h3>{{ notice.title }}</h3>
      <div v-if="notice.date" class="meta">{{ notice.date }}</div>
      <p v-if="notice.body" style="margin-bottom: 0">{{ notice.body }}</p>
    </div>
  </div>
</template>
