<template>
  <v-snackbar
    v-model="showUpdatePrompt"
    color="primary"
    location="bottom"
    :timeout="-1"
  >
    <v-icon :icon="mdiUpdate" class="mr-2" />
    A new version of Subterra is available.

    <template #actions>
      <v-btn variant="text" @click="showUpdatePrompt = false">Later</v-btn>
      <v-btn variant="flat" color="white" class="text-primary" @click="doUpdate">Update Now</v-btn>
    </template>
  </v-snackbar>
</template>

<script setup>
import { mdiUpdate } from '@mdi/js'
import { computed } from 'vue'
import { useOfflineStore } from '@/stores/offline'

const offlineStore = useOfflineStore()

const showUpdatePrompt = computed({
  get: () => offlineStore.swUpdateAvailable,
  set: (val) => offlineStore.setSwUpdateAvailable(val),
})

const doUpdate = () => {
  offlineStore.updateServiceWorker()
}
</script>
