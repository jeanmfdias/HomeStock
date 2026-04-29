<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'

import { api } from '@/api/client'
import type { ReportRow } from '@/api/types'
import ExpirationBadge from '@/components/ExpirationBadge.vue'

const days = ref(7)
const items = ref<ReportRow[]>([])

async function load() {
  items.value = (await api.expiring(days.value)).items
}

onMounted(load)
watch(days, load)
</script>

<template>
  <section class="page">
    <header class="page-header">
      <h1>{{ $t('reports.expiringTitle') }}</h1>
      <label class="field">
        <span>{{ $t('reports.days') }}</span>
        <input v-model.number="days" type="number" min="1" max="365" />
      </label>
    </header>
    <p v-if="items.length === 0" class="muted">{{ $t('reports.emptyExpiring') }}</p>
    <div v-else class="table-panel">
      <table class="table">
        <thead>
          <tr>
            <th>{{ $t('products.title') }}</th>
            <th>{{ $t('products.category') }}</th>
            <th>{{ $t('products.quantity') }}</th>
            <th>{{ $t('products.expiration') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>{{ item.name }}</td>
            <td>{{ item.category }}</td>
            <td>{{ item.quantity }} {{ item.unitType }}</td>
            <td><ExpirationBadge :date="item.expirationDate" /></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
