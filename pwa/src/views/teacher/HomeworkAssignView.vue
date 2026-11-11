<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import {
  createHomework,
  getHomeworkCreateMeta,
  getHomeworkSubjects,
} from '@/services/teacher'
import type { TeacherClass } from '@/services/types'

const { t } = useI18n()
const router = useRouter()

const classes = ref<TeacherClass[]>([])
const subjects = ref<string[]>([])
const standardLinkId = ref<number | null>(null)
const subject = ref('')
const title = ref('')
const description = ref('')
const dueDate = ref('')
const sending = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const meta = await getHomeworkCreateMeta()
    classes.value = meta.classes
  } catch (err) {
    error.value = err instanceof Error ? err.message : String(err)
  }
})

watch(standardLinkId, async (id) => {
  subjects.value = []
  subject.value = ''
  if (!id) return
  try {
    subjects.value = await getHomeworkSubjects(id)
  } catch {
    // Subject list is optional — teacher can type it manually below.
  }
})

async function submit(): Promise<void> {
  if (!standardLinkId.value || sending.value) return
  sending.value = true
  error.value = null
  try {
    await createHomework({
      standardLinkId: standardLinkId.value,
      subject: subject.value,
      title: title.value,
      description: description.value,
      dueDate: dueDate.value,
    })
    await router.push({ name: 'teacher-homework' })
  } catch (err) {
    error.value = err instanceof Error ? err.message : String(err)
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div>
    <h2 style="margin-top: 0">{{ t('teacher.assignHomework') }}</h2>
    <div class="card">
      <form @submit.prevent="submit">
        <div class="field">
          <label for="hw-class">{{ t('teacher.classField') }}</label>
          <select id="hw-class" v-model="standardLinkId" required>
            <option :value="null" disabled>—</option>
            <option v-for="c in classes" :key="c.standardLinkId" :value="c.standardLinkId">
              {{ c.className }}<template v-if="c.section"> {{ c.section }}</template>
            </option>
          </select>
        </div>
        <div class="field">
          <label for="hw-subject">{{ t('common.subject') }}</label>
          <select v-if="subjects.length" id="hw-subject" v-model="subject" required>
            <option value="" disabled>—</option>
            <option v-for="s in subjects" :key="s" :value="s">{{ s }}</option>
          </select>
          <input v-else id="hw-subject" v-model="subject" required />
        </div>
        <div class="field">
          <label for="hw-title">{{ t('teacher.titleField') }}</label>
          <input id="hw-title" v-model="title" required />
        </div>
        <div class="field">
          <label for="hw-desc">{{ t('teacher.description') }}</label>
          <textarea id="hw-desc" v-model="description" rows="4" required />
        </div>
        <div class="field">
          <label for="hw-due">{{ t('common.dueDate') }}</label>
          <input id="hw-due" v-model="dueDate" type="date" required />
        </div>
        <div v-if="error" class="notice-bar bad">{{ error }}</div>
        <button class="btn" type="submit" :disabled="sending">{{ t('common.submit') }}</button>
      </form>
    </div>
  </div>
</template>
