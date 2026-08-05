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
              <v-chip v-if="!isIndividualMembership" class="ml-2" color="info" variant="outlined">
                <v-icon start :icon="mdiAccountGroup" /> {{ club.member_count }} members
              </v-chip>
              <v-chip
                v-for="hut in (club.huts || [])"
                :key="hut.id"
                class="ml-2"
                color="secondary"
                variant="outlined"
                :to="`/huts/${hut.id}`"
              >
                <v-icon start :icon="mdiHomeVariantOutline" /> {{ hut.name }}
              </v-chip>
              <!-- Edit Club Button (Club Admins Only) -->
              <v-btn v-if="isClubAdmin" class="ml-2" color="primary" variant="outlined"
                     size="small" @click="openEditClubModal('details')">
                <v-icon start :icon="mdiPencil" /> Edit Club
              </v-btn>
              <v-btn v-if="isClubAdmin" class="ml-2" color="info" variant="outlined"
                     size="small" @click="openEditClubModal('pending')">
                <v-icon start :icon="mdiAccountClock" /> Confirm Members <span
                  v-if="club.pending_users_count > 0">({{ club.pending_users_count }})</span>
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
        <!-- Loading/Error State for Club Info.
             Keep this chained directly to the club card's v-if above — an element
             with its own v-if in between (as the edit modal once was) silently
             re-parents the v-else-if/v-else onto *that* condition, which left
             every non-club-admin staring at the spinner forever. -->
        <v-container v-else-if="error" class="text-center mt-6">
          <v-icon :icon="mdiAlertCircleOutline" size="64" color="grey" class="mb-4" />
          <h2 class="text-h5 text-grey-darken-1 mb-2">Oops!</h2>
          <p class="text-body-1 text-grey mb-6">{{ error }}</p>
          <v-btn color="primary" variant="flat" to="/" :prepend-icon="mdiArrowLeft">
            Back to Home
          </v-btn>
        </v-container>
        <v-progress-circular v-else indeterminate color="primary" />

        <!-- Club Edit Modal -->
        <ClubEditModal v-if="club && isClubAdmin" v-model="showEditClubModal" :club-slug="club.slug" :initial-tab="editClubTab"
                       @saved="onClubEditSaved" />
      </v-col>
    </v-row>

    <!-- Member Specific Content Wrapper -->
    <!-- The Direct Individual Member catch-all club has no member roster, trips or stats. -->
    <template v-if="club && !error && !isIndividualMembership"> <!-- Only render this section if club loaded -->
      <!-- Loading State for Member Data -->
      <v-row v-if="memberDataLoading">
        <v-col cols="12" class="text-center py-5">
          <v-progress-circular indeterminate color="primary" size="64" />
          <p class="mt-3">Loading club activity...</p>
        </v-col>
      </v-row>

      <!-- Member Content (Stats, Heatmap, Trips, Photos, Members) -->
      <v-row v-else-if="isApprovedMember">
        <!-- By the numbers -->
        <v-col v-if="statCards.length > 0" cols="12">
          <v-row dense>
            <v-col v-for="stat in statCards" :key="stat.label" cols="6" md="3">
              <v-card class="h-100 stat-card" variant="flat" border :to="stat.to" :hover="!!stat.to">
                <v-card-text class="d-flex align-center ga-3 py-4">
                  <v-avatar :color="stat.color" variant="tonal" size="48" rounded="lg">
                    <v-icon :icon="stat.icon" size="24" />
                  </v-avatar>
                  <div class="stat-card__body">
                    <div class="stat-card__value" :class="stat.isText ? 'text-subtitle-1 font-weight-bold' : 'text-h4 font-weight-bold'">
                      {{ stat.value }}
                    </div>
                    <div class="text-body-2 text-medium-emphasis text-truncate">{{ stat.label }}</div>
                    <div v-if="stat.sub" class="text-caption font-weight-medium" :class="`text-${stat.color}`">{{ stat.sub }}</div>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-col>

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

        <!-- Recent Trips + Photo Wall -->
        <v-col cols="12" md="8">
          <v-card class="mb-4">
            <v-card-title class="d-flex align-center justify-space-between">
              <span>Recent Trips</span>
              <span v-if="tripsSummaryLabel" class="text-caption text-medium-emphasis font-weight-regular">{{ tripsSummaryLabel }}</span>
            </v-card-title>
            <v-card-text v-if="recentTrips.length > 0" class="pt-0">
              <CaveTripListItem v-for="trip in recentTrips" :key="trip.id" :trip="trip" />
            </v-card-text>
            <v-card-text v-else>No recent trips found.</v-card-text>
          </v-card>

          <v-card v-if="visiblePhotos.length > 0">
            <v-card-title>Recent photos</v-card-title>
            <v-card-text>
              <div class="photo-wall">
                <component
                  :is="photo.trip_id ? 'router-link' : 'div'"
                  v-for="(photo, index) in visiblePhotos"
                  :key="photo.id"
                  :to="photo.trip_id ? `/trips/${photo.trip_id}` : undefined"
                  class="photo-wall__tile"
                >
                  <v-img :src="photo.url" :srcset="photo.srcset || undefined" :alt="photo.title || 'Trip photo'" cover aspect-ratio="1" />
                  <div v-if="index === visiblePhotos.length - 1 && extraPhotoCount > 0" class="photo-wall__more">
                    +{{ extraPhotoCount }}
                  </div>
                </component>
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Right rail: Caved Alongside, Members, Huts -->
        <v-col cols="12" md="4">
          <v-card v-if="alliedClubs.length > 0" class="mb-4">
            <v-card-title>Caved Alongside</v-card-title>
            <v-card-text class="d-flex flex-wrap ga-2">
              <v-chip v-for="ac in alliedClubs" :key="ac.slug" :to="`/club/${ac.slug}`"
                      color="primary" variant="tonal" size="small">
                {{ ac.name }} · {{ ac.trip_count }}
              </v-chip>
            </v-card-text>
          </v-card>

          <v-card class="mb-4">
            <v-card-title class="d-flex align-center justify-space-between">
              <span>Members</span>
              <span v-if="members.length > 0" class="text-body-2 text-medium-emphasis font-weight-regular">{{ members.length }}</span>
            </v-card-title>
            <v-card-text v-if="members.length > 8" class="py-0">
              <v-text-field
                v-model="memberSearch"
                :prepend-inner-icon="mdiMagnify"
                placeholder="Search members"
                density="compact"
                variant="outlined"
                hide-details
                clearable
              />
            </v-card-text>
            <div v-if="members.length > 0" class="members-scroll">
              <v-list density="compact">
                <v-list-item v-for="member in filteredMembers" :key="member.id" :to="`/profile/${member.id}`"
                             :title="member.name">
                  <template #prepend>
                    <v-avatar size="small" class="mr-2">
                      <v-img :src="member.photo || '/default-avatar.png'" :alt="member.name" />
                    </v-avatar>
                  </template>
                </v-list-item>
                <v-list-item v-if="filteredMembers.length === 0" class="text-medium-emphasis"
                             title="No members match your search" />
              </v-list>
            </div>
            <v-card-text v-else>No members found.</v-card-text>
          </v-card>
        </v-col>

      </v-row>

      <!-- Not a Member Message -->
      <v-row v-else>
        <v-col cols="12">
          <v-alert type="info" variant="tonal">
            Your membership must be confirmed by the club before you can see its activity and member details.
          </v-alert>
        </v-col>
      </v-row>
    </template>

  </v-container>
