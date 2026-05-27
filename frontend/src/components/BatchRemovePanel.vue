<script setup lang="ts">
import { computed, ref, useId } from 'vue'

import type { Batch, MovementReason, UnitType } from '@/api/types'
import QuantityStepper from './QuantityStepper.vue'

const props = defineProps<{
  productId: number
  batches: Batch[]
  unitType: UnitType
}>()

const emit = defineEmits<{
  batchMovement: [productId: number, batchId: number, delta: string, reason: MovementReason]
  cancel: []
}>()

const groupId = useId()
const qtyInputId = useId()
const reasonId = useId()

const selectedId = ref<number | null>(props.batches[0]?.id ?? null)
const quantity = ref('1')
const reason = ref<MovementReason>('consume')

const selectedBatch = computed(() => props.batches.find((b) => b.id === selectedId.value) ?? null)

const overLimit = computed(() => {
  if (!selectedBatch.value) return false
  const qty = Number.parseFloat(quantity.value || '0')
  const max = Number.parseFloat(selectedBatch.value.quantity)
  return qty > max
})

const submitDisabled = computed(() => {
  const qty = Number.parseFloat(quantity.value || '0')
  return !selectedBatch.value || qty <= 0 || overLimit.value
})

function submit() {
  if (!selectedBatch.value) return
  const qty = quantity.value.trim()
  if (!qty || Number.parseFloat(qty) <= 0) return
  if (overLimit.value) return
  emit('batchMovement', props.productId, selectedBatch.value.id, `-${qty}`, reason.value)
}
</script>

<template>
  <form class="batch-panel" @submit.prevent="submit">
    <fieldset class="batch-options">
      <legend>{{ $t('products.pickBatch') }}</legend>
      <p v-if="batches.length === 0" class="muted">{{ $t('products.batchEmpty') }}</p>
      <label v-for="batch in batches" :key="batch.id" class="batch-option">
        <input v-model="selectedId" type="radio" :name="groupId" :value="batch.id" />
        <span>
          {{ batch.quantity }} {{ unitType }}
          ·
          <span v-if="batch.expirationDate">{{ batch.expirationDate }}</span>
          <span v-else class="muted">{{ $t('products.noDate') }}</span>
        </span>
      </label>
    </fieldset>

    <label class="field">
      <span :for="reasonId">{{ $t('products.reason') }}</span>
      <select :id="reasonId" v-model="reason">
        <option value="consume">{{ $t('movement.consume') }}</option>
        <option value="discard">{{ $t('movement.discard') }}</option>
        <option value="adjust">{{ $t('movement.adjust') }}</option>
      </select>
    </label>

    <div class="field">
      <label :for="qtyInputId" class="field-label">{{ $t('products.quantity') }}</label>
      <QuantityStepper v-model="quantity" :input-id="qtyInputId" :step="1" :min="0" />
      <p v-if="selectedBatch" class="field-hint">
        {{ $t('products.maxAvailable', { qty: selectedBatch.quantity, unit: unitType }) }}
      </p>
    </div>

    <div class="panel-actions">
      <button class="ghost-button" type="button" @click="emit('cancel')">
        {{ $t('common.cancel') }}
      </button>
      <button class="primary-button" type="submit" :disabled="submitDisabled">
        {{ $t('products.removeStock') }}
      </button>
    </div>
  </form>
</template>

<style scoped>
.batch-panel {
  display: grid;
  gap: var(--space-3);
  padding: var(--space-3);
  border: 1px solid var(--c-border);
  border-radius: var(--radius);
  background: var(--c-surface-muted, #fafafa);
}

.batch-options {
  border: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: var(--space-2);
}

.batch-options legend {
  font-weight: 700;
  font-size: var(--fs-sm);
  color: var(--c-text-subtle);
  margin-bottom: var(--space-2);
}

.batch-option {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2);
  border: 1px solid var(--c-border);
  border-radius: var(--radius);
  cursor: pointer;
  background: #fff;
}

.batch-option:has(input:checked) {
  border-color: var(--c-accent, #2c6e49);
  outline: 2px solid color-mix(in srgb, var(--c-accent, #2c6e49) 25%, transparent);
}

.field select {
  min-height: 40px;
  border: 1px solid var(--c-border-strong);
  border-radius: var(--radius);
  padding: var(--space-2);
  background: #fff;
}

.panel-actions {
  display: flex;
  gap: var(--space-2);
  justify-content: flex-end;
}

.field-hint {
  margin: 4px 0 0;
  color: var(--c-text-muted);
  font-size: 0.85rem;
}

.muted {
  color: var(--c-text-muted);
  font-size: 0.85rem;
}
</style>
