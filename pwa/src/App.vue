<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { getSchoolDetails } from '@/services/parent'
import InstallBanner from '@/components/InstallBanner.vue'

const auth = useAuthStore()

async function applySchoolTheme(): Promise<void> {
  if (!auth.isAuthenticated) return
  try {
    const school = await getSchoolDetails()
    if (school.themeColor && /^#[0-9a-f]{6}$/i.test(school.themeColor)) {
      document.documentElement.style.setProperty('--brand', school.themeColor)
    }
  } catch {
    // Theming is cosmetic — ignore failures.
  }
}

onMounted(applySchoolTheme)
</script>

<template>
  <router-view />
  <InstallBanner />
</template>
