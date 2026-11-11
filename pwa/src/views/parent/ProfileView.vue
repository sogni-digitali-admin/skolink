<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import ProfilePanel from '@/components/ProfilePanel.vue'

const { t } = useI18n()
</script>

<template>
  <div>
    <h2 style="margin-top: 0">{{ t('profile.title') }}</h2>
    <ProfilePanel />
  </div>
</template>
