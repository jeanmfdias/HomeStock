<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { api } from '@/api/client'
import type { Category, ReferenceItem } from '@/api/types'

const categories = ref<Category[]>([])
const locations = ref<ReferenceItem[]>([])
const stores = ref<ReferenceItem[]>([])
const categoryName = ref('')
const categoryRequiresExpiration = ref(false)
const locationName = ref('')
const storeName = ref('')

async function load() {
  ;[categories.value, locations.value, stores.value] = await Promise.all([
    api.categories(),
    api.storageLocations(),
    api.stores(),
  ])
}

async function addCategory() {
  categories.value.push(
    await api.createCategory(categoryName.value, categoryRequiresExpiration.value),
  )
  categoryName.value = ''
  categoryRequiresExpiration.value = false
}

async function addLocation() {
  locations.value.push(await api.createStorageLocation(locationName.value))
  locationName.value = ''
}

async function addStore() {
  stores.value.push(await api.createStore(storeName.value))
  storeName.value = ''
}

onMounted(load)
</script>

<template>
  <section class="page">
    <header class="page-header">
      <h1>{{ $t('settings.title') }}</h1>
    </header>

    <div class="grid">
      <form class="card" @submit.prevent="addCategory">
        <h2>{{ $t('settings.categories') }}</h2>
        <label class="field">
          <span>{{ $t('settings.name') }}</span>
          <input v-model="categoryName" required />
        </label>
        <label class="toolbar">
          <input v-model="categoryRequiresExpiration" type="checkbox" />
          <span>{{ $t('settings.requiresExpiration') }}</span>
        </label>
        <button class="primary-button" type="submit">{{ $t('settings.add') }}</button>
        <ul v-if="categories.length" class="simple-list">
          <li v-for="item in categories" :key="item.id">{{ item.name }}</li>
        </ul>
        <p v-else class="muted simple-list--empty">{{ $t('settings.empty') }}</p>
      </form>

      <form class="card" @submit.prevent="addLocation">
        <h2>{{ $t('settings.locations') }}</h2>
        <label class="field">
          <span>{{ $t('settings.name') }}</span>
          <input v-model="locationName" required />
        </label>
        <button class="primary-button" type="submit">{{ $t('settings.add') }}</button>
        <ul v-if="locations.length" class="simple-list">
          <li v-for="item in locations" :key="item.id">{{ item.name }}</li>
        </ul>
        <p v-else class="muted simple-list--empty">{{ $t('settings.empty') }}</p>
      </form>

      <form class="card" @submit.prevent="addStore">
        <h2>{{ $t('settings.stores') }}</h2>
        <label class="field">
          <span>{{ $t('settings.name') }}</span>
          <input v-model="storeName" required />
        </label>
        <button class="primary-button" type="submit">{{ $t('settings.add') }}</button>
        <ul v-if="stores.length" class="simple-list">
          <li v-for="item in stores" :key="item.id">{{ item.name }}</li>
        </ul>
        <p v-else class="muted simple-list--empty">{{ $t('settings.empty') }}</p>
      </form>
    </div>
  </section>
</template>

<style scoped>
.card {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.card h2 {
  margin: 0;
  font-size: var(--fs-h2);
}

.card .field input {
  height: 42px;
}

.card .primary-button {
  width: 100%;
}
</style>
