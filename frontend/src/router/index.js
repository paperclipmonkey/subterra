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
  // console.log('[Debug] Router beforeEach', { to: to.fullPath, from: from.fullPath })

  // Allow demo page without authentication
  if (to.path === '/demo') {
    return next()
  }

  let user = await useAppStore().getUser()
  // console.log('[Debug] User loaded:', user ? 'Yes' : 'No', { is_admin: user?.is_admin })

  // Exception for magic link login page, CMS pages, and public trip reports
  if (to.path.startsWith('/magiclink/') || to.path.startsWith('/pages/') || to.path === '/callout/active' || (to.path.startsWith('/trips/') && to.params.id && to.params.id.length >= 8)) {
    return next()
  }




  if (to.name === '/') {
    if (user.email) {
      // console.log('[Debug] Redirecting / to /trips because user is logged in')
      return next({ path: '/trips' })
    }
    return next()
  }



  // Admin route check
  if (to.path.startsWith('/admin')) {
    if (!user.is_admin) {
      // Redirect non-admins away from admin pages
      // console.log('[Debug] User not admin, redirecting to /trips')
      return next({ path: '/trips' });
    }

    // Role-based guarding for specific sub-routes
    const hasRole = (role) => user.roles && user.roles.some(r => r.slug === role)

    // Platform Admin Routes (Exclusive)
    if (
      (to.path.startsWith('/admin/users') ||
        to.path.startsWith('/admin/clubs') ||
        to.path.startsWith('/admin/communications') ||
        to.path.startsWith('/admin/dashboard')) &&
      !hasRole('platform_admin')
    ) {
      return next({ path: '/admin' })
    }

    // Duty Officer Routes (Exclusive)
    if (
      (to.path.startsWith('/admin/callout') ||
        to.path.startsWith('/admin/rota')) &&
      !hasRole('duty_officer')
    ) {
      return next({ path: '/admin' })
    }

    // Data Admin Routes (Exclusive)
    if (
      (to.path.startsWith('/admin/catchments') ||
        to.path.startsWith('/admin/cave-system-with-cave')) &&
      !hasRole('data_admin')
    ) {
      return next({ path: '/admin' })
    }

    // Shared Routes (Platform Admin OR Data Admin)
    if (
      (to.path.startsWith('/admin/pages') ||
        to.path.startsWith('/admin/suggested-edits') ||
        to.path.startsWith('/admin/tasks')) &&
      (!hasRole('platform_admin') && !hasRole('data_admin'))
    ) {
      return next({ path: '/admin' })
    }
  }

  if (user.email) {
    // console.log('[Debug] User logged in, allowing navigation to:', to.fullPath)
    return next()
  }
  // console.log('[Debug] User not logged in, redirecting to /')
  return next({ path: '/' })
})

// Scroll to top after each navigation
router.afterEach(() => {
  document.title = 'subterra.world'
  window.scrollTo(0, 0)
})

router.isReady().then(() => {
  localStorage.removeItem('vuetify:dynamic-reload')
})

export default router
