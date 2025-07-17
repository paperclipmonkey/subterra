// Utilities
import { defineStore } from 'pinia'

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    show: false,
    message: '',
    type: 'info', // 'success', 'error', 'warning', 'info'
    timeout: 4000,
  }),

  actions: {
    showNotification(message, type = 'info', timeout = 4000) {
      this.message = message
      this.type = type
      this.timeout = timeout
      this.show = true
    },

    showSuccess(message, timeout = 4000) {
      this.showNotification(message, 'success', timeout)
    },

    showError(message, timeout = 6000) {
      this.showNotification(message, 'error', timeout)
    },

    showWarning(message, timeout = 5000) {
      this.showNotification(message, 'warning', timeout)
    },

    showInfo(message, timeout = 4000) {
      this.showNotification(message, 'info', timeout)
    },

    hideNotification() {
      this.show = false
    },
  },
})