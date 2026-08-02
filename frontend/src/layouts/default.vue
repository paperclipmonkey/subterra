<template>
  <v-app>
    <v-main>
      <router-view />
    </v-main>

    <!-- Hide app chrome on iframe-embedded pages (e.g. public permit calendars). -->
    <AppFooter v-if="!isEmbed" />
  </v-app>
</template>

<script setup>
  import { computed } from 'vue'
  import { useRoute } from 'vue-router'

  const route = useRoute()
  const isEmbed = computed(() => route.path.startsWith('/embed/'))
</script>
