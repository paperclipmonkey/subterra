/**
 * router/index.ts
 *
 * Automatic routes for `./src/pages/*.vue`
 *
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
  console.log('Router Error:', err)
  console.log('Target:', to)
  // alert('Router Error: ' + err.message + '\nTarget: ' + to.fullPath) 
  if (err?.message?.includes?.('Failed to fetch dynamically imported module')) {
    if (!localStorage.getItem('vuetify:dynamic-reload')) {
      console.log('Reloading page to fix dynamic import error', to.fullPath)
      localStorage.setItem('vuetify:dynamic-reload', 'true')
      location.assign(to.fullPath)
    } else {
      console.error('Dynamic import error, reloading page did not fix it', err)
      alert('Dynamic import error: ' + err.message)
    }
  } else {
    console.error(err)
  }
})

// Basic cookie functionality for login check
router.beforeEach(async (to, from, next) => {
  console.log('[Debug] Router beforeEach', { to: to.fullPath, from: from.fullPath })

  // Allow demo page without authentication
  if (to.path === '/demo') {
    return next()
  }

  let user = await useAppStore().getUser()
  console.log('[Debug] User loaded:', user ? 'Yes' : 'No', { is_approved: user?.is_approved, is_admin: user?.is_admin })

  // Exception for magic link login page
  if (to.path.startsWith('/magiclink/')) {
    return next()
  }

  if (to.name === '/') {
    if (user.email) {
      return next({ name: '/trips' })
    }
    return next()
  }

  if (!user.is_approved && !['/waitlist', '/news', '/profile/[id]', '/profile/[id].edit'].includes(to.name)) {
    return next({ name: '/waitlist' })
  }

  // Admin route check
  if (to.path.startsWith('/admin')) {
    if (!user.is_admin) {
      // Redirect non-admins away from admin pages
      return next({ name: '/trips' }); // Or wherever you want to redirect them
    }
  }

  if (user.email) {
    return next()
  }
  return next({ name: '/' })
})

router.isReady().then(() => {
  localStorage.removeItem('vuetify:dynamic-reload')
})

export default router
