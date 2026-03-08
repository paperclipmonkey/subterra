<template>
  <div class="fill-height bg-grey-lighten-5">
    <v-container v-if="loading" class="fill-height d-flex justify-center align-center">
      <v-progress-circular indeterminate color="primary" />
    </v-container>

    <v-container v-else-if="error" class="fill-height d-flex flex-column justify-center align-center text-center">
      <v-icon :icon="mdiAlertCircleOutline" size="64" color="grey" class="mb-4" />
      <h2 class="text-h5 text-grey-darken-1 mb-2">Oops!</h2>
      <p class="text-body-1 text-grey mb-6">{{ error }}</p>
      <v-btn color="primary" variant="flat" to="/" :prepend-icon="mdiArrowLeft">
        Back to Home
      </v-btn>
    </v-container>

    <v-container v-else class="py-8 px-4" style="max-width: 1200px;">

      <!-- Profile Header Card -->
      <v-card class="rounded-xl mb-6 overflow-hidden" elevation="0" border>
        <div class="bg-gradient-primary px-6 pt-4 pb-12 pt-sm-10 pb-sm-16" />
        <div class="px-6 pb-6 mt-n10 mt-sm-n12 d-flex flex-column flex-sm-row align-center align-sm-end">

          <v-avatar size="100" class="border-lg elevation-2 bg-white flex-shrink-0 mx-auto mx-sm-0 d-sm-none">
            <v-img :src="profile.photo || '/default-avatar.png'" cover />
          </v-avatar>
          <v-avatar size="140"
                    class="border-lg elevation-2 bg-white flex-shrink-0 mx-auto mx-sm-0 d-none d-sm-flex">
            <v-img :src="profile.photo || '/default-avatar.png'" cover />
          </v-avatar>

          <div class="ml-sm-6 mt-4 mt-sm-0 flex-grow-1 text-center text-sm-left" style="min-width: 0;">
            <h1 class="text-h5 text-sm-h4 font-weight-bold text-grey-darken-4 mb-1">{{ profile.name }}</h1>
            <div class="d-flex align-center justify-center justify-sm-start flex-wrap gap-2">
              <v-chip v-if="profile.clubs && profile.clubs.length > 0" color="primary" variant="flat"
                      size="small" :prepend-icon="mdiAccountGroupOutline" class="font-weight-medium">
                {{ profile.clubs[0].name }}
                <span v-if="profile.clubs.length > 1" class="ml-1 opacity-70">+{{ profile.clubs.length - 1
                }}</span>
              </v-chip>
              <div v-if="profile.bio" class="text-body-2 text-medium-emphasis"
                   style="max-width: 100%;">
                {{ profile.bio }}
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div v-if="profile.id === user.id" class="d-flex gap-1 mt-4 mt-sm-0 flex-wrap justify-center">
            <!-- Edit -->
            <v-btn v-tooltip="'Edit Profile'" icon variant="text"
                   color="grey-darken-1" @click="$router.push('/profile/' + profile.id + '/edit')">
              <v-icon :icon="mdiPencil" />
            </v-btn>

            <!-- Export CSV -->
            <v-btn v-tooltip="'Export Trips (CSV)'" icon variant="text"
                   color="grey-darken-1"
                   @click="openDownloadDialog('Export Trips (CSV)', 'Your trip history will be exported as a CSV file.', '/api/me/trips/download')">
              <v-icon :icon="mdiFileExport" />
            </v-btn>

            <!-- Download All Data (JSON) -->
            <v-btn v-tooltip="'Download My Data (JSON)'" icon variant="text"
                   color="primary"
                   @click="openDownloadDialog('Download My Data (JSON)', 'All your profile data, trips, and memberships will be exported as a JSON file.', '/api/user/export')">
              <v-icon :icon="mdiDatabaseExport" />
            </v-btn>

            <!-- Logout -->
            <v-btn v-tooltip="'Logout'" icon variant="text" color="error" @click="appStore.logout()">
              <v-icon :icon="mdiLogout" />
            </v-btn>
          </div>
        </div>
      </v-card>

      <!-- Stats Row -->
      <v-row class="mb-2">
        <v-col cols="12" md="4">
          <v-card class="py-4 px-6 rounded-xl h-100 d-flex align-center" elevation="0" border>
            <v-avatar color="blue-lighten-5" size="56" class="mr-4">
              <v-icon color="blue" :icon="mdiFlashlight" size="32" />
            </v-avatar>
            <div>
              <div class="text-h4 font-weight-bold text-grey-darken-4">{{ formatNumber(profile.stats.caves) }}
              </div>
              <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis letter-spacing-1">
                Caves Visited</div>
            </div>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card class="py-4 px-6 rounded-xl h-100 d-flex align-center" elevation="0" border>
            <v-avatar color="orange-lighten-5" size="56" class="mr-4">
              <v-icon color="orange-darken-1" :icon="mdiHiking" size="32" />
            </v-avatar>
            <div>
              <div class="text-h4 font-weight-bold text-grey-darken-4">{{ formatNumber(profile.stats.trips) }}
              </div>
              <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis letter-spacing-1">
                Total Trips</div>
            </div>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card class="py-4 px-6 rounded-xl h-100 d-flex align-center" elevation="0" border>
            <v-avatar color="purple-lighten-5" size="56" class="mr-4">
              <v-icon color="purple" :icon="mdiClockTimeFourOutline" size="32" />
            </v-avatar>
            <div>
              <div class="text-h4 font-weight-bold text-grey-darken-4">{{ formatDuration(profile.stats.duration)
              }}</div>
              <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis letter-spacing-1">
                Underground</div>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <!-- Medals -->
        <v-col v-if="medals.length > 0 || profile.id === user.id" cols="12" md="4">
          <v-card class="rounded-xl h-100" elevation="0" border>
            <v-card-title class="d-flex align-center py-4 px-6">
              <v-icon :icon="mdiMedalOutline" color="amber-darken-2" class="mr-2" />
              <span class="text-h6 font-weight-bold">Trophy Case</span>
              <v-spacer />
              <v-chip size="x-small" color="amber" variant="flat">{{ medals.length }}</v-chip>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-6">
              <div v-if="medals.length > 0" class="medals-grid">
                <div v-for="medal in medals" :key="medal.id" v-tooltip="medal.name" class="medal-item"
                     @click="openMedalModal(medal)">
                  <img v-if="medal.image_url" :src="medal.image_url" class="medal-img">
                  <v-icon v-else :icon="mdiMedalOutline" size="32" color="grey-lighten-2" />
                </div>
              </div>
              <div v-else-if="profile.id === user.id" class="text-center py-4">
                <v-icon :icon="mdiMedalOutline" size="48" color="grey-lighten-1" class="mb-3" />
                <div class="text-subtitle-1 font-weight-bold mb-2">Start your collection!</div>
                <p class="text-body-2 text-medium-emphasis mb-4">
                  You can earn medals by completing specific trips and exploring new caves.
                </p>
                <v-btn color="primary" variant="tonal" size="small" to="/caves" :prepend-icon="mdiMagnify">
                  Find a cave
                </v-btn>
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Main Content -->
        <v-col cols="12" :md="(medals.length > 0 || profile.id === user.id) ? 8 : 12">
          <!-- Heatmap -->
          <v-card class="rounded-xl mb-6" elevation="0" border>
            <v-card-title class="py-4 px-6 d-flex align-center">
              <v-icon :icon="mdiFire" color="orange" class="mr-2" />
              <span class="text-h6 font-weight-bold">Activity Log</span>
            </v-card-title>
            <v-divider />
            <div class="pa-4 pt-6 overflow-x-auto">
              <div class="calendar-wrapper">
                <calendar-heatmap :values="heatmapData" :end-date="endDate"
                                  :range-color="['#f3f4f6', '#d1fae5', '#34d399', '#10b981', '#059669']" tooltip-unit="hours"
                                  class="heatmap-scale" />
              </div>
            </div>
          </v-card>


          <!-- Clubs -->
          <v-card v-if="profile.clubs && profile.clubs.length > 0" class="rounded-xl mb-6" elevation="0" border>
            <v-card-title class="py-4 px-6 d-flex align-center">
              <v-icon :icon="mdiAccountGroup" color="indigo" class="mr-2" />
              <span class="text-h6 font-weight-bold">Clubs</span>
            </v-card-title>
            <v-divider />
            <v-list lines="one" class="py-0">
              <template v-for="(club, index) in profile.clubs" :key="club.slug">
                <v-divider v-if="index > 0" inset />
                <v-list-item :to="`/club/${club.slug}`" class="py-3 px-6 hover-bg">
                  <template #prepend>
                    <v-avatar color="indigo-lighten-5" class="mr-4">
                      <v-icon color="indigo" :icon="mdiShieldAccount" />
                    </v-avatar>
                  </template>

                  <v-list-item-title class="text-body-1 font-weight-bold">
                    {{ club.name }}
                  </v-list-item-title>

                  <template #append>
                    <v-chip v-if="club.is_admin" color="primary" size="x-small" variant="flat" class="mr-2">
                      Admin
                    </v-chip>
                    <v-icon :icon="mdiChevronRight" color="grey-lighten-1" />
                  </template>
                </v-list-item>
              </template>
            </v-list>
          </v-card>

          <!-- Recent Trips -->
          <v-card class="rounded-xl" elevation="0" border>
            <v-card-title class="py-4 px-6 d-flex align-center">
              <v-icon :icon="mdiHistory" color="primary" class="mr-2" />
              <span class="text-h6 font-weight-bold">Recent Trips</span>
              <v-spacer />
              <v-btn variant="text" size="small" color="primary" :to="`/trips?user_id=${profile.id}`" :append-icon="mdiArrowRight">View
                All</v-btn>
            </v-card-title>

            <v-list v-if="recentTrips.length > 0" lines="two" class="py-0">
              <template v-for="(trip, index) in recentTrips" :key="trip.id">
                <v-divider v-if="index > 0" inset />
                <v-list-item :to="`/trips/${trip.id}`" class="py-4 px-6 hover-bg">
                  <template #prepend>
                    <div
                      class="d-flex flex-column align-center justify-center bg-blue-lighten-5 rounded-lg pa-2 mr-4"
                      style="width: 50px; height: 50px;">
                      <div class="text-caption text-blue font-weight-bold text-uppercase"
                           style="line-height: 1;">{{ formatTripDateMonth(trip.start_time) }}</div>
                      <div class="text-h6 text-blue-darken-2 font-weight-black" style="line-height: 1;">{{
                        formatTripDateDay(trip.start_time) }}</div>
                    </div>
                  </template>

                  <v-list-item-title class="text-body-1 font-weight-bold mb-1">
                    {{ trip.name || 'Untitled Trip' }}
                  </v-list-item-title>

                  <v-list-item-subtitle class="d-flex align-center text-body-2">
                    <v-icon size="small" :icon="mdiMapMarker" class="mr-1" />
                    {{ trip.entrance?.name || 'Unknown Entrance' }}
                  </v-list-item-subtitle>

                  <template #append>
                    <v-icon :icon="mdiChevronRight" color="grey-lighten-1" />
                  </template>
                </v-list-item>
              </template>
            </v-list>
            <div v-else class="pa-12 text-center text-medium-emphasis">
              <v-icon :icon="mdiHiking" size="64" class="mb-4 opacity-20" />
              <div class="text-h6 font-weight-regular">No recent trips</div>
            </div>
          </v-card>
        </v-col>
      </v-row>
    </v-container>

    <!-- Medal Details Modal -->
    <v-dialog v-model="isMedalModalOpen" max-width="360" content-class="medal-dialog">
      <v-card class="rounded-xl text-center pa-6">
        <div class="medal-glow mx-auto mb-6 d-flex align-center justify-center">
          <img v-if="selectedMedal.image_url" :src="selectedMedal.image_url" alt="Medal" class="medal-modal-img">
        </div>
        <h3 class="text-h5 font-weight-black text-grey-darken-3 mb-2">{{ selectedMedal.name }}</h3>
        <p class="text-body-1 text-grey-darken-1 mb-6">{{ selectedMedal.description }}</p>
        <v-btn color="primary" variant="flat" block rounded="lg" size="large"
               @click="isMedalModalOpen = false">Close</v-btn>
      </v-card>
    </v-dialog>
 
    <!-- Download Confirmation Dialog -->
    <v-dialog v-model="isDownloadDialogOpen" max-width="400">
      <v-card class="rounded-xl pa-4">
        <v-card-title class="text-h6 font-weight-bold">
          <v-icon color="primary" class="mr-2" :icon="mdiDownload" />
          {{ downloadTitle }}
        </v-card-title>
        <v-card-text class="text-body-1 py-4">
          {{ downloadDescription }}
        </v-card-text>
        <v-card-actions class="pt-2">
          <v-spacer />
          <v-btn variant="text" color="grey-darken-1" @click="isDownloadDialogOpen = false">Cancel</v-btn>
          <v-btn variant="flat" color="primary" class="px-6" @click="confirmDownload">Download</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { mdiAccountGroup, mdiAccountGroupOutline, mdiAlertCircleOutline, mdiArrowLeft, mdiArrowRight, mdiChevronRight, mdiClockTimeFourOutline, mdiDatabaseExport, mdiDownload, mdiFileExport, mdiFire, mdiFlashlight, mdiHiking, mdiHistory, mdiLogout, mdiMagnify, mdiMapMarker, mdiMedalOutline, mdiPencil, mdiShieldAccount } from '@mdi/js'
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { mande } from 'mande'
import { CalendarHeatmap } from "vue3-calendar-heatmap"
import moment from 'moment'
import { useAppStore } from '@/stores/app'
import { usePageTitle } from '@/composables/usePageTitle'

