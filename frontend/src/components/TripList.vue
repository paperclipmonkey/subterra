<template>
  <v-container class="pa-0 d-flex flex-column">
    <!-- Sticky Header Area -->
    <div class="sticky-header bg-background pt-2 pb-2 px-2 z-index-10 flex-shrink-0">
      <div class="d-flex align-center justify-space-between mb-2">
        <h1 class="text-h5 font-weight-bold ml-1">{{ tripsUser ? `${tripsUser.name}'s Trips` :
          (route.query.user_id ? 'User Trips' : 'My Trips') }}</h1>
        <v-menu>
          <template #activator="{ props }">
            <v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" />
          </template>
          <v-list>
            <v-list-item to="/create-trip" prepend-icon="mdi-plus">
              <v-list-item-title>Log Trip</v-list-item-title>
            </v-list-item>
            <v-list-item href="/api/me/trips/download" download="my_trips.csv" prepend-icon="mdi-download">
              <v-list-item-title>Download Trips</v-list-item-title>
            </v-list-item>
          </v-list>
        </v-menu>
      </div>

      <v-text-field v-model="search" placeholder="Search trips..." prepend-inner-icon="mdi-magnify" variant="outlined"
                    hide-details density="comfortable" bg-color="surface" class="rounded-lg mb-2" />

      <!-- Only show tabs when viewing own trips and not searching -->
      <v-tabs v-if="isOwnTrips && !isSearching" v-model="tab" color="primary" density="compact">
        <v-tab value="detailed">Trips</v-tab>
        <v-tab value="stubbed">Marked as Done</v-tab>
      </v-tabs>
    </div>

    <template v-if="tripStore.loading">
      <div class="d-flex justify-center my-8 flex-grow-1">
        <v-progress-circular indeterminate color="primary" size="48" />
      </div>
    </template>

    <template v-else>
      <!-- Unified search results (searching across both collections) -->
      <template v-if="isSearching">
        <div v-if="filteredTrips.length === 0" class="text-center py-8">
          <v-icon size="64" color="grey lighten-10" icon="mdi-magnify" class="mb-4" />
          <h3 class="text-h6 font-weight-medium text-grey-darken-1 mb-2">No results found</h3>
          <p class="text-body-2 text-grey-darken-1">Try a different search term.</p>
        </div>
        <div v-else class="px-3 pb-16 pt-2">
          <v-row>
            <v-col v-for="trip in filteredTrips" :key="trip.id" cols="12" sm="6" md="4">
              <trip-card :trip="trip" :format-date="formatDate" :format-duration="formatDuration" :get-gradient-class="getGradientClass" />
            </v-col>
          </v-row>
        </div>
      </template>

      <!-- Normal tabbed view -->
      <v-window v-else v-model="tab" class="flex-grow-1">
        <v-window-item value="detailed">
          <div v-if="detailedFilteredTrips.length === 0" class="text-center py-8">
            <v-icon size="64" color="grey lighten-10" icon="mdi-compass-outline" class="mb-4" />
            <h3 class="text-h6 font-weight-medium text-grey-darken-1 mb-2">No trips found</h3>
            <p class="text-body-2 text-grey-darken-1 mb-6">Try adjusting your search or log a new trip.</p>
            <div class="d-flex justify-center ga-4">
              <v-btn color="primary" to="/caves" prepend-icon="mdi-map-search">Find Caves</v-btn>
              <v-btn variant="outlined" color="primary" to="/create-trip" prepend-icon="mdi-plus">Log Trip</v-btn>
            </div>
          </div>

          <div v-else class="px-3 pb-16 pt-2">
            <v-row>
              <v-col v-for="trip in detailedFilteredTrips" :key="trip.id" cols="12" sm="6" md="4">
                <trip-card :trip="trip" :format-date="formatDate" :format-duration="formatDuration" :get-gradient-class="getGradientClass" />
              </v-col>
            </v-row>
          </div>
        </v-window-item>

        <v-window-item v-if="isOwnTrips" value="stubbed">
          <!-- Explanatory banner -->
          <v-alert type="info" variant="tonal" density="compact" class="mx-3 mt-3 mb-2" icon="mdi-information-outline">
            These are caves you've quickly marked as visited. Tap any entry to add details and turn it into a full trip report.
          </v-alert>

          <div v-if="stubbedFilteredTrips.length === 0" class="text-center py-8">
            <p class="text-body-2 text-grey">No marked caves yet.</p>
          </div>
          <v-list v-else bg-color="transparent" class="px-2">
            <v-list-item v-for="trip in stubbedFilteredTrips" :key="trip.id" :to="`/trips/${trip.id}`" class="mb-2 rounded-lg elevation-1 bg-surface" lines="two">
              <template #prepend>
                <v-avatar color="primary" variant="tonal" class="mr-2">
                  <v-icon icon="mdi-check" />
                </v-avatar>
              </template>
              <v-list-item-title class="font-weight-medium">
                {{ trip.entrance?.name || 'Unknown Entrance' }}
              </v-list-item-title>
              <v-list-item-subtitle>
                {{ formatDate(trip.start_time) }} • {{ trip.participants?.length || 0 }} participants
              </v-list-item-subtitle>
              <template #append>
                <v-icon icon="mdi-chevron-right" color="grey-lighten-1" />
              </template>
            </v-list-item>
          </v-list>
        </v-window-item>
      </v-window>
    </template>
  </v-container>
