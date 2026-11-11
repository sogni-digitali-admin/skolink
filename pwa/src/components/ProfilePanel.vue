<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { setLocale, getStoredLocale, type AppLocale } from '@/i18n'
import PushToggle from '@/components/PushToggle.vue'
import { useInstallStore } from '@/stores/install'
import { ref } from 'vue'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const install = useInstallStore()

const locale = ref<AppLocale>(getStoredLocale())
function switchLocale(l: AppLocale): void {
  locale.value = l
  setLocale(l)
}

async function doLogout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div>
    <div class="card">
      <div class="row" style="border-bottom: none">
        <div class="avatar">{{ auth.userName.charAt(0).toUpperCase() || '?' }}</div>
        <div class="grow">
          <div class="name">{{ auth.userName }}</div>
          <div class="meta">{{ t('profile.signedInAs') }} · {{ auth.userEmail || auth.userId }}</div>
          <div v-if="auth.designation" class="meta">{{ auth.designation }}</div>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>{{ t('profile.language') }}</h3>
      <div class="role-tabs">
        <button class="role-tab" :class="{ active: locale === 'en' }" @click="switchLocale('en')">
          English
        </button>
        <button class="role-tab" :class="{ active: locale === 'fr' }" @click="switchLocale('fr')">
          Français
        </button>
      </div>
    </div>

    <PushToggle />

    <div v-if="install.isIos && !install.installed" class="card">
      <h3>📲 {{ t('install.title') }}</h3>
      <p class="meta">{{ t('install.iosBody') }}</p>
    </div>

    <div class="card">
      <p class="meta">{{ t('profile.deviceInfo') }}</p>
      <button class="btn btn-danger" @click="doLogout">{{ t('common.logout') }}</button>
    </div>
  </div>
</template>
