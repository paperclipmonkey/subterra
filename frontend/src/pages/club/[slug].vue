<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <!-- Club Info Card -->
        <v-card v-if="club">
          <v-card-title>
            <h1 class="text-wrap">{{ club.name }}</h1>
          </v-card-title>
          <v-card-subtitle v-if="club.location">
            <v-icon start :icon="mdiMapMarker" /> {{ club.location }}
          </v-card-subtitle>
          <v-card-text>
            <div v-if="club.description">
              <MarkdownRenderer :source="club.description" />
            </div>
            <div class="mt-4">
              <v-chip v-if="club.website" color="primary" variant="outlined" :href="club.website" target="_blank">
                <v-icon start :icon="mdiWeb" /> Website
              </v-chip>
              <v-chip class="ml-2" color="info" variant="outlined">
                <v-icon start :icon="mdiAccountGroup" /> {{ club.member_count }} members
              </v-chip>
              <!-- Edit Club Button (Club Admins Only) -->
              <v-btn v-if="isClubAdmin" class="ml-2" color="primary" variant="outlined"
                     size="small" @click="openEditClubModal('details')">
                <v-icon start :icon="mdiPencil" /> Edit Club
              </v-btn>
              <v-btn v-if="isClubAdmin" class="ml-2" color="info" variant="outlined"
                     size="small" @click="openEditClubModal('pending')">
                <v-icon start :icon="mdiAccountClock" /> Pending Requests <span
                  v-if="club.pending_users_count > 0">({{ club.pending_users_count }})</span>
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
        <!-- Club Edit Modal -->
        <ClubEditModal v-if="club && isClubAdmin" v-model="showEditClubModal" :club-slug="club.slug" :initial-tab="editClubTab"
                       @saved="onClubEditSaved" />
        <!-- Loading/Error State for Club Info -->
        <v-container v-else-if="error" class="text-center mt-6">
          <v-icon :icon="mdiAlertCircleOutline" size="64" color="grey" class="mb-4" />
          <h2 class="text-h5 text-grey-darken-1 mb-2">Oops!</h2>
          <p class="text-body-1 text-grey mb-6">{{ error }}</p>
          <v-btn color="primary" variant="flat" to="/" :prepend-icon="mdiArrowLeft">
            Back to Home
          </v-btn>
        </v-container>
        <v-progress-circular v-else indeterminate color="primary" />
      </v-col>
    </v-row>

    <!-- Member Specific Content Wrapper -->
    <template v-if="club && !error"> <!-- Only render this section if club loaded -->
      <!-- Loading State for Member Data -->
      <v-row v-if="memberDataLoading">
        <v-col cols="12" class="text-center py-5">
          <v-progress-circular indeterminate color="primary" size="64" />
          <p class="mt-3">Loading club activity...</p>
        </v-col>
      </v-row>

      <!-- Member Content (Heatmap, Trips, Members) -->
      <v-row v-else-if="isApprovedMember">
        <!-- Activity Heatmap -->
        <v-col cols="12">
          <v-card>
            <v-card-title>Hours Underground Heatmap</v-card-title>
            <v-card-text>
              <calendar-heatmap dark-mode :values="heatmapData" :end-date="endDate"
                                :range-color="['#ebedf0', '#9be9a8', '#40c463', '#30a14e', '#216e39']" tooltip-unit="hours" />
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Recent Trips -->
        <v-col cols="12" md="8">
          <v-card v-if="club && club.huts && club.huts.length > 0" class="mb-4">
            <v-card-title>Club Huts</v-card-title>
            <v-list density="compact">
              <v-list-item
                v-for="hut in club.huts"
                :key="hut.id"
                :to="`/huts/${hut.id}`"
                :title="hut.name"
              >
                <template #prepend>
                  <v-avatar size="small" rounded="0">
                    <v-img :src="hut.image_url || '/default-hut.jpg'" cover />
                  </v-avatar>
                </template>
              </v-list-item>
            </v-list>
          </v-card>

          <v-card>
            <v-card-title>Recent Trips</v-card-title>
            <v-list v-if="recentTrips.length > 0">
              <v-list-item v-for="trip in recentTrips" :key="trip.id" :to="`/trips/${trip.id}`"
                           :title="trip.name || 'Untitled Trip'" :subtitle="`On ${formatTripDate(trip.start_time)}`" />
            </v-list>
            <v-card-text v-else>No recent trips found.</v-card-text>
          </v-card>
        </v-col>

        <!-- Members List -->
        <v-col cols="12" md="4">
          <v-card>
            <v-card-title>Members</v-card-title>
            <v-list v-if="members.length > 0">
              <v-list-item v-for="member in members" :key="member.id" :to="`/profile/${member.id}`"
                           :title="member.name">
                <template #prepend>
                  <v-avatar size="small" class="mr-2">
                    <v-img :src="member.photo || '/default-avatar.png'" :alt="member.name" />
                  </v-avatar>
                </template>
              </v-list-item>
            </v-list>
            <v-card-text v-else>No members found.</v-card-text>
          </v-card>
        </v-col>

      </v-row>

      <!-- Not a Member Message -->
      <v-row v-else>
        <v-col cols="12">
          <v-alert type="info" variant="tonal">
            You must be an approved member to see club activity and member details.
          </v-alert>
        </v-col>
      </v-row>
    </template>

  </v-container>
