// Utilities
import { defineStore } from 'pinia'
import { api } from '@/plugins/api'

export const useCaveStore = defineStore('caves', {
  state: () => ({
    caves: [],
    loading: false,
    allCaves: [],
    savedFilter: [],
    savedSearch: '',
    savedCatchmentId: null,
  }),

  actions: {
    async getList() {
      try {
        this.loading = true
        this.caves = (await api.get('/api/caves')).data.data
        this.allCaves = this.caves
        this.loading = false

        // Apply saved filters after loading caves
        if (this.savedFilter.length > 0 || this.savedSearch || this.savedCatchmentId) {
          this.applyFilters(this.savedFilter, this.savedSearch, this.savedCatchmentId)
        }
      } catch (error) {
        this.loading = false
        return error
      }
    },
    applyFilters(tags, search, catchmentId = null) {
      // Save filters for future use
      this.savedFilter = tags
      this.savedSearch = search
      this.savedCatchmentId = catchmentId

      let filtered = this.allCaves

      // Apply tags filter if any tags are provided
      if (tags && tags.length > 0) {
        filtered = filtered.filter(cave => {
          return tags.every(tag =>
            cave.tags.some(caveTag => caveTag.tag === tag) ||
            cave.system.tags.some(caveTag => caveTag.tag === tag)
          )
        })
      }

      // Apply catchment filter if provided
      if (catchmentId) {
        filtered = filtered.filter(cave => {
          return cave.system && Number(cave.system.catchment_id) === Number(catchmentId)
        })
      }

      // Apply search filter if a search term is provided
      if (search) {
        const searchLower = search.toLowerCase()
        filtered = filtered.filter(cave => {
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
      this.caves = filtered
    }
  },
})
