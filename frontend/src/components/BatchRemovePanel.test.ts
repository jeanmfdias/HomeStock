import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import { i18n } from '@/i18n'
import type { Batch } from '@/api/types'
import BatchRemovePanel from './BatchRemovePanel.vue'

const batchA: Batch = {
  id: 7,
  quantity: '2.000',
  expirationDate: '2026-05-10',
  createdAt: '2026-05-01T00:00:00+00:00',
  updatedAt: '2026-05-01T00:00:00+00:00',
}

const batchB: Batch = {
  id: 8,
  quantity: '1.000',
  expirationDate: '2026-06-01',
  createdAt: '2026-05-01T00:00:00+00:00',
  updatedAt: '2026-05-01T00:00:00+00:00',
}

function mountPanel() {
  return mount(BatchRemovePanel, {
    global: { plugins: [i18n] },
    props: {
      productId: 12,
      batches: [batchA, batchB],
      unitType: 'l',
    },
  })
}

describe('BatchRemovePanel', () => {
  it('emits batch-movement with negative delta and chosen reason', async () => {
    const wrapper = mountPanel()

    const reason = wrapper.get('select')
    await reason.setValue('discard')

    await wrapper.get('input[inputmode="decimal"]').setValue('1')
    await wrapper.get('form').trigger('submit')

    const events = wrapper.emitted('batchMovement')
    expect(events).toBeTruthy()
    expect(events?.[0]).toEqual([12, 7, '-1', 'discard'])
  })

  it('disables submission when quantity exceeds the selected batch', async () => {
    const wrapper = mountPanel()

    await wrapper.get('input[inputmode="decimal"]').setValue('5')

    const submitBtn = wrapper.get('button[type="submit"]')
    expect(submitBtn.attributes('disabled')).toBeDefined()

    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('batchMovement')).toBeFalsy()
  })
})
