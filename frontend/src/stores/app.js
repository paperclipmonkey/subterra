// Utilities
import { defineStore } from 'pinia'
import { api } from '@/plugins/api'

export const useAppStore = defineStore('app', {
  state: () => ({
    user: {
      name: '',
      email: '',
      is_admin: false,
      clubs: [],
      roles: [],
    },
    loading: false,
    //
  }),

  getters: {
    canSuggest: (state) => {
      if (!state.user || !state.user.id) return false
      if (state.user.is_admin) return true
      return !!(state.user.clubs && state.user.clubs.some(c => c.status === 'approved'))
    }
  },

  actions: {
    async getUser() {
      try {
        this.loading = true
        const response = await api.get('/api/users/me', {
          suppressErrorNotification: true // Don't show error notification for unauthenticated users
        })
        this.user = response.data.data
        this.loading = false
        return this.user
        // showTooltip(`Welcome back ${this.userData.name}!`)
      } catch (error) {
        this.loading = false
        // Silently handle unauthenticated state - it's expected on public pages
        // Return empty user object
        return { name: '', email: '', is_admin: false, clubs: [] }
      }
    },
  },
})