</template>

<script setup>
import { mdiAccountClock, mdiAccountGroup, mdiAlertCircleOutline, mdiArrowLeft, mdiMapMarker, mdiPencil, mdiWeb } from '@mdi/js'
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '@/plugins/api'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import { CalendarHeatmap } from "vue3-calendar-heatmap"
import ClubEditModal from '@/components/ClubEditModal.vue'
import { useAppStore } from '@/stores/app'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const club = ref(null)
const recentTrips = ref([])
const members = ref([])
const heatmapData = ref([])
const error = ref(null)
const memberDataLoading = ref(true)
const isApprovedMember = ref(false)
const endDate = ref(new Date())

// Modal state
const showEditClubModal = ref(false)
const editClubTab = ref('details')

// Pinia user store
const appStore = useAppStore()
const user = computed(() => appStore.user)

// Determine if user is a club admin for this club (use is_admin property)
const isClubAdmin = computed(() => {
  if (!user.value || !club.value || !user.value.clubs) return false
  const clubEntry = (user.value.clubs || []).find(c => c.slug === club.value.slug)
  return (clubEntry && clubEntry.is_admin) || user.value.is_admin
})

function openEditClubModal(tab = 'details') {
  editClubTab.value = tab
  showEditClubModal.value = true
}

function onClubEditSaved() {
  // Refetch club data after save
  fetchClubData()
}

function formatTripDate(date) {
  const parsed = moment(date)
  return parsed.isValid() ? parsed.format('YYYY-MM-DD') : '-'
}

async function fetchClubData() {
  const clubSlug = route.params.slug
  error.value = null
  memberDataLoading.value = true
  isApprovedMember.value = false
  recentTrips.value = []
  members.value = []
  heatmapData.value = []
  try {
    const clubResponse = await api.get(`/api/clubs/${clubSlug}`)
    club.value = clubResponse.data.data || clubResponse.data
    // Attempt to load member-specific data ONLY if club loaded
    try {
      const [tripsResponse, membersResponse, heatmapResponse] = await Promise.all([
        api.get(`/api/clubs/${clubSlug}/recent-trips`),
        api.get(`/api/clubs/${clubSlug}/members`),
        api.get(`/api/clubs/${clubSlug}/activity-heatmap`)
      ])
      recentTrips.value = tripsResponse.data.data || tripsResponse.data
      members.value = membersResponse.data.data || membersResponse.data
      heatmapData.value = heatmapResponse.data || []
      isApprovedMember.value = true
    } catch (memberDataError) {
      isApprovedMember.value = false
    } finally {
      memberDataLoading.value = false
    }
  } catch (e) {
    club.value = null
    if (e.response && e.response.status === 404) {
      error.value = "Club not found. It may have been deleted or you may have the wrong link."
    } else {
      error.value = "Failed to load club. Please try again later."
    }
    memberDataLoading.value = false
  }
}

onMounted(async () => {
  await appStore.getUser()
  await fetchClubData()
  // Check for ?editClub=1&tab=pending in query
  const { editClub, tab } = route.query
  if (editClub && isClubAdmin.value) {
    openEditClubModal(tab === 'pending' ? 'pending' : 'details')
  }
})
</script>

<style scoped>
.v-card {
  margin-bottom: 1rem;
}

/* Ensure heatmap container allows responsiveness if needed */
.v-card-text>div[style*="width"] {
  max-width: 100%;
  overflow-x: auto;
}
</style>
<style>
.vch__tooltip {
  /* Example: Customize tooltip appearance */
  background-color: #333;
  color: white;
  padding: 5px 10px;
  border-radius: 4px;
  font-size: 0.8rem;
  z-index: 1000;
}
</style>
