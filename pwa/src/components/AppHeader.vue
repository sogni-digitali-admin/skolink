<script setup lang="ts">
defineProps<{ title: string; sub?: string }>()
</script>

<template>
  <header class="page-header">
    <h1>{{ title }}</h1>
    <div v-if="sub" class="sub">{{ sub }}</div>
  </header>
</template>
