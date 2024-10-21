// Utilities
import { defineStore } from 'pinia'
import { mande } from 'mande'
import { useRouter } from 'vue-router';
const router = useRouter();
const api = mande('/api/users/me')

export const useAppStore = defineStore('app', {
  state: () => ({
    user: {
      name: '',
      email: '',
    }
    //
  }),

  actions: {
    async getUser(login, password) {
      try {
        this.user = (await api.get()).data
        return this.user
        // showTooltip(`Welcome back ${this.userData.name}!`)
      } catch (error) {
        // showTooltip(error)
        // let the form component display the error
        return error
      }
    },
  },
})
