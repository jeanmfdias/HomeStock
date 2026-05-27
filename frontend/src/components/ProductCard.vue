<script setup lang="ts">
import { ref } from 'vue'

import type { BatchPayload, MovementReason, Product } from '@/api/types'
import BatchAddPanel from './BatchAddPanel.vue'
import BatchList from './BatchList.vue'
import BatchRemovePanel from './BatchRemovePanel.vue'
import ExpirationBadge from './ExpirationBadge.vue'

defineProps<{
  product: Product
}>()

const emit = defineEmits<{
  edit: [id: number]
  delete: [id: number]
  addBatch: [productId: number, payload: BatchPayload]
  batchMovement: [productId: number, batchId: number, delta: string, reason: MovementReason]
  deleteBatch: [productId: number, batchId: number]
}>()

type PanelMode = null | 'add' | 'remove'
const panel = ref<PanelMode>(null)

function togglePanel(mode: 'add' | 'remove') {
  panel.value = panel.value === mode ? null : mode
}

function onAddBatch(productId: number, payload: BatchPayload) {
  emit('addBatch', productId, payload)
  panel.value = null
}

function onBatchMovement(
  productId: number,
  batchId: number,
  delta: string,
  reason: MovementReason,
) {
  emit('batchMovement', productId, batchId, delta, reason)
  panel.value = null
}
</script>

<template>
  <article class="card product-card">
    <header class="product-card-header">
      <div class="product-title">
        <h2>{{ product.name }}</h2>
        <p v-if="product.brand" class="muted">{{ product.brand }}</p>
      </div>
      <span class="badge" :class="{ warning: product.belowMinStock }">
        {{ product.belowMinStock ? $t('products.low') : $t('products.ok') }}
      </span>
    </header>

    <dl class="product-meta">
      <div>
        <dt>{{ $t('products.quantity') }}</dt>
        <dd>{{ product.quantity }} {{ product.unitType }}</dd>
      </div>
      <div>
        <dt>{{ $t('products.minStock') }}</dt>
        <dd>{{ product.minStock }} {{ product.unitType }}</dd>
      </div>
      <div>
        <dt>{{ $t('products.category') }}</dt>
        <dd>{{ product.category.name }}</dd>
      </div>
      <div v-if="product.storageLocation">
        <dt>{{ $t('products.storage') }}</dt>
        <dd>{{ product.storageLocation.name }}</dd>
      </div>
    </dl>

    <div v-if="product.batches.length > 0" class="next-expiration">
      <span class="next-expiration-label">{{ $t('products.nextExpiration') }}</span>
      <ExpirationBadge :date="product.nextExpiration" />
    </div>

    <section class="batches-section">
      <h3 class="section-title">{{ $t('products.batches') }}</h3>
      <BatchList
        :batches="product.batches"
        :unit-type="product.unitType"
        @delete="(batchId) => emit('deleteBatch', product.id, batchId)"
      />
    </section>

    <div class="stock-actions">
      <button
        class="primary-button"
        type="button"
        :aria-pressed="panel === 'add'"
        @click="togglePanel('add')"
      >
        {{ $t('products.addStock') }}
      </button>
      <button
        class="ghost-button"
        type="button"
        :disabled="product.batches.length === 0"
        :aria-pressed="panel === 'remove'"
        @click="togglePanel('remove')"
      >
        {{ $t('products.removeStock') }}
      </button>
    </div>

    <BatchAddPanel
      v-if="panel === 'add'"
      :product-id="product.id"
      :category="product.category"
      :batches="product.batches"
      :unit-type="product.unitType"
      @add-batch="onAddBatch"
      @batch-movement="onBatchMovement"
      @cancel="panel = null"
    />

    <BatchRemovePanel
      v-if="panel === 'remove'"
      :product-id="product.id"
      :batches="product.batches"
      :unit-type="product.unitType"
      @batch-movement="onBatchMovement"
      @cancel="panel = null"
    />

    <footer class="card-actions">
      <button class="ghost-button" type="button" @click="emit('edit', product.id)">
        {{ $t('products.edit') }}
      </button>
      <button class="danger-button" type="button" @click="emit('delete', product.id)">
        {{ $t('products.delete') }}
      </button>
    </footer>
  </article>
</template>

<style scoped>
.product-card {
  display: grid;
  gap: 14px;
}

.product-card-header,
.card-actions {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
}

.product-title {
  min-width: 0;
}

h2 {
  margin: 0;
  font-size: var(--fs-h2);
  overflow-wrap: anywhere;
}

p,
dl {
  margin: 0;
}

.product-meta {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

dt {
  color: var(--c-text-muted);
  font-size: 0.82rem;
  font-weight: 700;
}

dd {
  margin: 2px 0 0;
  overflow-wrap: anywhere;
}

.next-expiration {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.next-expiration-label {
  color: var(--c-text-muted);
  font-size: 0.82rem;
  font-weight: 700;
}

.batches-section {
  display: grid;
  gap: var(--space-2);
}

.section-title {
  margin: 0;
  font-size: 0.95rem;
  color: var(--c-text-subtle);
}

.stock-actions {
  display: flex;
  gap: var(--space-2);
}

.stock-actions .primary-button,
.stock-actions .ghost-button {
  flex: 1;
}

.card-actions .ghost-button,
.card-actions .danger-button {
  flex: 1;
}
</style>
