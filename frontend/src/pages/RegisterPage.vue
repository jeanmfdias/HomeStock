<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import LocaleSwitcher from '@/components/LocaleSwitcher.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const name = ref('')
const email = ref('')
const password = ref('')
const error = ref<string | null>(null)

async function submit() {
  error.value = null
  try {
    await auth.register(name.value, email.value, password.value)
    await auth.login(email.value, password.value)
    await router.push('/products')
  } catch {
    error.value = auth.error
  }
}
</script>

<template>
  <div class="auth-wrap">
    <form class="auth-panel" @submit.prevent="submit">
      <LocaleSwitcher />
      <h1>{{ $t('auth.register') }}</h1>
      <p v-if="error" class="error">{{ $t(error) }}</p>
      <label class="field">
        <span>{{ $t('auth.name') }}</span>
        <input v-model="name" autocomplete="name" required />
      </label>
      <label class="field">
        <span>{{ $t('auth.email') }}</span>
        <input v-model="email" type="email" autocomplete="email" required />
      </label>
      <label class="field">
        <span>{{ $t('auth.password') }}</span>
        <input
          v-model="password"
          type="password"
          autocomplete="new-password"
          minlength="8"
          required
        />
      </label>
      <button class="primary-button" type="submit" :disabled="auth.loading">
        {{ $t('auth.register') }}
      </button>
      <RouterLink class="ghost-button" to="/login">{{ $t('auth.haveAccount') }}</RouterLink>
    </form>
  </div>
</template>
