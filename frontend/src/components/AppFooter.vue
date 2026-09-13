<template>
  <div class="footer">
    <v-footer v-if="userStore.user.id" app class="bg-transparent">
      <v-bottom-navigation color="primary" elevation="0" class="nav-dock">
        <v-btn v-for="item in items" :key="item.title" :to="item.href" :title="item.title" icon>
          <v-icon :icon="item.icon" />
          <v-tooltip bottom>
            <template #activator="{ attrs }">
              <span v-bind="attrs" :v-on="attrs">{{ item.title }}</span>
            </template>
          </v-tooltip>
        </v-btn>

      </v-bottom-navigation>
    </v-footer>

    <v-footer v-else app>
      <v-bottom-navigation bg-color="primary" elevation="4">
        <v-btn to="/" block class="text-none">
          <v-icon start :icon="mdiLogin" />
          Login or Join to see more
        </v-btn>
      </v-bottom-navigation>
    </v-footer>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { mdiAccount, mdiAlertOctagram, mdiDotsHorizontal, mdiEarth, mdiLogin, mdiNotebookOutline } from '@mdi/js'
import { useAppStore } from '@/stores/app'

const userStore = useAppStore()

const canUseCallout = computed(() => {
  if (!userStore.calloutsEnabled) return false
  const roles = userStore.user?.roles ?? []
  return roles.some(r => r.slug === 'platform_admin' || r.slug === 'duty_officer' || r.slug === 'callout_access')
})

const items = computed(() => {
  const nav = [
    {
      title: 'My Trips',
      icon: mdiNotebookOutline,
      href: '/trips',
    },
    {
      title: 'Caves',
      icon: mdiEarth,
      href: '/caves',
    },
  ]

  if (canUseCallout.value) {
    nav.push({
      title: 'Callout',
      icon: mdiAlertOctagram,
      href: '/callout',
      class: 'v-btn--active-warning',
    })
  }

  nav.push(
    {
      title: 'Profile',
      icon: mdiAccount,
      href: '/profile/' + userStore.user.id,
    },
    {
      title: 'More',
      icon: mdiDotsHorizontal,
      href: '/more',
    },
  )

  return nav
})
</script>

<style scoped lang="scss">
.footer {
  height: auto;
  bottom: 0px;
  position: fixed;
  width: 100%;
  padding: 0px;
  // Sit above page content (sticky headers, expansion panels like the resolved
  // incidents table) but below dialogs/overlays (~2400) and the top system bars
  // (9999). Without an explicit z-index this fixed dock defaulted to `auto` (0),
  // so page panels painted over it.
  z-index: 1010;
  // The wrapper spans the full width, but only the centered dock is visible —
  // let clicks fall through the transparent gutters to the content beneath
  // (re-enabled on the nav itself below).
  pointer-events: none;
}

.v-footer {
  padding: 0px;
  background: transparent !important;
}

/* Only the actual nav bar is interactive, not the transparent full-width wrapper. */
:deep(.v-bottom-navigation) {
  pointer-events: auto;
}

/* Floating frosted dock instead of a full-width bar */
:deep(.v-bottom-navigation.nav-dock) {
  position: relative !important;
  left: auto !important;
  right: auto !important;
  bottom: auto !important;
  width: min(480px, calc(100vw - 24px)) !important;
  margin: 0 auto 10px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.58) !important;
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  box-shadow:
    0 2px 8px rgba(24, 38, 31, 0.08),
    0 12px 32px rgba(24, 38, 31, 0.2) !important;
  border: 1px solid rgba(24, 38, 31, 0.08);
  overflow: hidden;
}

:deep(.v-bottom-navigation__content) {
  overflow-x: auto;
  justify-content: flex-start !important;
  flex-wrap: nowrap;

  &::-webkit-scrollbar {
    display: none;
  }

  -ms-overflow-style: none;
  scrollbar-width: none;
}

:deep(.v-btn) {
  min-width: 80px;
  /* Prevent squashing */
  flex-shrink: 0;
}

/* Fit all five items inside the dock on narrow phones */
@media (max-width: 600px) {
  :deep(.v-bottom-navigation .v-btn) {
    min-width: 0 !important;
    flex: 1 1 0;
    padding: 0 4px;
    font-size: 0.7rem !important;
    letter-spacing: 0;
  }
}

/* Center items when they fit, but align left when they overflow */
:deep(.v-btn:first-child) {
  margin-left: auto;
}

:deep(.v-btn:last-child) {
  margin-right: auto;
}
</style>
