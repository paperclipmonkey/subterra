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
  console.log(user)
  console.log('router.beforeEach')
  console.log(to.name)
  if(to.name === '/') {
    console.log('user is on login page')
    console.log(user.email)
    if(user.email) {
      console.log('user is logged in')
      return next({ name: '/trips' })
    }
    return next()
  }

  if(user.email) {
    console.log('user is available')
    return next()
  }
  return next({ name: '/' })
})

router.isReady().then(() => {
  localStorage.removeItem('vuetify:dynamic-reload')
})

export default router
