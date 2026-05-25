import { defineStore } from 'pinia'
import { api } from '@/plugins/api'

export const useTagStore = defineStore('tags', {
  state: () => ({
    tags: {},
    loading: false,
    loaded: false,
  }),

  actions: {
    async fetchTags() {
      if (this.loaded || this.loading) return
      this.loading = true
      try {
        const response = await api.get('/api/tags')
        this.tags = response.data
        this.loaded = true
      } catch (error) {
        console.error('Failed to fetch tags:', error)
      } finally {
        this.loading = false
      }
    },
  },
})
