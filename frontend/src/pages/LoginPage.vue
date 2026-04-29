<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import LocaleSwitcher from '@/components/LocaleSwitcher.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const email = ref('demo@homestock.local')
const password = ref('demopass123')
const error = ref<string | null>(null)

async function submit() {
  error.value = null
  try {
    await auth.login(email.value, password.value)
    await router.push((route.query.redirect as string) || '/products')
  } catch {
    error.value = auth.error
  }
}
</script>

<template>
  <div class="auth-wrap">
    <form class="auth-panel" @submit.prevent="submit">
      <LocaleSwitcher />
      <h1>{{ $t('auth.login') }}</h1>
      <p v-if="error" class="error">{{ $t(error) }}</p>
      <label class="field">
        <span>{{ $t('auth.email') }}</span>
        <input v-model="email" type="email" autocomplete="email" required />
      </label>
      <label class="field">
        <span>{{ $t('auth.password') }}</span>
        <input v-model="password" type="password" autocomplete="current-password" required />
      </label>
      <button class="primary-button" type="submit" :disabled="auth.loading">
        {{ $t('auth.login') }}
      </button>
      <RouterLink class="ghost-button" to="/register">{{ $t('auth.needAccount') }}</RouterLink>
    </form>
  </div>
</template>
