/**
 * main.js
 *
 * Bootstraps Vuetify and other plugins then mounts the App`
 */

// Plugins
import { registerPlugins } from '@/plugins'

// Components
import App from './App.vue'

// Composables
import { createApp } from 'vue'

// Import calendar heatmap CSS
import 'vue3-calendar-heatmap/dist/style.css'

const app = createApp(App)

registerPlugins(app)

app.mount('#app')

// Register PWA service worker with auto-update
import { registerSW } from 'virtual:pwa-register'
import { useOfflineStore } from '@/stores/offline'

const updateSW = registerSW({
  onNeedRefresh() {
    // A new version is available - the SwUpdatePrompt component handles the UI
    const offlineStore = useOfflineStore()
    offlineStore.setSwUpdateAvailable(true)
  },
  onOfflineReady() {
    console.log('Subterra is ready for offline use')
  },
  // Check for updates every 60 minutes
  onRegisteredSW(swUrl, registration) {
    if (registration) {
      const offlineStore = useOfflineStore()
      offlineStore.setSwRegistration(registration)

      setInterval(() => {
        registration.update()
      }, 60 * 60 * 1000)
    }
  },
})

