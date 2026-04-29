<script setup lang="ts">
import { useId } from 'vue'

import type { MovementReason, Product } from '@/api/types'
import ExpirationBadge from './ExpirationBadge.vue'

defineProps<{
  product: Product
}>()

const emit = defineEmits<{
  edit: [id: number]
  delete: [id: number]
  movement: [id: number, delta: string, reason: MovementReason]
}>()

const deltaId = useId()
const reasonId = useId()
const hintId = useId()

function submitMovement(event: Event, id: number) {
  const form = event.currentTarget as HTMLFormElement
  const data = new FormData(form)
  emit(
    'movement',
    id,
    String(data.get('delta') ?? ''),
    String(data.get('reason')) as MovementReason,
  )
  form.reset()
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

    <ExpirationBadge :date="product.expirationDate" />

    <form class="movement-form" @submit.prevent="submitMovement($event, product.id)">
      <div class="movement-fields">
        <div class="field">
          <label :for="deltaId">{{ $t('products.delta') }}</label>
          <input
            :id="deltaId"
            name="delta"
            inputmode="decimal"
            :placeholder="$t('products.deltaPlaceholder')"
            :aria-describedby="hintId"
            required
          />
        </div>
        <div class="field">
          <label :for="reasonId">{{ $t('products.reason') }}</label>
          <select :id="reasonId" name="reason">
            <option value="purchase">{{ $t('movement.purchase') }}</option>
            <option value="consume">{{ $t('movement.consume') }}</option>
            <option value="discard">{{ $t('movement.discard') }}</option>
            <option value="adjust">{{ $t('movement.adjust') }}</option>
          </select>
        </div>
      </div>
      <p :id="hintId" class="field-hint">{{ $t('products.deltaHelp') }}</p>
      <button class="primary-button" type="submit">{{ $t('products.apply') }}</button>
    </form>

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

.movement-form {
  display: grid;
  gap: var(--space-2);
}

.movement-fields {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
  gap: var(--space-2);
}

.movement-form input,
.movement-form select {
  min-width: 0;
  min-height: 40px;
  border: 1px solid var(--c-border-strong);
  border-radius: var(--radius);
  padding: var(--space-2);
  background: #fff;
}

.movement-form .field > label {
  color: var(--c-text-subtle);
  font-size: var(--fs-sm);
  font-weight: 700;
}

.card-actions .ghost-button,
.card-actions .danger-button {
  flex: 1;
}
</style>