</template>

<script setup>
import { mdiAccountClock, mdiAccountGroup, mdiAlertCircleOutline, mdiArrowLeft, mdiClockOutline, mdiHomeVariantOutline, mdiMagnify, mdiMapMarker, mdiMapMarkerDistance, mdiPencil, mdiTerrain, mdiTrophyOutline, mdiWeb } from '@mdi/js'
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '@/plugins/api'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import CaveTripListItem from '@/components/CaveTripListItem.vue'
import { CalendarHeatmap } from "vue3-calendar-heatmap"
import ClubEditModal from '@/components/ClubEditModal.vue'
import { useAppStore } from '@/stores/app'

const route = useRoute()
const router = useRouter()
const club = ref(null)
const recentTrips = ref([])
const members = ref([])
const memberSearch = ref('')
const heatmapData = ref([])
const summary = ref(null)
const error = ref(null)
const memberDataLoading = ref(true)
const isApprovedMember = ref(false)
const endDate = ref(new Date())

const PHOTO_LIMIT = 11

// The Direct Individual Member catch-all club hides all of its social
// features (member roster, club trips, stats) — it isn't a real club.
const isIndividualMembership = computed(() => !!club.value?.is_individual_membership)

const stats = computed(() => summary.value?.stats || null)
const alliedClubs = computed(() => summary.value?.allied_clubs || [])
const photos = computed(() => summary.value?.photos || [])
const photoCount = computed(() => summary.value?.photo_count || 0)
const visiblePhotos = computed(() => photos.value.slice(0, PHOTO_LIMIT))
const extraPhotoCount = computed(() => Math.max(0, photoCount.value - visiblePhotos.value.length))