const appStore = useAppStore()

const route = useRoute()

const profile = ref({
  "name": "",
  "id": 0,
  "photo": "",
  "stats": { caves: 0, trips: 0, duration: 0 },
  "bio": "",
  "clubs": [],
})

const pageTitle = computed(() => profile.value?.name)
usePageTitle(pageTitle)

const loading = ref(true)
const error = ref(null)

const recentTrips = ref([])
const heatmapData = ref([])
const endDate = ref(new Date())
const medals = ref([])
let user = ref({})

// Download Dialog State
const isDownloadDialogOpen = ref(false)
const downloadTitle = ref('')
const downloadDescription = ref('')
const downloadLink = ref('')

const openDownloadDialog = (title, description, link) => {
  downloadTitle.value = title
  downloadDescription.value = description
  downloadLink.value = link
  isDownloadDialogOpen.value = true
}

const confirmDownload = () => {
  window.location.href = downloadLink.value
  isDownloadDialogOpen.value = false
}

const loadProfile = async (id) => {
  loading.value = true
  error.value = null
  try {
    const userApi = mande(`/api/users/${id}`)
    const response = await userApi.get()
    user.value = await useAppStore().getUser() || {} // Ensure valid object
    profile.value = response.data || response
    // Ensure stats object exists
    if (!profile.value.stats) profile.value.stats = { caves: 0, trips: 0, duration: 0 }

    medals.value = (profile.value.medals || [])

    // Fetch recent trips and heatmap data
    // Use Promise.allSettled to ensure one failure doesn't break the whole page
    const [recentTripsResult, heatmapResult] = await Promise.allSettled([
      mande(`/api/users/${id}/recent-trips`).get(),
      mande(`/api/users/${id}/activity-heatmap`).get()
    ])

    if (recentTripsResult.status === 'fulfilled') {
      recentTrips.value = recentTripsResult.value.data || recentTripsResult.value
    } else {
      console.warn(`Failed to load recent trips for user ${id}:`, recentTripsResult.reason)
      recentTrips.value = []
    }

    if (heatmapResult.status === 'fulfilled') {
      heatmapData.value = heatmapResult.value || []
    } else {
      console.warn(`Failed to load heatmap for user ${id}:`, heatmapResult.reason)
      heatmapData.value = []
    }

  } catch (err) {
    console.error(`Error fetching profile for user ${id}:`, err)
    if (err.response && err.response.status === 404) {
      error.value = "User not found. It may have been deleted or you may have the wrong link."
    } else {
      error.value = "Failed to load profile. Please try again later."
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadProfile(route.params.id)
})

watch(() => route.params.id, (newId) => {
  if (newId) {
    loadProfile(newId)
  }
})

const openMedalModal = (medal) => {
  selectedMedal.value = medal
  isMedalModalOpen.value = true
}
const selectedMedal = ref({})
const isMedalModalOpen = ref(false)

const formatTripDateMonth = (date) => {
  const parsed = moment(date)
  return parsed.isValid() ? parsed.format('MMM') : '-'
}

const formatTripDateDay = (date) => {
  const parsed = moment(date)
  return parsed.isValid() ? parsed.format('D') : '-'
}

const formatNumber = (num) => {
  return new Intl.NumberFormat().format(num || 0)
}

const formatDuration = (minutes) => {
  if (!minutes) return '0m'
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  // const remainingMinutes = minutes % 60
  // returning simple hours for cleaner profile stat
  return `${hours}h+`
}
</script>

<style scoped>
.bg-gradient-primary {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
  height: 60px;
  width: 100%;
}

@media (min-width: 600px) {
  .bg-gradient-primary {
    height: 100px;
  }
}

.border-lg {
  border: 4px solid white !important;
}

.medals-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
  gap: 16px;
  justify-items: center;
}

.medal-item {
  width: 100%;
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  padding: 8px;
  transition: all 0.2s ease;
  cursor: pointer;
}

.medal-item:hover {
  background-color: rgb(var(--v-theme-grey-lighten-4));
  transform: translateY(-2px);
}

.medal-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.15));
}

.letter-spacing-1 {
  letter-spacing: 1px;
}

.hover-bg {
  transition: background-color 0.2s;
}

.hover-bg:hover {
  background-color: rgb(var(--v-theme-grey-lighten-5));
}

.medal-glow {
  width: 140px;
  height: 140px;
  background: radial-gradient(circle, rgba(var(--v-theme-primary), 0.1) 0%, transparent 70%);
  border-radius: 50%;
}

.medal-modal-img {
  width: 120px;
  height: 120px;
  object-fit: contain;
  filter: drop-shadow(0 10px 15px rgba(0, 0, 0, 0.2));
}

.calendar-wrapper {
  width: 100%;
  overflow: hidden;
}

/* Force heatmap to scale */
.calendar-wrapper :deep(svg) {
  width: 100%;
  height: auto;
}
</style>