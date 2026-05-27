import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import { i18n } from '@/i18n'
import type { Batch, Category } from '@/api/types'
import BatchAddPanel from './BatchAddPanel.vue'

const requiringCategory: Category = {
  id: 1,
  name: 'Market',
  slug: 'market',
  requiresExpiration: true,
}

const optionalCategory: Category = {
  id: 2,
  name: 'Cleaning',
  slug: 'cleaning',
  requiresExpiration: false,
}

const sampleBatch: Batch = {
  id: 7,
  quantity: '1.500',
  expirationDate: '2026-05-10',
  createdAt: '2026-05-01T00:00:00+00:00',
  updatedAt: '2026-05-01T00:00:00+00:00',
}

function mountPanel(batches: Batch[] = [sampleBatch], category: Category = requiringCategory) {
  return mount(BatchAddPanel, {
    global: { plugins: [i18n] },
    props: {
      productId: 12,
      category,
      batches,
      unitType: 'l',
    },
  })
}

describe('BatchAddPanel', () => {
  it('emits batch-movement with positive delta and purchase reason for an existing batch', async () => {
    const wrapper = mountPanel()

    const qtyInput = wrapper.get('input[inputmode="decimal"]')
    await qtyInput.setValue('2')

    await wrapper.get('form').trigger('submit')

    const events = wrapper.emitted('batchMovement')
    expect(events).toBeTruthy()
    expect(events?.[0]).toEqual([12, 7, '2', 'purchase'])
  })

  it('emits add-batch when "+ New batch" is selected with valid date', async () => {
    const wrapper = mountPanel()

    const radios = wrapper.findAll('input[type="radio"]')
    await radios[radios.length - 1].setValue(true)

    await wrapper.get('input[type="date"]').setValue('2026-08-01')
    await wrapper.get('input[inputmode="decimal"]').setValue('3')
    await wrapper.get('form').trigger('submit')

    const events = wrapper.emitted('addBatch')
    expect(events).toBeTruthy()
    expect(events?.[0]).toEqual([12, { quantity: '3', expirationDate: '2026-08-01' }])
  })

  it('blocks submission when category requires expiration but date is missing', async () => {
    const wrapper = mountPanel([])

    await wrapper.get('input[inputmode="decimal"]').setValue('1')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('addBatch')).toBeFalsy()
    expect(wrapper.emitted('batchMovement')).toBeFalsy()
  })

  it('allows new batch without date when category does not require expiration', async () => {
    const wrapper = mountPanel([], optionalCategory)

    await wrapper.get('input[inputmode="decimal"]').setValue('5')
    await wrapper.get('form').trigger('submit')

    const events = wrapper.emitted('addBatch')
    expect(events).toBeTruthy()
    expect(events?.[0]).toEqual([12, { quantity: '5', expirationDate: null }])
  })
})
