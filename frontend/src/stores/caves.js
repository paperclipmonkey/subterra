// Utilities
import { defineStore } from 'pinia'
import { api } from '@/plugins/api'
import { useOfflineStore } from '@/stores/offline'

export const useCaveStore = defineStore('caves', {
  state: () => ({
    caves: [],
    loading: false,
    allCaves: [],
    allCavesLoaded: false,  // true once the full (non-curated) list has been fetched
    savedFilter: [],
    savedSearch: '',
    savedCatchmentId: null,
    isOfflineData: false,
  }),

  actions: {
    async getList() {
      try {
        this.loading = true
        this.isOfflineData = false
        this.allCavesLoaded = false
        // Fetch curated caves only — fast default payload
        this.caves = (await api.get('/api/caves?curated=1')).data.data
        this.allCaves = this.caves
        this.loading = false

        // Apply saved filters after loading caves
        if (this.savedFilter.length > 0 || this.savedSearch || this.savedCatchmentId) {
          this.applyFilters(this.savedFilter, this.savedSearch, this.savedCatchmentId)
        }
      } catch (error) {
        this.loading = false

        // Fallback to offline caves if we're offline
        if (!navigator.onLine || !error.response) {
          await this.loadOfflineCaves()
        }

        return error
      }
    },

    /**
     * Re-fetch whichever dataset is currently active (curated or full), preserving
     * applied filters. Use after a mutation that should reflect new data — e.g.
     * marking a cave as done — without forcing the user back to the curated list.
     */
    async refresh() {
      if (this.allCavesLoaded) {
        // Force a re-fetch of the full list, then re-apply the current filters
        this.allCavesLoaded = false
        await this.loadAllCaves(this.savedFilter, this.savedSearch, this.savedCatchmentId)
      } else {
        await this.getList()
      }
    },

    // Lazily fetch the full cave list (called when user removes the Curated filter)
    async loadAllCaves(tags, search, catchmentId = null) {
      if (this.allCavesLoaded) {
        this.applyFilters(tags ?? this.savedFilter, search ?? this.savedSearch, catchmentId ?? this.savedCatchmentId)
        return
      }
      try {
        this.loading = true
        this.caves = (await api.get('/api/caves')).data.data
        this.allCaves = this.caves
        this.allCavesLoaded = true
        this.loading = false
        this.applyFilters(tags ?? this.savedFilter, search ?? this.savedSearch, catchmentId ?? this.savedCatchmentId)
      } catch (error) {
        this.loading = false
        return error
      }
    },

    async loadOfflineCaves() {
      try {
        const offlineStore = useOfflineStore()
        const offlineCaves = await offlineStore.getAllOfflineCaves()
        if (offlineCaves.length > 0) {
          this.caves = offlineCaves
          this.allCaves = offlineCaves
          this.isOfflineData = true

          // Apply saved filters after loading
          if (this.savedFilter.length > 0 || this.savedSearch || this.savedCatchmentId) {
            this.applyFilters(this.savedFilter, this.savedSearch, this.savedCatchmentId)
          }
        }
      } catch {
        // IndexedDB not available
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
