
import { defineStore } from 'pinia'
import { mande } from 'mande'
import { toFormData } from '@/utilities'

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
                const response = await api.get()
                this.collections = response.data || response // Fallback in case wrapped or not
            } catch (err) {
                this.error = err.message
            } finally {
                this.loading = false
            }
        },
        async fetchCollection(id) {
            this.loading = true
            this.error = null
            this.currentCollection = null
            try {
                const response = await api.get(id)
                this.currentCollection = response.data || response
            } catch (err) {
                if (err.response && err.response.status === 404) {
                    this.error = "Collection not found. It may have been deleted or you may have the wrong link."
                } else {
                    this.error = "Failed to load collection. Please try again later."
                }
            } finally {
                this.loading = false
            }
        },
        async addCaveToCollection(collectionId, caveId) {
            // Deprecated in UI but kept for compatibility or manual calls
            try {
                await api.post(`${collectionId}/caves`, { cave_id: caveId })
                await this.fetchCollection(collectionId) // Refresh
            } catch (err) {
                this.error = err.message
                throw err
            }
        },
        async removeCaveFromCollection(collectionId, caveId) {
            // Deprecated in UI but kept for compatibility
            try {
                await api.delete(`${collectionId}/caves/${caveId}`)
                await this.fetchCollection(collectionId) // Refresh
            } catch (err) {
                this.error = err.message
                throw err
            }
        },
        async updateCollection(collection) {
            try {
                const identifier = collection.slug || collection.id

                // If photo is present (File) or we have complex nested data for caves, use FormData
                // To allow file upload in PUT, we must use POST with _method=PUT
                const isFormData = (collection.photo instanceof File) ||
                    (collection.caves && collection.caves.length > 0)

                if (isFormData) {
                    const formData = toFormData(collection)
                    formData.append('_method', 'PUT')
                    // mande handles FormData but likely keeps JSON header, ensuring it's cleared
                    await api.post(identifier, formData, { headers: { 'Content-Type': null } })
                } else {
                    await api.put(identifier, collection)
                }

                // Fetch using slug as well to be consistent
                await this.fetchCollection(identifier)
            } catch (err) {
                this.error = err.message
                throw err
            }
        },
        async createCollection(collection) {
            try {
                const isFormData = (collection.photo instanceof File) ||
                    (collection.caves && collection.caves.length > 0)

                if (isFormData) {
                    const formData = toFormData(collection)
                    return await api.post('/', formData, { headers: { 'Content-Type': null } })
                } else {
                    return await api.post(collection)
                }
            } catch (err) {
                this.error = err.message
                throw err
            }
        }
    },
})
