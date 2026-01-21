<template>
  <div class="footer">
    <v-footer app>
      <v-bottom-navigation bg-color="surface" elevation="4">
        <v-btn v-for="item in items" :key="item.title" :to="item.href" :title="item.title" icon>
          <v-icon :icon="item.icon" />
          <v-tooltip bottom>
            <template v-slot:activator="{ on, attrs }">
              <span v-bind="attrs" :v-on="attrs">{{ item.title }}</span>
            </template>
          </v-tooltip>
        </v-btn>

      </v-bottom-navigation>
    </v-footer>
  </div>
</template>

<script setup>
import { useAppStore } from '@/stores/app';

const userStore = useAppStore();

const items = [
  {
    title: 'My Trips',
    icon: `mdi-notebook-outline`,
    href: '/trips',
  },
  {
    title: 'Caves',
    icon: 'mdi-earth',
    href: '/caves',
  },
  // {
  //   title: 'Collections',
  //   icon: `mdi-bookmark-box-multiple-outline`,
  //   href: '/collections',
  // },
  {
    title: 'Callout',
    icon: `mdi-alert-octagram`,
    href: '/callout',
    class: 'v-btn--active-warning' // Optional styling if needed, or just standard
  },
  {
    title: 'Profile',
    icon: `mdi-account`,
    href: '/profile/' + userStore.user.id,
  },
  {
    title: 'More',
    icon: `mdi-dots-horizontal`,
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

  /* Optional: Hide scrollbar for cleaner look, or keep it */
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
