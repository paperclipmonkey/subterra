// Utilities
import { defineStore } from 'pinia'
import { mande } from 'mande'
const api = mande('/api/caves')

export const useCaveStore = defineStore('caves', {
  state: () => ({
    caves: [],
    loading: false,
    //
  }),

  actions: {
    async getList() {
      try {
        this.caves = (await api.get()).data
      } catch (error) {
        return error
      }
    },
  },
})
