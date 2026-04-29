import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import { i18n } from '@/i18n'
import QuantityStepper from './QuantityStepper.vue'

describe('QuantityStepper', () => {
  it('increments and decrements the bound value', async () => {
    const wrapper = mount(QuantityStepper, {
      global: {
        plugins: [i18n],
      },
      props: {
        modelValue: '2',
        step: 0.5,
        'onUpdate:modelValue': (value: string) => {
          void wrapper.setProps({ modelValue: value })
        },
      },
    })

    await wrapper.get('button[aria-label="Aumentar"]').trigger('click')
    expect(wrapper.props('modelValue')).toBe('2.5')

    await wrapper.get('button[aria-label="Diminuir"]').trigger('click')
    expect(wrapper.props('modelValue')).toBe('2')
  })

  it('does not go below the minimum', async () => {
    const wrapper = mount(QuantityStepper, {
      global: {
        plugins: [i18n],
      },
      props: {
        modelValue: '0',
        min: 0,
        'onUpdate:modelValue': (value: string) => {
          void wrapper.setProps({ modelValue: value })
        },
      },
    })

    await wrapper.get('button[aria-label="Diminuir"]').trigger('click')
    expect(wrapper.props('modelValue')).toBe('0')
  })
})
