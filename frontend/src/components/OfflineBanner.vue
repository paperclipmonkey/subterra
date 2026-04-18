<template>
  <v-slide-y-transition>
    <v-system-bar
      v-if="isOffline"
      color="grey-darken-3"
      class="text-white px-4"
      height="36"
      style="z-index: 10000;"
      window
    >
      <v-icon color="white" class="mr-2" :icon="mdiWifiOff" size="small" />
      <span class="font-weight-bold text-body-2">YOU ARE OFFLINE</span>
      <v-spacer />
      <span class="text-caption d-none d-sm-flex">
        {{ downloadedCount > 0 ? `${downloadedCount} cave(s) available offline` : 'Only downloaded caves are available' }}
      </span>
    </v-system-bar>
  </v-slide-y-transition>
</template>

<script setup>
import { mdiWifiOff } from '@mdi/js'
import { computed } from 'vue'
import { useOfflineStore } from '@/stores/offline'

const offlineStore = useOfflineStore()
const isOffline = computed(() => !offlineStore.isOnline)
const downloadedCount = computed(() => offlineStore.downloadedCaveCount)
</script>
