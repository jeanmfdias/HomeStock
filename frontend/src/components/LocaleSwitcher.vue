<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { locale, t } = useI18n()

function setLocale(nextLocale: 'pt-BR' | 'en') {
  locale.value = nextLocale
  localStorage.setItem('homestock.locale', nextLocale)
  document.documentElement.lang = nextLocale
}
</script>

<template>
  <fieldset class="locale-switch">
    <legend class="sr-only">{{ t('common.language') }}</legend>
    <button
      class="chip"
      :class="{ active: locale === 'pt-BR' }"
      type="button"
      :aria-pressed="locale === 'pt-BR'"
      @click="setLocale('pt-BR')"
    >
      PT
    </button>
    <button
      class="chip"
      :class="{ active: locale === 'en' }"
      type="button"
      :aria-pressed="locale === 'en'"
      @click="setLocale('en')"
    >
      EN
    </button>
  </fieldset>
</template>

<style scoped>
.locale-switch {
  border: 0;
  margin: 0;
  padding: 0;
}
</style>
