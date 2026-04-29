import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { i18n } from '@/i18n'
import ExpirationBadge from './ExpirationBadge.vue'

describe('ExpirationBadge', () => {
  it('shows an urgent label for dates expiring today', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-04-28T10:00:00'))

    const wrapper = mount(ExpirationBadge, {
      global: {
        plugins: [i18n],
      },
      props: {
        date: '2026-04-28',
      },
    })

    expect(wrapper.text()).toContain('Vence hoje')
    expect(wrapper.classes()).toContain('danger')
    vi.useRealTimers()
  })

  it('renders a neutral label when no expiration date exists', () => {
    const wrapper = mount(ExpirationBadge, {
      global: {
        plugins: [i18n],
      },
      props: {
        date: null,
      },
    })

    expect(wrapper.text()).toContain('Sem validade')
  })
})
