<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useInstallStore } from '@/stores/install'

const { t } = useI18n()
const install = useInstallStore()
</script>

<template>
  <div v-if="install.canShowBanner" class="modal-backdrop" @click.self="install.dismiss()">
    <div class="modal" role="dialog" aria-modal="true" :aria-label="t('install.title')">
      <h2>{{ t('install.title') }}</h2>
      <p>{{ t('install.body') }}</p>
      <button class="btn" @click="install.promptInstall()">{{ t('install.button') }}</button>
      <button class="btn btn-ghost" style="margin-top: 8px" @click="install.dismiss()">
        {{ t('install.later') }}
      </button>
    </div>
  </div>
</template>
