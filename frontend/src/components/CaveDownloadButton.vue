<template>
  <div>
    <v-btn
      v-if="!isDownloaded && !isDownloading"
      :variant="variant"
      :color="color"
      :size="size"
      :block="block"
      :prepend-icon="mdiCloudDownload"
      :disabled="!isOnline"
      @click="startDownload"
    >
      {{ label || 'Save Offline' }}
    </v-btn>

    <div v-else-if="isDownloading" class="d-flex align-center" :class="block ? 'w-100' : ''">
      <v-progress-linear
        :model-value="offlineStore.downloadProgress"
        color="primary"
        height="8"
        rounded
        class="flex-grow-1 mr-2"
      />
      <span class="text-caption text-medium-emphasis">{{ offlineStore.downloadProgress }}%</span>
    </div>

    <v-btn
      v-else
      :variant="variant"
      color="success"
      :size="size"
      :block="block"
      :prepend-icon="mdiCheckCircle"
      @click="showRemoveDialog = true"
    >
      Saved Offline
    </v-btn>

    <!-- Remove confirmation dialog -->
    <v-dialog v-model="showRemoveDialog" max-width="400">
      <v-card>
        <v-card-title>Remove Offline Data</v-card-title>
        <v-card-text>
          Remove this cave's offline data? You won't be able to view it without an internet connection.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showRemoveDialog = false">Cancel</v-btn>
          <v-btn color="error" variant="flat" @click="removeDownload">Remove</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { mdiCheckCircle, mdiCloudDownload } from '@mdi/js'
import { computed, ref } from 'vue'
import { useOfflineStore } from '@/stores/offline'
import { useNotificationStore } from '@/stores/notifications'
import { api } from '@/plugins/api'

const props = defineProps({
  caveId: { type: [Number, String], required: true },
  label: { type: String, default: '' },
  variant: { type: String, default: 'tonal' },
  color: { type: String, default: 'primary' },
  size: { type: String, default: 'default' },
  block: { type: Boolean, default: false },
})

const offlineStore = useOfflineStore()
const notificationStore = useNotificationStore()
const showRemoveDialog = ref(false)

const isOnline = computed(() => offlineStore.isOnline)
const isDownloaded = computed(() => offlineStore.isCaveDownloaded(Number(props.caveId)))
const isDownloading = computed(() => offlineStore.downloadingCaveId === Number(props.caveId))

const startDownload = async () => {
  const result = await offlineStore.downloadCaveForOffline(Number(props.caveId), api)
  if (result.success) {
    notificationStore.showSuccess('Cave saved for offline use')
  } else {
    notificationStore.showError('Failed to save cave offline: ' + result.error)
  }
}

const removeDownload = async () => {
  showRemoveDialog.value = false
  await offlineStore.removeCaveOfflineData(Number(props.caveId))
  notificationStore.showInfo('Offline data removed')
}
</script>
