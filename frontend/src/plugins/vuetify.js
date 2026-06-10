/**
 * plugins/vuetify.js
 *
 * Framework documentation: https://vuetifyjs.com`
 */

// Styles
import 'vuetify/styles'
import '@/styles/global.scss'

// Icons
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg'

// Composables
import { createVuetify } from 'vuetify'

// https://vuetifyjs.com/en/introduction/why-vuetify/#feature-guides
export default createVuetify({
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: {
      mdi,
    },
  },
  theme: {
    defaultTheme: 'light',
    variations: {
      colors: ['primary', 'secondary'],
      lighten: 5,
      darken: 4,
    },
    themes: {
      light: {
        colors: {
          // Earthy palette drawn from the Subterra logo: pine green,
          // bark brown, amber sunlight and warm stone neutrals.
          primary: '#2F6852',
          secondary: '#7E5A3C',
          accent: '#D9A441',
          error: '#C94F44',
          info: '#3E7CB1',
          success: '#37875B',
          warning: '#E8930C',
          background: '#F4F4F0',
          surface: '#FFFFFF',
          'on-background': '#1E2A24',
          'on-surface': '#1E2A24',
        },
      },
    },
  },
  defaults: {
    VCard: { rounded: 'lg' },
    VAlert: { rounded: 'lg' },
    VTextField: { density: 'comfortable' },
    VSelect: { density: 'comfortable' },
    VAutocomplete: { density: 'comfortable' },
    VCombobox: { density: 'comfortable' },
  },
})
