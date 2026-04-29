import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'

import { useAuthStore } from './auth'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('loads the current session', async () => {
    const store = useAuthStore()
    await store.loadSession()

    expect(store.user?.email).toBe('demo@homestock.local')
    expect(store.isAuthenticated).toBe(true)
  })

  it('logs in and stores the returned user', async () => {
    const store = useAuthStore()
    await store.login('demo@homestock.local', 'demopass123')

    expect(store.user?.name).toBe('Demo')
  })
})
