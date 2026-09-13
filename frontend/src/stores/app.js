
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
      onboarding_completed_at: null,
      features: {},
    },
    userFetched: false,
    loading: false,
  }),

  getters: {
    canSuggest: (state) => {
      if (!state.user || !state.user.id) return false
      if (state.user.is_admin) return true
      return !!(state.user.clubs && state.user.clubs.some(c => c.status === 'approved'))
    },

    // The server's master switch for callouts. Defaults to enabled so a cached
    // user record from before this field existed doesn't hide a live feature.
    calloutsEnabled: (state) => state.user?.features?.callouts !== false,
  },

  actions: {
    async getUser(forceRefresh = false) {
      if (this.userFetched && !forceRefresh) {
        return this.user
      }
      try {
        this.loading = true
        const response = await api.get('/api/users/me', {
          suppressErrorNotification: true // Don't show error notification for unauthenticated users
        })
        this.user = response.data.data
        this.userFetched = true
        this.loading = false

        // Cache user data for offline use
        try {
          localStorage.setItem('subterra:cached-user', JSON.stringify(this.user))
        } catch {
          // localStorage may be full
        }

        return this.user
      } catch (error) {
        this.loading = false

        // When offline, try to use cached user data instead of treating as unauthenticated
        if (!navigator.onLine || !error.response) {
          const cached = localStorage.getItem('subterra:cached-user')
          if (cached) {
            try {
              this.user = JSON.parse(cached)
              this.userFetched = true
              return this.user
            } catch {
              // Corrupted cache, fall through
            }
          }
        }

        this.userFetched = true // Set to true even on failure so we don't spam the API with unathenticated requests
        // Silently handle unauthenticated state - it's expected on public pages
        // Return empty user object
        return { name: '', email: '', is_admin: false, clubs: [] }
      }
    },
    async logout() {
      try {
        this.loading = true
        await api.post('/api/logout')
        this.user = {
          name: '',
          email: '',
          is_admin: false,
          clubs: [],
          roles: [],
          onboarding_completed_at: null,
        }
        this.userFetched = false
        this.loading = false
        window.location.href = '/'
      } catch (error) {
        this.loading = false
        console.error('Logout failed:', error)
        // Even if the API call fails, we should probably clear the local state
        // or at least redirect to home to force a fresh session check
        window.location.href = '/'
      }
    },
  },
})
