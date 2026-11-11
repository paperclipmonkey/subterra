<template>
  <v-app>
    <v-main>
      <router-view />
    </v-main>

    <!-- Hide app chrome on iframe-embedded pages (e.g. public permit calendars). -->
    <AppFooter v-if="!isEmbed" />
    <QuickJump v-if="!isEmbed && userStore.user?.id" />
  </v-app>
</template>

<script setup>
  import { computed } from 'vue'
  import { useRoute } from 'vue-router'
  import { useAppStore } from '@/stores/app'

  const route = useRoute()
  const userStore = useAppStore()
  const isEmbed = computed(() => route.path.startsWith('/embed/'))
</script>
