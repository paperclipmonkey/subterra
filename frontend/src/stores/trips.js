// Utilities
import { defineStore } from 'pinia'
import { mande } from 'mande'
const tripsApi = mande('/api/trips')

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
        const response = await tripsApi.get({ query: filters })
        this.trips = response.data || response
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
  },
})
