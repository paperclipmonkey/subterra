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
    // Where the user had got to in the list, so opening a cave and coming back
    // doesn't dump them at the top of a list thousands of caves long. Only
    // restored when they return to the exact same URL (see CaveList).
    listState: {
      fullPath: null,
      displayCount: 0,
      scrollY: 0,
    },
  }),

  getters: {
    // True when the active filter only shows caves the user hasn't done yet, so
    // a cave just marked done no longer belongs in the list and should be removed.
    hidesDoneCaves: (state) => (state.savedFilter || []).includes('Not Done Yet'),

    // Identifies *what* is being listed. The `caves` array is reassigned on
    // every fetch and filter pass, so pagination keys off this instead —
    // otherwise simply revisiting the page would reset it to the first page.
    filterSignature: (state) => JSON.stringify([
      [...(state.savedFilter || [])].sort(),
      state.savedSearch || '',
      state.savedCatchmentId || '',
    ]),
  },

  actions: {
    /** Remember where the user was in the list before they navigate away. */
    rememberListState({ fullPath, displayCount, scrollY }) {
      this.listState = { fullPath, displayCount, scrollY }
    },

    /** The remembered position, but only for the URL it was captured on. */
    listStateFor(fullPath) {
      return this.listState.fullPath === fullPath ? this.listState : null
    },

    async getList() {
      // Keep whatever is already on screen while refreshing in the background.
      // Flipping to a spinner collapses the page height, which loses both the
      // user's scroll position and their place in the list.
      const hasCached = this.caves.length > 0
      try {
        this.loading = !hasCached
        this.isOfflineData = false
        this.allCavesLoaded = false
        // Fetch curated caves only — fast default payload
        this.allCaves = (await api.get('/api/caves?curated=1')).data.data
        this.loading = false

        // Assign `caves` exactly once, via the filter pass, so the list never
        // flashes an unfiltered intermediate state.
        this.applyFilters(this.savedFilter, this.savedSearch, this.savedCatchmentId)
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
     * Optimistically flag a cave as done in the in-memory lists, without a
     * refetch. A full refresh() re-downloads the entire cave list, flips the
     * loading spinner, and resets the infinite-scroll pagination — which throws
     * the user back to the top of a list that can be thousands of caves long.
     * Mutating the cave in place keeps their scroll position intact.
     */
    markDoneLocally(caveId) {
      for (const list of [this.caves, this.allCaves]) {
        const cave = list.find(c => c.id === caveId)
        if (cave) this.applyDoneState(cave)
      }
    },

    /**
     * Drop a cave from the currently displayed list after it's been marked done
     * — used when the active filter only shows not-yet-done caves. Splices the
     * cave out in place (rather than reassigning `caves`, which would reset the
     * infinite-scroll pagination), so the user's scroll position is preserved.
     * Also updates allCaves so the cave doesn't reappear if filters are
     * re-applied locally before the next server fetch.
     */
    removeCaveFromList(caveId) {
      const source = this.allCaves.find(c => c.id === caveId)
      if (source) this.applyDoneState(source)
      const index = this.caves.findIndex(c => c.id === caveId)
      if (index !== -1) this.caves.splice(index, 1)
    },

    // Mirror the server's done bookkeeping on a single cave: flag it done and
    // swap its computed "Not Done Yet" tag for "Previously Done".
    //
    // Idempotent: `caves` and `allCaves` share cave object references, so
    // markDoneLocally applies this twice to the same cave. Stripping any
    // existing "Previously Done" before re-adding it keeps the tag from
    // rendering twice (and makes a cave the server already flagged safe to
    // pass through).
    applyDoneState(cave) {
      cave.previously_done = true
      cave.tags = [
        ...(cave.tags || []).filter(t => t.tag !== 'Not Done Yet' && t.tag !== 'Previously Done'),
        { tag: 'Previously Done' },
      ]
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
        this.loading = this.caves.length === 0
        this.allCaves = (await api.get('/api/caves')).data.data
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
