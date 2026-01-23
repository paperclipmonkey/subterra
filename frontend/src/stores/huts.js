
import { defineStore } from 'pinia'
import { mande } from 'mande'

const api = mande('/api/huts')

export const useHutStore = defineStore('huts', {
    state: () => ({
        huts: [],
        currentHut: null,
        loading: false,
        error: null,
    }),
    actions: {
        async fetchHuts() {
            this.loading = true
            try {
                this.huts = await api.get()
            } catch (err) {
                this.error = err.message
            } finally {
                this.loading = false
            }
        },
        async fetchHut(id) {
            this.loading = true
            try {
                this.currentHut = await api.get(id)
            } catch (err) {
                this.error = err.message
            } finally {
                this.loading = false
            }
        },
        async createHut(hut) {
            try {
                return await api.post(hut);
            } catch (err) {
                this.error = err.message;
                throw err;
            }
        },
        async updateHut(hut) {
            try {
                return await api.put(hut.id, hut);
            } catch (err) {
                this.error = err.message;
                throw err;
            }
        },
        async deleteHut(id) {
            try {
                return await api.delete(id);
            } catch (err) {
                this.error = err.message;
                throw err;
            }
        }
    },
})
