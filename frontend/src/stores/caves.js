// Utilities
import { defineStore } from 'pinia'
import { mande } from 'mande'
const api = mande('/api/caves')

export const useCaveStore = defineStore('caves', {
  state: () => ({
    caves: [],
    loading: false,
    allCaves: [],
    savedFilter: [],
    savedSearch: '',
  }),

  actions: {
    async getList() {
      try {
        this.loading = true
        this.caves = (await api.get()).data
        this.allCaves = this.caves
        this.loading = false

        // Apply saved filters after loading caves
        if (this.savedFilter.length > 0 || this.savedSearch) {
          this.applyFilters(this.savedFilter, this.savedSearch)
        }
      } catch (error) {
        this.loading = false
        return error
      }
    },
    applyFilters(tags, search) {
      // Save filters for future use
      this.savedFilter = tags;
      this.savedSearch = search;
      let filtered = this.allCaves;
      
      // Apply tags filter if any tags are provided
      if (tags && tags.length > 0) {
        filtered = filtered.filter(cave => {
          return tags.every(tag => 
            cave.tags.some(caveTag => caveTag.tag === tag) || 
            cave.system.tags.some(caveTag => caveTag.tag === tag)
          );
        });
      }
      
      // Apply search filter if a search term is provided
      if (search) {
        const searchLower = search.toLowerCase();
        filtered = filtered.filter(cave => {
          return Object.values(cave).some(value => {
            if (typeof value === 'string') {
              return value.toLowerCase().includes(searchLower);
            }
            if (typeof value === 'object' && value !== null) {
              return Object.values(value).some(nestedValue => 
                typeof nestedValue === 'string' && nestedValue.toLowerCase().includes(searchLower)
              );
            }
            return false;
          });
        });
      }
      this.caves = filtered;
    }
  },
})
