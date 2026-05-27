<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

import { ApiError, api } from '@/api/client'
import type { BatchPayload, MovementReason, Product } from '@/api/types'
import ProductCard from '@/components/ProductCard.vue'

type Filter = 'all' | 'belowMin' | 'expiring'

const router = useRouter()
const { t } = useI18n()
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

function replaceProduct(updated: Product) {
  products.value = products.value.map((product) => (product.id === updated.id ? updated : product))
}

async function addBatch(productId: number, payload: BatchPayload) {
  try {
    const updated = await api.createBatch(productId, payload)
    replaceProduct(updated)
  } catch (e) {
    if (e instanceof ApiError && typeof e.body === 'object' && e.body !== null) {
      const code = (e.body as { error?: string }).error
      if (code === 'expiration_required_for_category') {
        error.value = 'products.expirationRequired'
        return
      }
    }
    error.value = 'errors.generic'
  }
}

async function batchMovement(
  productId: number,
  batchId: number,
  delta: string,
  reason: MovementReason,
) {
  try {
    const updated = await api.batchMovement(productId, batchId, delta, reason)
    replaceProduct(updated)
  } catch {
    error.value = 'errors.generic'
  }
}

async function deleteBatch(productId: number, batchId: number) {
  try {
    await api.deleteBatch(productId, batchId)
    const refreshed = await api.product(productId)
    replaceProduct(refreshed)
  } catch (e) {
    if (e instanceof ApiError && e.status === 409) {
      error.value = 'products.cannotDeleteBatchWithHistory'
      return
    }
    error.value = 'errors.generic'
  }
}

async function deleteProduct(id: number) {
  const product = products.value.find((p) => p.id === id)
  if (!product) return
  if (!globalThis.confirm(t('common.confirmDelete', { name: product.name }))) return

  try {
    await api.deleteProduct(id)
    products.value = products.value.filter((p) => p.id !== id)
  } catch {
    error.value = 'errors.generic'
  }
}

onMounted(load)
</script>

<template>
  <section class="page">
    <header class="page-header">
      <h1>{{ $t('products.title') }}</h1>
      <RouterLink class="primary-button" to="/products/new">{{ $t('products.new') }}</RouterLink>
    </header>

    <fieldset class="filters">
      <legend class="sr-only">{{ $t('products.title') }}</legend>
      <button
        v-for="filter in ['all', 'belowMin', 'expiring'] as const"
        :key="filter"
        class="chip"
        :class="{ active: activeFilter === filter }"
        type="button"
        :aria-pressed="activeFilter === filter"
        @click="setFilter(filter)"
      >
        {{ $t(`products.${filter}`) }}
      </button>
    </fieldset>

    <p v-if="error" class="error" role="alert">{{ $t(error) }}</p>

    <div v-if="loading" class="grid" aria-busy="true" :aria-label="$t('common.loading')">
      <div v-for="n in 6" :key="n" class="card skeleton" aria-hidden="true"></div>
    </div>
    <p v-else-if="products.length === 0" class="muted">{{ $t('products.empty') }}</p>
    <div v-else class="grid">
      <ProductCard
        v-for="product in products"
        :key="product.id"
        :product="product"
        @edit="router.push(`/products/${$event}/edit`)"
        @delete="deleteProduct"
        @add-batch="addBatch"
        @batch-movement="batchMovement"
        @delete-batch="deleteBatch"
      />
    </div>
  </section>
</template>
