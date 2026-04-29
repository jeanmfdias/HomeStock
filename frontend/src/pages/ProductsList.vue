<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { api } from '@/api/client'
import type { MovementReason, Product } from '@/api/types'
import ProductCard from '@/components/ProductCard.vue'

type Filter = 'all' | 'belowMin' | 'expiring'

const router = useRouter()
const products = ref<Product[]>([])
const activeFilter = ref<Filter>('all')
const loading = ref(false)
const error = ref<string | null>(null)

function setFilter(filter: Filter) {
  activeFilter.value = filter
  load()
}

async function load() {
  loading.value = true
  error.value = null
  const params = new URLSearchParams()
  if (activeFilter.value === 'belowMin') params.set('below_min_stock', '1')
  if (activeFilter.value === 'expiring') params.set('expiring_within_days', '7')

  try {
    products.value = await api.products(params)
  } catch {
    error.value = 'errors.generic'
  } finally {
    loading.value = false
  }
}

async function addMovement(id: number, delta: string, reason: MovementReason) {
  try {
    const updated = await api.addMovement(id, delta, reason)
    products.value = products.value.map((product) => (product.id === id ? updated : product))
  } catch {
    error.value = 'errors.generic'
  }
}

async function deleteProduct(id: number) {
  await api.deleteProduct(id)
  products.value = products.value.filter((product) => product.id !== id)
}

onMounted(load)
</script>

<template>
  <section class="page">
    <header class="page-header">
      <h1>{{ $t('products.title') }}</h1>
      <RouterLink class="primary-button" to="/products/new">{{ $t('products.new') }}</RouterLink>
    </header>

    <div class="filters">
      <button
        v-for="filter in ['all', 'belowMin', 'expiring'] as const"
        :key="filter"
        class="chip"
        :class="{ active: activeFilter === filter }"
        type="button"
        @click="setFilter(filter)"
      >
        {{ $t(`products.${filter}`) }}
      </button>
    </div>

    <p v-if="error" class="error">{{ $t(error) }}</p>
    <p v-if="loading" class="muted">...</p>
    <p v-else-if="products.length === 0" class="muted">{{ $t('products.empty') }}</p>

    <div v-else class="grid">
      <ProductCard
        v-for="product in products"
        :key="product.id"
        :product="product"
        @edit="router.push(`/products/${$event}/edit`)"
        @delete="deleteProduct"
        @movement="addMovement"
      />
    </div>
  </section>
</template>
