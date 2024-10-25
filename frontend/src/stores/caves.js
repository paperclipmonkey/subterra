// Utilities
import { defineStore } from 'pinia'
import { mande } from 'mande'
const api = mande('/api/caves')

export const useCaveStore = defineStore('caves', {
  state: () => ({
    caves: [],
    loading: false,
    allCaves: [],
    //
  }),

  actions: {
    async getList() {
      try {
        this.caves = (await api.get()).data
        this.allCaves = this.caves
      } catch (error) {
        return error
      }
    },
    applyFilter(filter) {
      // Filter the caves list by the tags
      if (filter.length === 0) {
        this.caves = this.allCaves
      } else {
        this.caves = this.allCaves.filter(cave => {
            return filter.every(tag => cave.tags.some(caveTag => caveTag.tag === tag))
        })
      }
    },
  },
})
