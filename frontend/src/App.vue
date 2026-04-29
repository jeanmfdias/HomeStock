<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import LocaleSwitcher from '@/components/LocaleSwitcher.vue'

const auth = useAuthStore()
</script>

<template>
  <div class="app-shell">
    <aside v-if="auth.isAuthenticated" class="sidebar" aria-label="Main navigation">
      <RouterLink class="brand" to="/products">
        <span class="brand-mark">H</span>
        <span>
          <strong>{{ $t('app.name') }}</strong>
          <small>{{ $t('app.tagline') }}</small>
        </span>
      </RouterLink>

      <nav class="nav-links">
        <RouterLink to="/products">{{ $t('nav.products') }}</RouterLink>
        <RouterLink to="/shopping-list">{{ $t('nav.shopping') }}</RouterLink>
        <RouterLink to="/expiring">{{ $t('nav.expiring') }}</RouterLink>
        <RouterLink to="/settings">{{ $t('nav.settings') }}</RouterLink>
        <RouterLink to="/profile">{{ $t('nav.profile') }}</RouterLink>
      </nav>

      <div class="sidebar-footer">
        <LocaleSwitcher />
        <button class="ghost-button" type="button" @click="auth.logout">
          {{ $t('nav.logout') }}
        </button>
      </div>
    </aside>

    <main class="main-panel">
      <RouterView />
    </main>
  </div>
</template>
