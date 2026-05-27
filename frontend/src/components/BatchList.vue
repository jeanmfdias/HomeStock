<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import type { Batch, UnitType } from '@/api/types'
import ExpirationBadge from './ExpirationBadge.vue'

defineProps<{
  batches: Batch[]
  unitType: UnitType
}>()

const emit = defineEmits<{
  delete: [batchId: number]
}>()

const { t } = useI18n()
</script>

<template>
  <div class="batch-list">
    <p v-if="batches.length === 0" class="muted">{{ $t('products.batchEmpty') }}</p>
    <ul v-else>
      <li v-for="batch in batches" :key="batch.id" class="batch-row">
        <div class="batch-info">
          <span class="batch-qty">{{ batch.quantity }} {{ unitType }}</span>
          <ExpirationBadge :date="batch.expirationDate" />
        </div>
        <button
          class="icon-button"
          type="button"
          :aria-label="t('products.delete')"
          @click="emit('delete', batch.id)"
        >
          ×
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.batch-list ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--space-2);
}

.batch-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-2);
  border: 1px solid var(--c-border);
  border-radius: var(--radius);
  background: var(--c-surface, #fff);
}

.batch-info {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.batch-qty {
  font-weight: 600;
}

.icon-button {
  border: 1px solid var(--c-border-strong);
  background: transparent;
  border-radius: var(--radius);
  width: 32px;
  height: 32px;
  font-size: 1.1rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.icon-button:hover {
  background: var(--c-surface-muted, #f3f3f3);
}
</style>
