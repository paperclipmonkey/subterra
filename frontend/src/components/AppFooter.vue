<template>
  <div class="footer">
    <v-footer v-if="userStore.user.id" app>
      <v-bottom-navigation bg-color="surface" elevation="4">
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
import { mdiAccount, mdiAlertOctagram, mdiDotsHorizontal, mdiEarth, mdiLogin, mdiNotebookOutline } from '@mdi/js'
import { useAppStore } from '@/stores/app'

const userStore = useAppStore()

const items = [
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
  {
    title: 'Callout',
    icon: mdiAlertOctagram,
    href: '/callout',
    class: 'v-btn--active-warning'
  },
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
]
</script>

<style scoped lang="scss">
.footer {
  height: auto;
  bottom: 0px;
  position: fixed;
  width: 100%;
  padding: 0px;
}

.v-footer {
  padding: 0px;
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

/* Center items when they fit, but align left when they overflow */
:deep(.v-btn:first-child) {
  margin-left: auto;
}

:deep(.v-btn:last-child) {
  margin-right: auto;
}
</style>