const filteredMembers = computed(() => {
  const term = (memberSearch.value || '').trim().toLowerCase()
  if (!term) return members.value
  return members.value.filter(m => m.name?.toLowerCase().includes(term))
})

const statCards = computed(() => {
  const s = stats.value
  if (!s) return []
  const formatNumber = n => (n || 0).toLocaleString()
  return [
    { label: 'Hours underground', value: formatNumber(s.hours_underground), sub: 'last 12 months', icon: mdiClockOutline, color: 'primary' },
    {
      label: 'Trips logged',
      value: formatNumber(s.trips_logged),
      sub: s.trips_this_month > 0 ? `+${s.trips_this_month} this month` : 'last 12 months',
      icon: mdiMapMarkerDistance,
      color: 'info',
    },
    {
      label: 'Caves visited',
      value: formatNumber(s.caves_visited),
      sub: s.new_caves_this_year > 0 ? `${s.new_caves_this_year} new this year` : 'last 12 months',
      icon: mdiTerrain,
      color: 'secondary',
    },
    {
      label: 'Most active',
      value: s.most_active?.name || '—',
      sub: s.most_active ? `${s.most_active.trip_count} trip${s.most_active.trip_count === 1 ? '' : 's'}` : 'no trips yet',
      icon: mdiTrophyOutline,
      color: 'accent',
      isText: true,
      to: s.most_active ? `/profile/${s.most_active.id}` : undefined,
    },
  ]
})

const tripsSummaryLabel = computed(() => {
  const s = stats.value
  if (!s || !s.trips_logged) return ''
  return `${s.trips_logged} trip${s.trips_logged === 1 ? '' : 's'} · ${s.hours_underground.toLocaleString()} hrs underground`
})

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

async function fetchClubData() {
  const clubSlug = route.params.slug
  error.value = null
  memberDataLoading.value = true
  isApprovedMember.value = false
  recentTrips.value = []
  members.value = []
  heatmapData.value = []
  summary.value = null
  try {
    const clubResponse = await api.get(`/api/clubs/${clubSlug}`)
    club.value = clubResponse.data.data || clubResponse.data
    // The Direct Individual Member catch-all club has no member content, so
    // skip the activity/roster requests entirely.
    if (isIndividualMembership.value) {
      memberDataLoading.value = false
      return
    }
    // Attempt to load member-specific data ONLY if club loaded
    try {
      const [tripsResponse, membersResponse, heatmapResponse, summaryResponse] = await Promise.all([
        api.get(`/api/clubs/${clubSlug}/recent-trips`),
        api.get(`/api/clubs/${clubSlug}/members`),
        api.get(`/api/clubs/${clubSlug}/activity-heatmap`),
        api.get(`/api/clubs/${clubSlug}/summary`)
      ])
      recentTrips.value = tripsResponse.data.data || tripsResponse.data
      members.value = membersResponse.data.data || membersResponse.data
      heatmapData.value = heatmapResponse.data || []
      summary.value = summaryResponse.data || null
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

// The router reuses this component when navigating between clubs (e.g. via the
// "Caved Alongside" links), so onMounted won't re-fire — refetch on slug change.
watch(() => route.params.slug, (slug, prevSlug) => {
  if (slug && slug !== prevSlug) {
    club.value = null
    fetchClubData()
  }
})
</script>

<style scoped>
.v-card {
  margin-bottom: 1rem;
}

.stat-card__body {
  min-width: 0;
}

.stat-card__value {
  line-height: 1.15;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.members-scroll {
  max-height: 420px;
  overflow-y: auto;
}

.photo-wall {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

.photo-wall__tile {
  position: relative;
  display: block;
  border-radius: 6px;
  overflow: hidden;
  background-color: rgba(0, 0, 0, 0.06);
}

.photo-wall__more {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-weight: 500;
  font-size: 1rem;
}

@media (max-width: 600px) {
  .photo-wall {
    grid-template-columns: repeat(3, 1fr);
  }
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
