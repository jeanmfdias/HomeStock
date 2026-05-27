<script setup lang="ts">
import { computed, ref, useId } from 'vue'

import type { Batch, BatchPayload, Category, UnitType } from '@/api/types'
import QuantityStepper from './QuantityStepper.vue'

const props = defineProps<{
  productId: number
  category: Category
  batches: Batch[]
  unitType: UnitType
}>()

const emit = defineEmits<{
  addBatch: [productId: number, payload: BatchPayload]
  batchMovement: [productId: number, batchId: number, delta: string, reason: 'purchase']
  cancel: []
}>()

const NEW_BATCH = 'new'
const groupId = useId()
const qtyInputId = useId()
const dateInputId = useId()

const selected = ref<number | typeof NEW_BATCH>(props.batches[0]?.id ?? NEW_BATCH)
const quantity = ref('1')
const expirationDate = ref<string | null>(null)

const isNewBatch = computed(() => selected.value === NEW_BATCH)
const dateRequired = computed(() => isNewBatch.value && props.category.requiresExpiration)

function submit() {
  const qty = quantity.value.trim()
  if (!qty || Number.parseFloat(qty) <= 0) return

  if (isNewBatch.value) {
    if (dateRequired.value && !expirationDate.value) return
    emit('addBatch', props.productId, {
      quantity: qty,
      expirationDate: expirationDate.value || null,
    })
  } else {
    emit('batchMovement', props.productId, selected.value as number, qty, 'purchase')
  }
}
</script>

<template>
  <form class="batch-panel" @submit.prevent="submit">
    <fieldset class="batch-options">
      <legend>{{ $t('products.pickBatch') }}</legend>
      <label v-for="batch in batches" :key="batch.id" class="batch-option">
        <input v-model="selected" type="radio" :name="groupId" :value="batch.id" />
        <span>
          {{ batch.quantity }} {{ unitType }}
          ·
          <span v-if="batch.expirationDate">{{ batch.expirationDate }}</span>
          <span v-else class="muted">{{ $t('products.noDate') }}</span>
        </span>
      </label>
      <label class="batch-option">
        <input v-model="selected" type="radio" :name="groupId" :value="NEW_BATCH" />
        <span>+ {{ $t('products.newBatch') }}</span>
      </label>
    </fieldset>

    <div class="field">
      <label :for="qtyInputId" class="field-label">{{ $t('products.quantity') }}</label>
      <QuantityStepper v-model="quantity" :input-id="qtyInputId" :step="1" :min="0" />
    </div>

    <label v-if="isNewBatch" class="field">
      <span>
        {{ $t('products.expiration') }}
        <span v-if="!dateRequired" class="muted">({{ $t('products.batchOptional') }})</span>
      </span>
      <input :id="dateInputId" v-model="expirationDate" type="date" :required="dateRequired" />
    </label>

    <div class="panel-actions">
      <button class="ghost-button" type="button" @click="emit('cancel')">
        {{ $t('common.cancel') }}
      </button>
      <button class="primary-button" type="submit">
        {{ $t('products.addStock') }}
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

.field input[type='date'] {
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

.muted {
  color: var(--c-text-muted);
  font-size: 0.85rem;
}
</style>
