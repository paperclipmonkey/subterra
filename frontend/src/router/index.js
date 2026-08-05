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
import { authGuard, afterEachHook, handleRouterError, scrollBehavior } from './guard'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: setupLayouts(routes),
  scrollBehavior,
})

// Workaround for https://github.com/vitejs/vite/issues/11804
router.onError(handleRouterError)

router.beforeEach(authGuard)

// Reset the title after each navigation, and clear the dynamic-reload retry flag
// so any subsequent chunk-load failure can trigger another reload. Scrolling is
// handled by scrollBehavior above.
router.afterEach(afterEachHook)

router.isReady().then(() => {
  localStorage.removeItem('vuetify:dynamic-reload')
})

export default router
