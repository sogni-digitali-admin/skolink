<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useParentStore } from '@/stores/parent'

const { t } = useI18n()
const router = useRouter()
const parent = useParentStore()

function pick(id: number): void {
  parent.selectChild(id)
  void router.push({ name: 'parent-attendance' })
}

const quickLinks = [
  { name: 'parent-attendance', icon: '📋', label: 'nav.attendance' },
  { name: 'parent-homework', icon: '📚', label: 'nav.homework' },
  { name: 'parent-results', icon: '🎓', label: 'nav.results' },
  { name: 'parent-fees', icon: '💳', label: 'nav.fees' },
  { name: 'parent-leave', icon: '📝', label: 'nav.leave' },
  { name: 'parent-notices', icon: '📢', label: 'nav.notices' },
]

onMounted(() => parent.loadChildren())
</script>

<template>
  <div>
    <p class="meta" style="margin-top: 0">{{ t('parent.selectChild') }}</p>

    <div v-if="parent.loading" class="spinner" role="status" />
    <div v-else-if="parent.error" class="notice-bar bad">
      {{ parent.error }}
      <button class="btn btn-sm btn-secondary" style="margin-left: auto" @click="parent.loadChildren(true)">
        {{ t('common.retry') }}
      </button>
    </div>
    <div v-else-if="!parent.children.length" class="empty-state">{{ t('parent.noChildren') }}</div>

    <div v-for="child in parent.children" :key="child.id" class="card">
      <div class="row" style="border-bottom: none; padding: 0">
        <div class="avatar">{{ (child.name || '?').charAt(0).toUpperCase() }}</div>
        <div class="grow">
          <div class="name">{{ child.name }}</div>
          <div v-if="child.className" class="meta">{{ child.className }}</div>
        </div>
        <span v-if="child.id === parent.selectedChildId" class="chip chip-ok">✓</span>
        <button class="btn btn-sm btn-secondary" @click="pick(child.id)">{{ t('nav.attendance') }}</button>
      </div>
    </div>

    <div v-if="parent.children.length" class="grid2" style="margin-top: 12px">
      <button
        v-for="link in quickLinks"
        :key="link.name"
        class="card"
        style="text-align: center; border: 1px solid var(--line); cursor: pointer; margin-bottom: 0"
        @click="router.push({ name: link.name })"
      >
        <div style="font-size: 1.5rem">{{ link.icon }}</div>
        <div style="font-weight: 600; font-size: 0.85rem">{{ t(link.label) }}</div>
      </button>
    </div>
  </div>
</template>
