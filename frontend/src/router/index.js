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
  if (err?.message?.includes?.('Failed to fetch dynamically imported module')) {
    if (!localStorage.getItem('vuetify:dynamic-reload')) {
      console.warn('Reloading page to fix dynamic import error', to.fullPath)
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

  // Allow demo page without authentication
  if (to.path === '/demo') {
    return next()
  }

  // Allow offline caves page — still warm the user cache in the background
  // so components don't render with empty user state after a hard refresh.
  if (to.path === '/offline') {
    useAppStore().getUser().catch(() => {})
    return next()
  }

  let forceRefresh = false
  if (to.path.startsWith('/profile') || to.path.startsWith('/callout')) {
    forceRefresh = true
  }
  let user = await useAppStore().getUser(forceRefresh)

  // Exception for magic link login page, CMS pages, and public trip reports
  if (to.path.startsWith('/magiclink/') || to.path.startsWith('/pages/') || to.path === '/callout/active' || (to.path.startsWith('/trips/') && to.params.id && to.params.id.length >= 8)) {
    return next()
  }




  if (to.name === '/') {
    if (user.email) {
      return next({ path: '/trips' })
    }
    return next()
  }



  // Admin route check
  if (to.path.startsWith('/admin')) {
    if (!user.is_admin) {
      // Redirect non-admins away from admin pages
      return next({ path: '/trips' })
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

    // Access Officer Routes (Exclusive)
    if (
      (to.path.startsWith('/admin/permits') ||
        to.path.startsWith('/admin/bookings')) &&
      !hasRole('access_officer') && !hasRole('platform_admin')
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

  // Pip access — platform_admin OR pip_access role explicitly granted.
  if (to.path.startsWith('/pip')) {
    const hasRoleSlug = (slug) => user.roles && user.roles.some(r => r.slug === slug)
    if (!hasRoleSlug('platform_admin') && !hasRoleSlug('pip_access')) {
      return next({ path: '/trips' })
    }
  }

  if (user.email && (!user.name || user.name.trim() === '')) {
    const isProfilePage = to.name === '/profile/[id].edit' || to.path.includes('/profile/')
    if (!isProfilePage && to.path !== '/logout') {
      return next({ name: '/profile/[id].edit', params: { id: user.id } })
    }
  }

  if (user.email) {
    return next()
  }

  // When offline and there's no cached user session, redirect unauthenticated users
  // to the offline caves page instead of the login page.
  // Authenticated users (caught by user.email check above) navigate freely —
  // individual pages are responsible for showing appropriate offline state.
  if (!navigator.onLine) {
    if (
      to.path === '/offline' ||
      to.path === '/callout/active' ||
      to.path.startsWith('/caves') ||
      to.path.startsWith('/pages/')
    ) {
      return next()
    }
    return next({ path: '/offline' })
  }

  return next({ path: '/' })
})

// Scroll to top after each navigation, and clear the dynamic-reload retry flag
// so any subsequent chunk-load failure can trigger another reload.
router.afterEach(() => {
  document.title = 'subterra.world'
  window.scrollTo(0, 0)
  localStorage.removeItem('vuetify:dynamic-reload')
})

router.isReady().then(() => {
  localStorage.removeItem('vuetify:dynamic-reload')
})

export default router
