// Utilities
import { defineStore } from 'pinia'
import { mande } from 'mande'
import { useRouter } from 'vue-router';
const router = useRouter();
const api = mande('/api/trips')

export const useTripStore = defineStore('trips', {
  state: () => ({
    trips: [],
    loading: false,
    //
  }),

  actions: {
    async getTrips(login, password) {
      try {
        this.trips = (await api.get()).data
      } catch (error) {
        router.push({ name: '/' });
        // showTooltip(error)
        // let the form component display the error
        return error
      }
    },
  },
})
