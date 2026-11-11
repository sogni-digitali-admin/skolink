<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import AppHeader from '@/components/AppHeader.vue'
import BottomNav from '@/components/BottomNav.vue'
import SyncBadge from '@/components/SyncBadge.vue'

const { t } = useI18n()
</script>

<template>
  <AppHeader :title="t('common.appName')" />
  <main class="page">
    <SyncBadge />
    <router-view />
  </main>
  <BottomNav role="teacher" />
</template>
