<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineEmits<{ confirm: []; cancel: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="modal-backdrop" @click.self="$emit('cancel')">
    <div class="modal" role="dialog" aria-modal="true" :aria-label="t('push.preTitle')">
      <h2>🔔 {{ t('push.preTitle') }}</h2>
      <p>{{ t('push.preBody') }}</p>
      <button class="btn" @click="$emit('confirm')">{{ t('push.enable') }}</button>
      <button class="btn btn-ghost" style="margin-top: 8px" @click="$emit('cancel')">
        {{ t('install.later') }}
      </button>
    </div>
  </div>
</template>
