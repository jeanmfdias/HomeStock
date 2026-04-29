import { createI18n } from 'vue-i18n'

import en from './locales/en.json'
import ptBR from './locales/pt-BR.json'

const initialLocale = localStorage.getItem('homestock.locale') ?? 'pt-BR'

if (typeof document !== 'undefined') {
  document.documentElement.lang = initialLocale
}

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale,
  fallbackLocale: 'en',
  messages: {
    'pt-BR': ptBR,
    en,
  },
})
