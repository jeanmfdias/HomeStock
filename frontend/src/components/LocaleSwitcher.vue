<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()

function setLocale(nextLocale: 'pt-BR' | 'en') {
  locale.value = nextLocale
  localStorage.setItem('homestock.locale', nextLocale)
}
</script>

<template>
  <div class="locale-switch" aria-label="Language">
    <button
      class="chip"
      :class="{ active: locale === 'pt-BR' }"
      type="button"
      @click="setLocale('pt-BR')"
    >
      PT
    </button>
    <button
      class="chip"
      :class="{ active: locale === 'en' }"
      type="button"
      @click="setLocale('en')"
    >
      EN
    </button>
  </div>
</template>