</template>

<script setup>
import { useRoute } from 'vue-router'
import { mande } from 'mande'
import moment from 'moment'
import { useAppStore } from '@/stores/app'
import { useTripStore } from '@/stores/trips'
import { ref, computed, onMounted, watch } from 'vue'
import TripCard from '@/components/TripCard.vue'

const store = useAppStore()
const tripStore = useTripStore()
const search = ref('')
const tab = ref('detailed')

const isStubbed = (trip) => {
  return trip.name === 'Marked as Done'
}

const isOwnTrips = computed(() => {
  if (!tripsUser.value || !store.user) return true
  return String(tripsUser.value.id) === String(store.user.id)
})

const isSearching = computed(() => {
  return search.value.trim().length > 0
})

const filteredTrips = computed(() => {
  let trips = tripStore.trips

  // When viewing another user's trips, exclude stubbed
  if (!isOwnTrips.value) {
    trips = trips.filter(trip => !isStubbed(trip))
  }

  if (search.value.trim()) {
    const query = search.value.toLowerCase().trim()
    trips = trips.filter(trip => {
      if (trip.name?.toLowerCase().includes(query)) return true
      if (trip.entrance?.name?.toLowerCase().includes(query)) return true
      if (trip.participants?.some(p => p.name?.toLowerCase().includes(query))) return true
      return false
    })
  }
  return trips
})

const detailedFilteredTrips = computed(() => {
  return filteredTrips.value.filter(trip => !isStubbed(trip))
})

const stubbedFilteredTrips = computed(() => {
  return filteredTrips.value.filter(trip => isStubbed(trip))
})

const formatDate = (date) => {
  let parsedDate = moment(date)
  return parsedDate.isValid() ? parsedDate.format('DD-MM-YYYY') : '~'
}

const formatDuration = (minutes) => {
  if (!minutes) return ''
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  if (hours > 0) {
    if (mins > 0) return `${hours}h ${mins}m`
    return `${hours}h`
  }
  return `${mins}m`
}

const getGradientClass = (id) => {
  const gradients = ['gradient-0', 'gradient-1', 'gradient-2', 'gradient-3', 'gradient-4']
  const index = (typeof id === 'number' ? id : 0) % gradients.length
  return gradients[index]
}

const route = useRoute()
const tripsUser = ref(null)

const loadTrips = async () => {
  tripStore.loading = true
  await store.getUser()

  const query = { ...route.query }

  if (query.user_id) {
    try {
      const userApi = mande(`/api/users/${query.user_id}`)
      const response = await userApi.get()
      tripsUser.value = response.data ?? response
    } catch (e) {
      console.error('Failed to load user', e)
    }
  } else {
    query.user_id = store.user?.id
    tripsUser.value = store.user
  }

  await tripStore.getTrips(query)
}

onMounted(loadTrips)
watch(() => route.query, loadTrips, { deep: true })
</script>

<style scoped>
.sticky-header {
  position: sticky;
  top: 0;
  z-index: 10;
  background-color: rgb(var(--v-theme-background));
}

/* Fallback if variable doesn't work */
.bg-background {
  background-color: #f7f7f7;
}

.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
