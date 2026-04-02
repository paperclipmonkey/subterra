import { defineStore } from 'pinia'

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
        const response = await fetch('/api/tags')
        this.tags = await response.json()
        this.loaded = true
      } catch (error) {
        console.error('Failed to fetch tags:', error)
      } finally {
        this.loading = false
      }
    },
  },
})
