<template>
  <v-container>
    <v-row justify="center">
      <v-col cols="12" md="10" lg="8">
        <div class="d-flex align-center mb-6">
          <v-icon :icon="mdiCloudDownload" size="40" color="primary" class="mr-3" />
          <div>
            <h1 class="text-h4 font-weight-bold">Offline Caves</h1>
            <p class="text-body-2 text-medium-emphasis mb-0">
              Caves saved for use without internet connection
            </p>
          </div>
        </div>

        <!-- Storage Info -->
        <v-card variant="outlined" class="mb-6">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-body-2 text-medium-emphasis">Storage Used</div>
                <div class="text-h6 font-weight-bold">{{ storage.usedMB }} MB</div>
              </div>
              <div class="text-right">
                <div class="text-body-2 text-medium-emphasis">Available</div>
                <div class="text-h6 font-weight-bold">{{ storage.quotaMB }} MB</div>
              </div>
            </div>
            <v-progress-linear
              :model-value="storagePercent"
              color="primary"
              height="6"
              rounded
              class="mt-2"
            />
          </v-card-text>
        </v-card>

        <!-- Search -->
        <v-text-field
          v-if="caves.length > 0"
          v-model="search"
          :prepend-inner-icon="mdiMagnify"
          label="Search offline caves"
          variant="outlined"
          density="compact"
          clearable
          class="mb-4"
        />

        <!-- Empty State -->
        <v-card v-if="caves.length === 0" variant="outlined" class="text-center pa-8">
          <v-icon :icon="mdiCloudOffOutline" size="80" color="grey-lighten-1" class="mb-4" />
          <h3 class="text-h5 font-weight-bold mb-2">No Caves Saved Offline</h3>
          <p class="text-body-1 text-medium-emphasis mb-4">
            Download caves to access their data, routes, and images without an internet connection — perfect for underground navigation.
          </p>
          <v-btn color="primary" to="/caves" :prepend-icon="mdiMagnify">
            Browse Caves
          </v-btn>
        </v-card>

        <!-- Cave List -->
        <v-card v-for="cave in filteredCaves" :key="cave.id" class="mb-3" variant="outlined">
          <v-card-text class="d-flex align-center">
            <v-avatar v-if="cave.hero_image" size="56" rounded="lg" class="mr-4">
              <v-img :src="cave.hero_image" />
            </v-avatar>
            <v-avatar v-else size="56" rounded="lg" color="grey-lighten-3" class="mr-4">
              <v-icon :icon="mdiImageOff" color="grey" />
            </v-avatar>
            <div class="flex-grow-1">
              <router-link :to="`/caves/${cave.slug || cave.id}`" class="text-decoration-none">
                <div class="text-subtitle-1 font-weight-bold text-primary">{{ cave.name }}</div>
              </router-link>
              <div class="text-caption text-medium-emphasis">
                {{ cave.system?.name || 'Unknown system' }}
                <span v-if="cave._offlineAt"> · Saved {{ formatDate(cave._offlineAt) }}</span>
              </div>
              <div v-if="cave._offlineMedia && cave._offlineMedia.length > 0" class="text-caption text-medium-emphasis">
                {{ cave._offlineMedia.length }} media file(s) cached
              </div>
            </div>
            <v-btn
              icon
              variant="text"
              color="error"
              size="small"
              @click="confirmRemove(cave)"
            >
              <v-icon :icon="mdiDelete" />
            </v-btn>
          </v-card-text>
        </v-card>

        <!-- Clear All -->
        <div v-if="caves.length > 0" class="text-center mt-6">
          <v-btn
            variant="text"
            color="error"
            :prepend-icon="mdiDeleteSweep"
            @click="showClearAll = true"
          >
            Remove All Offline Data
          </v-btn>
        </div>

        <!-- Info Card -->
        <v-card variant="tonal" color="info" class="mt-6">
          <v-card-text>
            <div class="d-flex align-start">
              <v-icon :icon="mdiInformation" class="mr-3 mt-1" />
              <div>
                <div class="font-weight-bold mb-1">How Offline Mode Works</div>
                <ul class="text-body-2 pl-4">
                  <li>Download caves from any cave detail page using the "Save Offline" button</li>
                  <li>All cave data, routes, and images are stored on your device</li>
                  <li>Access downloaded caves without internet — ideal for underground use</li>
                  <li>An offline banner will appear whenever you lose connection</li>
                  <li>Callout timers continue counting down offline</li>
                </ul>
                <v-btn
                  variant="text"
                  size="small"
                  color="info"
                  to="/pages/offline-mode"
                  class="mt-2 px-0"
                >
                  Learn more about offline mode →
                </v-btn>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Remove single confirmation -->
        <v-dialog v-model="showRemoveOne" max-width="400">
          <v-card>
            <v-card-title>Remove Offline Data</v-card-title>
            <v-card-text>
              Remove <strong>{{ caveToRemove?.name }}</strong> from offline storage?
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn variant="text" @click="showRemoveOne = false">Cancel</v-btn>
              <v-btn color="error" variant="flat" @click="doRemoveOne">Remove</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Clear all confirmation -->
        <v-dialog v-model="showClearAll" max-width="400">
          <v-card>
            <v-card-title>Clear All Offline Data</v-card-title>
            <v-card-text>
              This will remove all downloaded caves, images, and routes from your device. This cannot be undone.
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn variant="text" @click="showClearAll = false">Cancel</v-btn>
              <v-btn color="error" variant="flat" @click="doClearAll">Clear All</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import {
  mdiCloudDownload,
  mdiCloudOffOutline,
  mdiDelete,
  mdiDeleteSweep,
  mdiImageOff,
  mdiInformation,
  mdiMagnify,
} from '@mdi/js'
import { ref, computed, onMounted } from 'vue'
import { useOfflineStore } from '@/stores/offline'
import { useNotificationStore } from '@/stores/notifications'
import moment from 'moment'

const offlineStore = useOfflineStore()
const notificationStore = useNotificationStore()

const caves = ref([])
const search = ref('')
const storage = ref({ usedMB: 0, quotaMB: 0 })
const showRemoveOne = ref(false)
const showClearAll = ref(false)
const caveToRemove = ref(null)

const storagePercent = computed(() => {
  if (!storage.value.quota) return 0
  return Math.round((storage.value.used / storage.value.quota) * 100)
})

const filteredCaves = computed(() => {
  if (!search.value) return caves.value
  const s = search.value.toLowerCase()
  return caves.value.filter(cave =>
    cave.name?.toLowerCase().includes(s) ||
    cave.system?.name?.toLowerCase().includes(s)
  )
})

const formatDate = (ts) => moment(ts).fromNow()

const loadData = async () => {
  caves.value = await offlineStore.getAllOfflineCaves()
  storage.value = await offlineStore.getOfflineStorageSize()
}

const confirmRemove = (cave) => {
  caveToRemove.value = cave
  showRemoveOne.value = true
}

const doRemoveOne = async () => {
  if (caveToRemove.value) {
    await offlineStore.removeCaveOfflineData(caveToRemove.value.id)
    notificationStore.showInfo('Offline data removed')
    showRemoveOne.value = false
    caveToRemove.value = null
    await loadData()
  }
}

const doClearAll = async () => {
  await offlineStore.clearAllOfflineData()
  notificationStore.showInfo('All offline data cleared')
  showClearAll.value = false
  await loadData()
}

onMounted(loadData)
</script>
