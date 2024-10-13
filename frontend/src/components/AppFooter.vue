<template>
  <div class="footer">
  <v-footer app>
      <v-bottom-navigation>
      <v-btn
        v-for="item in items"
        :key="item.title"
        :to="item.href"
        :title="item.title"
        icon
      >
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
    {
      title: 'Profile',
      icon: `mdi-account`,
      href: '/me',
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
</style>
