<template>
  <div v-if="trip" class="fill-height bg-grey-lighten-4">
    <!-- Hero Header - with photo -->
    <v-img v-if="heroImage" :src="heroImage" cover height="300" class="align-end"
           gradient="to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.8)">
      <!-- Top Navigation -->
      <div class="position-absolute top-0 left-0 w-100 d-flex align-center pa-4">
        <v-btn :icon="mdiArrowLeft" variant="tonal" color="white" class="backdrop-blur"
               @click="$router.push('/trips')" />
        <v-spacer />
        <template v-if="currentUserWasOnTrip">
          <v-btn :icon="mdiPencil" variant="tonal" color="white" class="mr-2 backdrop-blur"
                 @click="$router.push('/trips/' + trip.id + '/edit')" />
          <v-btn :icon="mdiDelete" variant="tonal" color="error" class="backdrop-blur text-white"
                 @click="showDeleteConfirmDialog = true" />
        </template>
      </div>
      <!-- Hero Content -->
      <v-container class="pb-6">
        <div class="text-subtitle-1 text-white mb-1 d-flex align-center">
          <v-icon :icon="mdiMapMarker" size="small" class="mr-1" />
          {{ trip.system?.name || 'Unknown System' }}
        </div>
        <h1 class="text-h3 text-white font-weight-bold mb-2">{{ trip.name }}</h1>
        <div class="d-flex align-center text-white">
          <v-chip size="small" color="white" variant="outlined" class="mr-3">
            <v-icon start :icon="mdiCalendar" />
            {{ formatDate(trip.start_time) }}
          </v-chip>
          <v-chip :color="getVisibilityColor(trip.visibility)" size="small" variant="flat">
            <v-icon start size="small">{{ getVisibilityIcon(trip.visibility) }}</v-icon>
            {{ trip.visibility }}
          </v-chip>
        </div>
      </v-container>
    </v-img>

    <!-- Hero Header - no photo gradient fallback -->
    <div v-else class="trip-hero-gradient d-flex flex-column justify-end" style="height: 300px; position: relative;">
      <!-- Top Navigation -->
      <div class="position-absolute top-0 left-0 w-100 d-flex align-center pa-4" style="z-index: 1;">
        <v-btn :icon="mdiArrowLeft" variant="tonal" color="white" class="backdrop-blur"
               @click="$router.push('/trips')" />
        <v-spacer />
        <template v-if="currentUserWasOnTrip">
          <v-btn :icon="mdiPencil" variant="tonal" color="white" class="mr-2 backdrop-blur"
                 @click="$router.push('/trips/' + trip.id + '/edit')" />
          <v-btn :icon="mdiDelete" variant="tonal" color="error" class="backdrop-blur text-white"
                 @click="showDeleteConfirmDialog = true" />
        </template>
      </div>
      <!-- Hero Content -->
      <v-container class="pb-6" style="position: relative; z-index: 1;">
        <div class="text-subtitle-1 text-white mb-1 d-flex align-center">
          <v-icon :icon="mdiMapMarker" size="small" class="mr-1" />
          {{ trip.system?.name || 'Unknown System' }}
        </div>
        <h1 class="text-h3 text-white font-weight-bold mb-2">{{ trip.name }}</h1>
        <div class="d-flex align-center text-white">
          <v-chip size="small" color="white" variant="outlined" class="mr-3">
            <v-icon start :icon="mdiCalendar" />
            {{ formatDate(trip.start_time) }}
          </v-chip>
          <v-chip :color="getVisibilityColor(trip.visibility)" size="small" variant="flat">
            <v-icon start size="small">{{ getVisibilityIcon(trip.visibility) }}</v-icon>
            {{ trip.visibility }}
          </v-chip>
        </div>
      </v-container>
    </div>

    <v-container class="mt-n8 position-relative">
      <v-row>
        <!-- Main Content (Left) -->
        <v-col cols="12" md="8">
          <!-- Trip Report -->
          <v-card class="rounded-lg mb-6" elevation="2">
            <v-card-title class="d-flex align-center py-4 bg-surface">
              <v-icon :icon="mdiTextBoxOutline" class="mr-2 text-primary" />
              Trip Report
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-6 text-body-1 leading-relaxed">
              <MarkdownRenderer v-if="trip.description" :source="trip.description" />
              <div v-else class="d-flex flex-column align-center justify-center py-8 text-grey">
                <v-icon :icon="mdiFountainPenTip" size="large" class="mb-2 opacity-50" />
                <p>No report written for this trip yet.</p>
              </div>
            </v-card-text>
          </v-card>

          <!-- Photo Gallery -->
          <v-card v-if="trip.media && trip.media.length > 0" class="rounded-lg mb-6" elevation="2">
            <v-card-title class="d-flex align-center py-4">
              <v-icon :icon="mdiImageMultipleOutline" class="mr-2 text-primary" />
              Gallery
              <span class="text-caption text-grey ml-2">({{ trip.media.length }})</span>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-4">
              <v-row dense>
                <v-col v-for="media in trip.media" :key="media.filename" cols="6" sm="4" md="3">
                  <v-hover v-slot="{ isHovering, props }">
                    <v-card v-bind="props" flat
                            class="rounded-lg border cursor-pointer overflow-hidden transition-swing"
                            :elevation="isHovering ? 4 : 0" @click="openMedia(media)">
                      <v-img :src="media.url" aspect-ratio="1" cover
                             class="bg-grey-lighten-2 transition-transform" :class="{ 'scale-110': isHovering }">
                        <template #placeholder>
                          <div class="d-flex align-center justify-center fill-height">
                            <v-progress-circular indeterminate
                                                 color="grey-lighten-4" />
                          </div>
                        </template>
                      </v-img>
                    </v-card>
                  </v-hover>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Sidebar (Right) -->
        <v-col cols="12" md="4">
          <!-- Key Stats Card -->
          <v-card class="rounded-lg mb-6" elevation="2">
            <v-card-title class="py-4 font-weight-bold">Trip Details</v-card-title>
            <v-divider />
            <v-list class="py-0">
              <!-- Entrance -->
              <v-list-item class="py-3">
                <template #prepend>
                  <v-avatar color="green-lighten-5" class="mr-4" rounded>
                    <v-icon color="green-darken-1" :icon="mdiLocationEnter" />
                  </v-avatar>
                </template>
                <v-list-item-subtitle class="text-caption mb-1">Entrance</v-list-item-subtitle>
                <v-list-item-title>
                  <router-link :to="'/caves/' + trip.entrance.slug"
                               class="text-decoration-none font-weight-bold text-high-emphasis text-primary">
                    {{ trip.entrance.name }}
                  </router-link>
                </v-list-item-title>
              </v-list-item>

              <v-divider inset />

              <!-- Exit (if different) -->
              <v-list-item v-if="trip.exit && trip.entrance.id !== trip.exit.id" class="py-3">
                <template #prepend>
                  <v-avatar color="red-lighten-5" class="mr-4" rounded>
                    <v-icon color="red-darken-1" :icon="mdiLocationExit" />
                  </v-avatar>
                </template>
                <v-list-item-subtitle class="text-caption mb-1">Exit</v-list-item-subtitle>
                <v-list-item-title>
                  <router-link :to="'/caves/' + trip.exit.slug"
                               class="text-decoration-none font-weight-bold text-high-emphasis text-primary">
                    {{ trip.exit.name }}
                  </router-link>
                </v-list-item-title>
              </v-list-item>

              <v-divider v-if="trip.exit && trip.entrance.id !== trip.exit.id" inset />

              <!-- Time -->
              <v-list-item class="py-3">
                <template #prepend>
                  <v-avatar color="blue-lighten-5" class="mr-4" rounded>
                    <v-icon color="blue-darken-1" :icon="mdiClockOutline" />
                  </v-avatar>
                </template>
                <v-list-item-subtitle class="text-caption mb-1">Start Time</v-list-item-subtitle>
                <v-list-item-title class="font-weight-medium">{{ formatTime(trip.start_time)
                }}</v-list-item-title>
              </v-list-item>

              <v-divider inset />

              <!-- Duration -->
              <v-list-item class="py-3">
                <template #prepend>
                  <v-avatar color="orange-lighten-5" class="mr-4" rounded>
                    <v-icon color="orange-darken-1" :icon="mdiTimerOutline" />
                  </v-avatar>
                </template>
                <v-list-item-subtitle class="text-caption mb-1">Duration</v-list-item-subtitle>
                <v-list-item-title class="font-weight-medium">{{ formatDuration(trip.start_time, trip.end_time)
                }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-card>

          <!-- Participants -->
          <v-card class="rounded-lg" elevation="2">
            <v-card-title class="d-flex align-center justify-space-between py-4">
              <span>The Team</span>
              <v-chip color="secondary" size="small" variant="flat">{{ trip.participants.length }}</v-chip>
            </v-card-title>
            <v-divider />
            <v-list class="py-2">
              <v-list-item v-for="participant in trip.participants" :key="participant.id"
                           :to="'/profile/' + participant.id" rounded="lg" class="ma-2 mb-1">
                <template #prepend>
                  <v-avatar color="grey-lighten-2" size="40" class="border">
                    <v-img :src="participant.photo || '/default-avatar.png'" :alt="participant.name"
                           cover />
                  </v-avatar>
                </template>
                <v-list-item-title class="font-weight-bold">{{ participant.name }}</v-list-item-title>
                <v-list-item-subtitle class="text-caption text-truncate">
                  {{ participant.clubs && participant.clubs.length ? participant.clubs.map(c =>
                    c.name).join(', ') :
                    'No club' }}
                </v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card>
        </v-col>
      </v-row>
    </v-container>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteConfirmDialog" persistent max-width="400">
      <v-card class="rounded-lg">
        <v-card-title class="text-h6 pa-4">Delete Trip?</v-card-title>
        <v-card-text class="pt-0 pb-4">Are you sure you want to delete this trip report? This action cannot be
          undone.</v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="showDeleteConfirmDialog = false">Cancel</v-btn>
          <v-btn color="error" variant="flat" @click="confirmDelete">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <MediaViewModal v-model="showMediaModal" :media="selectedMedia" />
  </div>

  <!-- Loading State -->
  <v-container v-else-if="loading" class="fill-height d-flex justify-center align-center">
    <v-progress-circular indeterminate color="primary" size="64" />
  </v-container>

  <!-- Error State -->
  <v-container v-else-if="error" class="fill-height d-flex flex-column justify-center align-center text-center">
    <v-icon :icon="mdiAlertCircleOutline" size="64" color="grey" class="mb-4" />
    <h2 class="text-h5 text-grey-darken-1 mb-2">Oops!</h2>
    <p class="text-body-1 text-grey mb-6">{{ error }}</p>
    <v-btn color="primary" variant="flat" to="/trips" :prepend-icon="mdiArrowLeft">
      Back to Trips
    </v-btn>
  </v-container>
</template>

<script setup>
import { mdiAccountGroup, mdiAlertCircleOutline, mdiArrowLeft, mdiCalendar, mdiClockOutline, mdiDelete, mdiEarth, mdiFountainPenTip, mdiImageMultipleOutline, mdiLocationEnter, mdiLocationExit, mdiLock, mdiMapMarker, mdiPencil, mdiTextBoxOutline, mdiTimerOutline } from '@mdi/js'

import MediaViewModal from '@/components/MediaViewModal.vue'
import moment from 'moment'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import { useRouter, useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { ref, computed, onMounted } from 'vue'
import { useToast } from "vue-toastification"
import { usePageTitle } from '@/composables/usePageTitle'

const appStore = useAppStore()
const router = useRouter()
const route = useRoute()
const toast = useToast()

const trip = ref(null)
const loading = ref(true)
const error = ref(null)
const showDeleteConfirmDialog = ref(false)

const pageTitle = computed(() => trip.value?.name)
usePageTitle(pageTitle)

const showMediaModal = ref(false)
const selectedMedia = ref({})

const currentUserWasOnTrip = computed(() => {
  if (!trip.value || !appStore.user?.id) return false
  return trip.value.participants.some((participant) => participant.id === appStore.user.id)
})

const heroImage = computed(() => {
  if (trip.value?.media && trip.value.media.length > 0) {
    return trip.value.media[0].url
  }
  return null
})

const formatDate = (date) => {
  const parsed = moment(date)
  return parsed.isValid() ? parsed.format('ddd, D MMM YYYY') : '-'
}
const formatTime = (date) => {
  const parsed = moment(date)
  return parsed.isValid() ? parsed.format('HH:mm') : '-'
}
const formatDuration = (start, end) => {
  if (!end) return '-'
  const duration = moment.duration(moment(end).diff(moment(start)))
  const hours = Math.floor(duration.asHours())
  const minutes = duration.minutes()
  return `${hours}h ${minutes}m`
}

const getVisibilityColor = (vis) => {
  return vis === 'public' ? 'success' : vis === 'club' ? 'primary' : 'grey'
}

const getVisibilityIcon = (vis) => {
  return vis === 'public' ? mdiEarth : vis === 'club' ? mdiAccountGroup : mdiLock
}

const confirmDelete = async () => {
  showDeleteConfirmDialog.value = false
  try {
    const response = await fetch(`/api/trips/${route.params.id}`, { method: 'DELETE', headers: { 'Accept': 'application/json' } })
    if (response.ok) {
      toast.success('Trip deleted successfully')
      router.push('/trips')
    } else {
      toast.error('Failed to delete trip')
    }
  } catch (e) {
    console.error("Failed to delete trip", e)
    toast.error('Failed to delete trip: ' + (e.message || 'Unknown error'))
  }
}

const openMedia = (item) => {
  selectedMedia.value = {
    ...item,
    trip_id: trip.value.id,
    trip_name: trip.value.name,
    photographer: item.photographer || (item.user_id ? trip.value.participants.find(p => p.id === item.user_id)?.name : null)
  }
  showMediaModal.value = true
}

onMounted(async () => {
  loading.value = true
  try {
    const response = await fetch(`/api/trips/${route.params.id}`, { headers: { 'Accept': 'application/json' } })
    if (response.status === 404) {
      error.value = "Trip not found. It may have been deleted or you may have the wrong link."
    } else if (!response.ok) {
      error.value = "Failed to load trip. Please try again later."
    } else {
      const json = await response.json()
      trip.value = json.data
    }
  } catch (e) {
    console.error("Failed to fetch trip", e)
    error.value = "An unexpected error occurred."
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.backdrop-blur {
  backdrop-filter: blur(4px);
  background-color: rgba(255, 255, 255, 0.1) !important;
}

.trip-hero-gradient {
  background: linear-gradient(160deg,
    #0d1b2a 0%,
    #1b2f45 25%,
    #162840 50%,
    #0f1f33 75%,
    #0a1628 100%
  );
  position: relative;
}

.trip-hero-gradient::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, rgba(0,0,0,0.05), rgba(0,0,0,0.75));
}

.scale-110 {
  transform: scale(1.1);
}

.transition-transform {
  transition: transform 0.3s ease-out;
}
</style>