
import { defineStore } from 'pinia'
import { mande } from 'mande'

const api = mande('/api/collections')

function toFormData(obj) {
    const formData = new FormData();
    for (const key in obj) {
        if (obj[key] === null || obj[key] === undefined) continue;

        if (Array.isArray(obj[key])) {
            obj[key].forEach((item, index) => {
                if (typeof item === 'object' && !(item instanceof File)) {
                    for (const subKey in item) {
                        formData.append(`${key}[${index}][${subKey}]`, item[subKey] !== null && item[subKey] !== undefined ? item[subKey] : '');
                    }
                } else {
                    formData.append(`${key}[${index}]`, item);
                }
            });
        } else {
            formData.append(key, obj[key]);
        }
    }
    return formData;
}

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
            this.currentCollection = null // Reset to avoid showing stale data
            try {
                const response = await api.get(id)
                this.currentCollection = response.data || response
            } catch (err) {
                this.error = err.message
            } finally {
                this.loading = false
            }
        },
        async addCaveToCollection(collectionId, caveId) {
            // Deprecated in UI but kept for compatibility or manual calls
            try {
                await api.post(`${collectionId}/caves`, { cave_id: caveId });
                await this.fetchCollection(collectionId); // Refresh
            } catch (err) {
                this.error = err.message;
                throw err;
            }
        },
        async removeCaveFromCollection(collectionId, caveId) {
            // Deprecated in UI but kept for compatibility
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
                const identifier = collection.slug || collection.id;

                // If photo is present (File) or we have complex nested data for caves, use FormData
                // To allow file upload in PUT, we must use POST with _method=PUT
                const isFormData = (collection.photo instanceof File) ||
                    (collection.caves && collection.caves.length > 0);

                if (isFormData) {
                    const formData = toFormData(collection);
                    formData.append('_method', 'PUT');
                    // mande handles FormData but likely keeps JSON header, ensuring it's cleared
                    await api.post(identifier, formData, { headers: { 'Content-Type': null } });
                } else {
                    await api.put(identifier, collection);
                }

                // Fetch using slug as well to be consistent
                await this.fetchCollection(identifier);
            } catch (err) {
                this.error = err.message;
                throw err;
            }
        },
        async createCollection(collection) {
            try {
                const isFormData = (collection.photo instanceof File) ||
                    (collection.caves && collection.caves.length > 0);

                if (isFormData) {
                    const formData = toFormData(collection);
                    return await api.post('/', formData, { headers: { 'Content-Type': null } });
                } else {
                    return await api.post(collection);
                }
            } catch (err) {
                this.error = err.message;
                throw err;
            }
        }
    },
})
