<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { LoginFailedError } from '@/services/auth'
import { ApiError } from '@/services/http'
import { setLocale, getStoredLocale, type AppLocale } from '@/i18n'
import type { Role } from '@/services/types'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const role = ref<Role>('parent')
const mobile = ref('')
const password = ref('')
const busy = ref(false)
const error = ref<string | null>(null)
const locale = ref<AppLocale>(getStoredLocale())

function switchLocale(l: AppLocale): void {
  locale.value = l
  setLocale(l)
}

async function submit(): Promise<void> {
  if (busy.value) return
  error.value = null
  busy.value = true
  try {
    await auth.login(role.value, mobile.value.trim(), password.value)
    await router.push(auth.homeRoute)
  } catch (err) {
    if (err instanceof ApiError && err.isNetwork) error.value = t('auth.networkError')
    else if (err instanceof ApiError && err.status === 422 && /device/i.test(err.message))
      error.value = t('auth.deviceConflict')
    else if (err instanceof LoginFailedError || (err instanceof ApiError && err.status === 422))
      error.value = t('auth.invalidCredentials')
    else error.value = t('common.error')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="login-wrap">
    <div class="login-logo">
      <img src="/favicon.svg" :alt="t('common.appName')" />
      <h1>{{ t('common.appName') }}</h1>
      <p>{{ t('auth.subtitle') }}</p>
    </div>

    <div class="role-tabs" role="tablist">
      <button
        class="role-tab"
        :class="{ active: role === 'parent' }"
        role="tab"
        @click="role = 'parent'"
      >
        {{ t('auth.parentTab') }}
      </button>
      <button
        class="role-tab"
        :class="{ active: role === 'teacher' }"
        role="tab"
        @click="role = 'teacher'"
      >
        {{ t('auth.teacherTab') }}
      </button>
    </div>

    <form @submit.prevent="submit">
      <div class="field">
        <label for="mobile">{{ t('auth.mobile') }}</label>
        <input
          id="mobile"
          v-model="mobile"
          type="tel"
          inputmode="tel"
          autocomplete="username"
          :placeholder="t('auth.mobilePlaceholder')"
          required
        />
      </div>
      <div class="field">
        <label for="password">{{ t('auth.password') }}</label>
        <input
          id="password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          required
        />
      </div>
      <div v-if="error" class="notice-bar bad" role="alert">{{ error }}</div>
      <button class="btn" type="submit" :disabled="busy">
        {{ busy ? t('auth.signingIn') : t('auth.signIn') }}
      </button>
    </form>

    <div class="role-tabs" style="margin-top: 18px">
      <button class="role-tab" :class="{ active: locale === 'en' }" @click="switchLocale('en')">
        English
      </button>
      <button class="role-tab" :class="{ active: locale === 'fr' }" @click="switchLocale('fr')">
        Français
      </button>
    </div>
  </div>
</template>
