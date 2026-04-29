import { createI18n } from 'vue-i18n'

import en from './locales/en.json'
import ptBR from './locales/pt-BR.json'

export const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('homestock.locale') ?? 'pt-BR',
  fallbackLocale: 'en',
  messages: {
    'pt-BR': ptBR,
    en,
  },
})
