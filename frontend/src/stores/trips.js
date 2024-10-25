// Utilities
import { defineStore } from 'pinia'
import { mande } from 'mande'
const api = mande('/api/trips')

export const useTripStore = defineStore('trips', {
  state: () => ({
    trips: [],
    loading: false,
    //
  }),

  actions: {
    async getTrips() {
      this.loading = true
      this.trips = (await api.get()).data
      this.loading = false
    },
  },
})
