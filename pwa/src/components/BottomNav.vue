<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { Role } from '@/services/types'

const props = defineProps<{ role: Role }>()
const route = useRoute()
const { t } = useI18n()

interface NavItem {
  to: string
  icon: string
  labelKey: string
}

const parentItems: NavItem[] = [
  { to: '/p/home', icon: '🏠', labelKey: 'nav.home' },
  { to: '/p/attendance', icon: '📋', labelKey: 'nav.attendance' },
  { to: '/p/homework', icon: '📚', labelKey: 'nav.homework' },
  { to: '/p/notices', icon: '📢', labelKey: 'nav.notices' },
  { to: '/p/profile', icon: '👤', labelKey: 'nav.profile' },
]

const teacherItems: NavItem[] = [
  { to: '/t/home', icon: '🏠', labelKey: 'nav.home' },
  { to: '/t/attendance', icon: '✅', labelKey: 'nav.attendance' },
  { to: '/t/homework', icon: '📚', labelKey: 'nav.homework' },
  { to: '/t/notices', icon: '📢', labelKey: 'nav.notices' },
  { to: '/t/profile', icon: '👤', labelKey: 'nav.profile' },
]

const items = props.role === 'parent' ? parentItems : teacherItems
const isActive = (to: string) => route.path.startsWith(to)
</script>

<template>
  <nav class="bottom-nav">
    <router-link v-for="item in items" :key="item.to" :to="to" :class="{ active: isActive(to) }">
      <span class="ico" aria-hidden="true">{{ item.icon }}</span>
      <span>{{ t(item.labelKey) }}</span>
    </router-link>
  </nav>
</template>
