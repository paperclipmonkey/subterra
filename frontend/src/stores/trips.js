// Utilities
import { defineStore } from 'pinia'
import { api } from '@/plugins/api'

export const useTripStore = defineStore('trips', {
  state: () => ({
    trips: [],
    loading: false,
    //
  }),

  actions: {
    async getTrips(filters = {}) {
      this.loading = true
      try {
        const response = await api.get('/api/trips', { params: filters })
        this.trips = response.data.data || response.data
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
  },
})
