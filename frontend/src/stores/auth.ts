import { defineStore } from 'pinia'

import { api, ApiError } from '@/api/client'
import type { User } from '@/api/types'

interface AuthState {
  user: User | null
  loading: boolean
  error: string | null
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    loading: false,
    error: null,
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
  },
  actions: {
    async loadSession() {
      this.loading = true
      this.error = null
      try {
        this.user = await api.me()
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          this.user = null
          return
        }
        this.error = 'errors.session'
      } finally {
        this.loading = false
      }
    },
    async login(email: string, password: string) {
      this.loading = true
      this.error = null
      try {
        this.user = await api.login(email, password)
      } catch {
        this.error = 'errors.login'
        throw new Error(this.error)
      } finally {
        this.loading = false
      }
    },
    async register(name: string, email: string, password: string) {
      this.loading = true
      this.error = null
      try {
        this.user = await api.register(name, email, password)
      } catch {
        this.error = 'errors.register'
        throw new Error(this.error)
      } finally {
        this.loading = false
      }
    },
    async logout() {
      try {
        await api.logout()
      } finally {
        this.user = null
      }
    },
  },
})
