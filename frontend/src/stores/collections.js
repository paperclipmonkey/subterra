
import { defineStore } from 'pinia'
import { mande } from 'mande'

const api = mande('/api/collections')

export const useCollectionStore = defineStore('collections', {
    state: () => ({
        collections: [],
        currentCollection: null,
        loading: false,
        error: null,
    }),
    actions: {
        async fetchCollections() {
            this.loading = true
            this.error = null
            try {
                this.collections = await api.get()
            } catch (err) {
                this.error = err.message
            } finally {
                this.loading = false
            }
        },
        async fetchCollection(id) {
            this.loading = true
            this.error = null
            this.currentCollection = null // Reset to avoid showing stale data
            try {
                this.currentCollection = await api.get(id)
            } catch (err) {
                this.error = err.message
            } finally {
                this.loading = false
            }
        },
        async addCaveToCollection(collectionId, caveId) {
            try {
                await api.post(`${collectionId}/caves`, { cave_id: caveId });
                await this.fetchCollection(collectionId); // Refresh
            } catch (err) {
                this.error = err.message;
                throw err;
            }
        },
        async removeCaveFromCollection(collectionId, caveId) {
            try {
                await api.delete(`${collectionId}/caves/${caveId}`);
                await this.fetchCollection(collectionId); // Refresh
            } catch (err) {
                this.error = err.message;
                throw err;
            }
        },
        async updateCollection(collection) {
            try {
                // Use slug for the URL identifier if available, technically backend expects the route key
                // Since we changed getRouteKeyName to slug, we should use slug.
                const identifier = collection.slug || collection.id;
                await api.put(identifier, collection);
                // Fetch using slug as well to be consistent
                await this.fetchCollection(identifier);
            } catch (err) {
                this.error = err.message;
                throw err;
            }
        },
        async createCollection(collection) {
            try {
                return await api.post(collection);
            } catch (err) {
                this.error = err.message;
                throw err;
            }
        }
    },
})
