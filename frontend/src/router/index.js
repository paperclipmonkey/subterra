/**
 * router/index.ts
 *
 * Automatic routes for `./src/pages/*.vue`
 */

// Composables
import { createRouter, createWebHistory } from 'vue-router/auto'
import { setupLayouts } from 'virtual:generated-layouts'
import { routes } from 'vue-router/auto-routes'
import { useAppStore } from '@/stores/app'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: setupLayouts(routes),
})

// Workaround for https://github.com/vitejs/vite/issues/11804
router.onError((err, to) => {
  if (err?.message?.includes?.('Failed to fetch dynamically imported module')) {
    if (!localStorage.getItem('vuetify:dynamic-reload')) {
      console.log('Reloading page to fix dynamic import error')
      localStorage.setItem('vuetify:dynamic-reload', 'true')
      location.assign(to.fullPath)
    } else {
      console.error('Dynamic import error, reloading page did not fix it', err)
    }
  } else {
    console.error(err)
  }
})

// Basic cookie functionality for login check
router.beforeEach(async (to, from, next) => {
  let user = await useAppStore().getUser()
  if(to.name === '/') {
    if(user.email) {
      return next({ name: '/trips' })
    }
    return next()
  }

  if(!user.is_approved && !['/waitlist', '/news'].includes(to.name)) {
    return next({ name: '/waitlist' })
  }

  // Admin route check
  if (to.path.startsWith('/admin')) {
    if (!user.is_admin) {
      // Redirect non-admins away from admin pages
      return next({ name: '/trips' }); // Or wherever you want to redirect them
    }
  }

  if(user.email) {
    return next()
  }
  return next({ name: '/' })
})

router.isReady().then(() => {
  localStorage.removeItem('vuetify:dynamic-reload')
})

export default router
