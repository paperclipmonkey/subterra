// Utilities
import { defineStore } from 'pinia'
import { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'

export const useTripStore = defineStore('trips', {
  state: () => ({
    trips: [],
    loading: false,
    isOfflineError: false,
  }),

  actions: {
    async getTrips(filters = {}) {
      this.loading = true
      this.isOfflineError = false
      try {
        const response = await api.get('/api/trips', { params: filters })
        this.trips = response.data.data || response.data
      } catch (e) {
        console.error(e)
        if (!navigator.onLine || !e.response) {
          this.isOfflineError = true
          const notificationStore = useNotificationStore()
          notificationStore.showWarning('You are offline. Trips require an internet connection to load.')
        }
      } finally {
        this.loading = false
      }
    },
  },
})
