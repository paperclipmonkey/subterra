/**
 * plugins/index.js
 *
 * Automatically included in `./src/main.js`
 */

// Plugins
import vuetify from './vuetify'
import pinia from '@/stores'
import router from '@/router'
import toast from './toast'
import api from './api'

export function registerPlugins(app) {
  app
    .use(vuetify)
    .use(router)
    .use(pinia)
    .use(toast)
    .use(api)
}

