<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { api } from '@/api/client'
import type { ReportRow } from '@/api/types'

const items = ref<ReportRow[]>([])

onMounted(async () => {
  items.value = (await api.shoppingList()).items
})
</script>

<template>
  <section class="page">
    <header class="page-header">
      <h1>{{ $t('reports.shoppingTitle') }}</h1>
    </header>
    <p v-if="items.length === 0" class="muted">{{ $t('reports.emptyShopping') }}</p>
    <div v-else class="table-panel">
      <table class="table">
        <thead>
          <tr>
            <th>{{ $t('products.title') }}</th>
            <th>{{ $t('products.quantity') }}</th>
            <th>{{ $t('products.minStock') }}</th>
            <th>{{ $t('products.store') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>{{ item.name }}</td>
            <td>{{ item.quantity }} {{ item.unitType }}</td>
            <td>{{ item.minStock }} {{ item.unitType }}</td>
            <td>{{ item.preferredStore ?? '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
