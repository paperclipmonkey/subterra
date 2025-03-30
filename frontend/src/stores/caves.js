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
        this.loading = true
        this.caves = (await api.get()).data
        this.allCaves = this.caves
        this.loading = false
      } catch (error) {
        this.loading = false
        return error
      }
    },
    applyFilter(filter) {
      // Filter the caves list by the tags
      if (filter.length === 0) {
        this.caves = this.allCaves
      } else {
        this.caves = this.allCaves.filter(cave => {
            return filter.every(tag => cave.tags.some(caveTag => caveTag.tag === tag) || cave.system.tags.some(caveTag => caveTag.tag === tag))
        })
      }
    },
    applySearch(search) {
      if (!search) {
        this.caves = this.allCaves
        return
      }

      const searchLower = search.toLowerCase()
      this.caves = this.allCaves.filter(cave => {
        return Object.values(cave).some(value => {
          if (typeof value === 'string') {
        return value.toLowerCase().includes(searchLower)
          }
          if (typeof value === 'object' && value !== null) {
        return Object.values(value).some(nestedValue => 
          typeof nestedValue === 'string' && nestedValue.toLowerCase().includes(searchLower)
        )
          }
          return false
        })
      })
    } 
  },
})
