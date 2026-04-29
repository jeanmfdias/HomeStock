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
        <p class="muted">{{ categories.map((item) => item.name).join(', ') }}</p>
      </form>

      <form class="card" @submit.prevent="addLocation">
        <h2>{{ $t('settings.locations') }}</h2>
        <label class="field">
          <span>{{ $t('settings.name') }}</span>
          <input v-model="locationName" required />
        </label>
        <button class="primary-button" type="submit">{{ $t('settings.add') }}</button>
        <p class="muted">{{ locations.map((item) => item.name).join(', ') }}</p>
      </form>

      <form class="card" @submit.prevent="addStore">
        <h2>{{ $t('settings.stores') }}</h2>
        <label class="field">
          <span>{{ $t('settings.name') }}</span>
          <input v-model="storeName" required />
        </label>
        <button class="primary-button" type="submit">{{ $t('settings.add') }}</button>
        <p class="muted">{{ stores.map((item) => item.name).join(', ') }}</p>
      </form>
    </div>
  </section>
</template>
