<script setup lang="ts">
import { computed, onMounted, ref, useId } from 'vue'
import { useRouter } from 'vue-router'

import { api } from '@/api/client'
import type { Category, ProductPayload, ReferenceItem } from '@/api/types'
import QuantityStepper from '@/components/QuantityStepper.vue'

const props = defineProps<{
  id?: number
}>()

const router = useRouter()
const categories = ref<Category[]>([])
const locations = ref<ReferenceItem[]>([])
const stores = ref<ReferenceItem[]>([])
const error = ref<string | null>(null)
const saving = ref(false)

const quantityId = useId()
const minStockId = useId()

const form = ref<ProductPayload>({
  name: '',
  brand: '',
  categoryId: 0,
  storageLocationId: null,
  preferredStoreId: null,
  unitType: 'unit',
  quantity: '0',
  minStock: '1',
  expirationDate: null,
  notes: '',
})

const selectedCategory = computed(() =>
  categories.value.find((category) => category.id === Number(form.value.categoryId)),
)

async function load() {
  const [categoryRows, locationRows, storeRows] = await Promise.all([
    api.categories(),
    api.storageLocations(),
    api.stores(),
  ])
  categories.value = categoryRows
  locations.value = locationRows
  stores.value = storeRows

  if (!form.value.categoryId && categories.value[0]) {
    form.value.categoryId = categories.value[0].id
  }

  if (props.id) {
    const product = await api.product(props.id)
    form.value = {
      name: product.name,
      brand: product.brand ?? '',
      categoryId: product.category.id,
      storageLocationId: product.storageLocation?.id ?? null,
      preferredStoreId: product.preferredStore?.id ?? null,
      unitType: product.unitType,
      quantity: product.quantity,
      minStock: product.minStock,
      expirationDate: product.expirationDate,
      notes: product.notes ?? '',
    }
  }
}

async function submit() {
  error.value = null
  saving.value = true
  try {
    if (props.id) {
      await api.updateProduct(props.id, form.value)
    } else {
      await api.createProduct(form.value)
    }
    await router.push('/products')
  } catch {
    error.value = 'errors.generic'
  } finally {
    saving.value = false
  }
}

function cancel() {
  router.push('/products')
}

onMounted(load)
</script>

<template>
  <section class="page">
    <form class="form-panel" @submit.prevent="submit">
      <div class="page-header">
        <h1>{{ $t(id ? 'products.edit' : 'products.new') }}</h1>
        <div class="form-actions">
          <button class="ghost-button" type="button" @click="cancel">
            {{ $t('common.cancel') }}
          </button>
          <button class="primary-button" type="submit" :disabled="saving">
            {{ $t('products.save') }}
          </button>
        </div>
      </div>

      <p v-if="error" class="error">{{ $t(error) }}</p>

      <div class="form-grid">
        <label class="field">
          <span>{{ $t('products.name') }}</span>
          <input v-model="form.name" required />
        </label>
        <label class="field">
          <span>{{ $t('products.brand') }}</span>
          <input v-model="form.brand" />
        </label>
        <label class="field">
          <span>{{ $t('products.category') }}</span>
          <select v-model.number="form.categoryId" required>
            <option v-for="category in categories" :key="category.id" :value="category.id">
              {{ category.name }}
            </option>
          </select>
        </label>
        <label class="field">
          <span>{{ $t('products.unit') }}</span>
          <select v-model="form.unitType">
            <option v-for="unit in ['unit', 'g', 'kg', 'ml', 'l']" :key="unit" :value="unit">
              {{ unit }}
            </option>
          </select>
        </label>
        <div class="field">
          <label :for="quantityId" class="field-label">{{ $t('products.quantity') }}</label>
          <QuantityStepper v-model="form.quantity" :input-id="quantityId" :step="1" />
        </div>
        <div class="field">
          <label :for="minStockId" class="field-label">{{ $t('products.minStock') }}</label>
          <QuantityStepper v-model="form.minStock" :input-id="minStockId" :step="1" />
        </div>
        <label class="field">
          <span>{{ $t('products.storage') }}</span>
          <select v-model.number="form.storageLocationId">
            <option :value="null">{{ $t('common.none') }}</option>
            <option v-for="location in locations" :key="location.id" :value="location.id">
              {{ location.name }}
            </option>
          </select>
        </label>
        <label class="field">
          <span>{{ $t('products.store') }}</span>
          <select v-model.number="form.preferredStoreId">
            <option :value="null">{{ $t('common.none') }}</option>
            <option v-for="store in stores" :key="store.id" :value="store.id">
              {{ store.name }}
            </option>
          </select>
        </label>
        <label v-if="selectedCategory?.requiresExpiration" class="field">
          <span>{{ $t('products.expiration') }}</span>
          <input v-model="form.expirationDate" type="date" required />
        </label>
        <label class="field full">
          <span>{{ $t('products.notes') }}</span>
          <textarea v-model="form.notes" rows="4"></textarea>
        </label>
      </div>
    </form>
  </section>
</template>
