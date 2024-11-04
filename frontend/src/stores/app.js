// Utilities
import { defineStore } from 'pinia'
import { mande } from 'mande'
// import { useRouter } from 'vue-router';
// const router = useRouter();
const api = mande('/api/users/me', { headers: { 'Accept': 'application/json' } })

export const useAppStore = defineStore('app', {
  state: () => ({
    user: {
      name: '',
      email: '',
      is_admin: false,
      is_approved: false,
    },
    loading: false,
    //
  }),

  actions: {
    async getUser() {
      try {
        this.loading = true
        this.user = (await api.get()).data
        this.loading = false
        return this.user
        // showTooltip(`Welcome back ${this.userData.name}!`)
      } catch (error) {
        this.loading = false
        // showTooltip(error)
        // let the form component display the error
        return error
      }
    },
  },
})
