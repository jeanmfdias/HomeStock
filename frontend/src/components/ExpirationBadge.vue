<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  date: string | null
}>()

const { t } = useI18n()

const state = computed(() => {
  if (!props.date) {
    return { label: t('products.noDate'), className: '' }
  }

  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const expiration = new Date(`${props.date}T00:00:00`)
  const diffDays = Math.ceil((expiration.getTime() - today.getTime()) / 86_400_000)

  if (diffDays < 0) {
    return { label: t('products.expired'), className: 'danger' }
  }
  if (diffDays === 0) {
    return { label: t('products.today'), className: 'danger' }
  }
  if (diffDays <= 7) {
    return {
      label: t('products.soon', diffDays, { named: { days: diffDays } }),
      className: 'warning',
    }
  }

  return { label: props.date, className: '' }
})
</script>

<template>
  <span class="badge" :class="state.className">{{ state.label }}</span>
</template>
