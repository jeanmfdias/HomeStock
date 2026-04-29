<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <section class="page">
    <header class="page-header">
      <h1>{{ $t('profile.title') }}</h1>
    </header>

    <div class="form-panel">
      <p>
        <strong>{{ auth.user?.name }}</strong>
      </p>
      <p class="muted">{{ auth.user?.email }}</p>
      <h2>{{ $t('profile.changePassword') }}</h2>
      <p class="muted">{{ $t('profile.passwordUnavailable') }}</p>
    </div>
  </section>
</template>
