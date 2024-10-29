/**
 * plugins/index.js
 *
 * Automatically included in `./src/main.js`
 */

// Plugins
import vuetify from './vuetify'
import pinia from '@/stores'
import router from '@/router'
import { vuetifyProTipTap } from './tiptap'

export function registerPlugins (app) {
  app
    .use(vuetify)
    .use(vuetifyProTipTap)
    .use(router)
    .use(pinia)
}
