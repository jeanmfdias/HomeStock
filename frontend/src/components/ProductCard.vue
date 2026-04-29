<script setup lang="ts">
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

function submitMovement(event: Event, id: number) {
  const data = new FormData(event.currentTarget as HTMLFormElement)
  emit(
    'movement',
    id,
    String(data.get('delta') ?? ''),
    String(data.get('reason')) as MovementReason,
  )
  const form = event.currentTarget as HTMLFormElement
  form.reset()
}
</script>

<template>
  <article class="card product-card">
    <header class="product-card-header">
      <div>
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
      <input name="delta" :aria-label="$t('products.delta')" placeholder="-1" required />
      <select name="reason" :aria-label="$t('products.reason')">
        <option value="purchase">{{ $t('movement.purchase') }}</option>
        <option value="consume">{{ $t('movement.consume') }}</option>
        <option value="discard">{{ $t('movement.discard') }}</option>
        <option value="adjust">{{ $t('movement.adjust') }}</option>
      </select>
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
  gap: 12px;
}

h2,
p,
dl {
  margin: 0;
}

.product-meta {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

dt {
  color: #667068;
  font-size: 0.82rem;
  font-weight: 700;
}

dd {
  margin: 2px 0 0;
}

.movement-form {
  display: grid;
  grid-template-columns: 1fr 1.2fr auto;
  gap: 8px;
}

.movement-form input,
.movement-form select {
  min-width: 0;
  min-height: 40px;
  border: 1px solid #cfc6b8;
  border-radius: 8px;
  padding: 8px;
}
</style>
