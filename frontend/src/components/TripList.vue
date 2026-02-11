  <template>
    <v-container class="pa-0 d-flex flex-column">
      <!-- Sticky Header Area -->
      <div class="sticky-header bg-background pt-2 pb-2 px-2 z-index-10 flex-shrink-0">
        <div class="d-flex align-center justify-space-between mb-2">
          <h1 class="text-h5 font-weight-bold ml-1">{{ tripsUser ? `${tripsUser.name}'s Trips` : (route.query.user_id ? 'User Trips' : 'My Trips') }}</h1>
          <v-menu>
            <template v-slot:activator="{ props }">
              <v-btn icon="mdi-dots-vertical" variant="text" v-bind="props"></v-btn>
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
          hide-details density="comfortable" bg-color="surface" class="rounded-lg"></v-text-field>
      </div>

      <template v-if="tripStore.loading">
        <div class="d-flex justify-center my-8 flex-grow-1">
          <v-progress-circular indeterminate color="primary" size="48"></v-progress-circular>
        </div>
      </template>

      <template v-else>
        <div v-if="tripStore.trips.length === 0" class="text-center py-8">
          <v-icon size="64" color="grey lighten-10" icon="mdi-compass-outline" class="mb-4"></v-icon>
          <h3 class="text-h6 font-weight-medium text-grey-darken-1 mb-2">No trips yet</h3>
          <p class="text-body-2 text-grey-darken-1 mb-6">Start your adventure by finding a cave or logging a trip.</p>
          <div class="d-flex justify-center ga-4">
            <v-btn color="primary" to="/caves" prepend-icon="mdi-map-search">Find Caves</v-btn>
            <v-btn variant="outlined" color="primary" to="/create-trip" prepend-icon="mdi-plus">Log Trip</v-btn>
          </div>
        </div>

        <div v-else class="px-3 pb-16 flex-grow-1">
          <v-row>
            <v-col v-for="trip in filteredTrips" :key="trip.id" cols="12" sm="6" md="4">
              <v-card :to="`/trips/${trip.id}`" elevation="2" class="fill-height d-flex flex-column trip-card" hover>
                <!-- Hero Image Placeholder or Map snapshot could go here -->
                <div class="trip-card-header pa-4 pb-2">
                  <div class="d-flex justify-space-between align-start">
                    <div>
                      <div class="text-caption text-primary font-weight-bold mb-1">
                        {{ formatDate(trip.start_time) }}
                      </div>
                      <h3 class="text-h6 font-weight-bold lh-tight mb-1">
                        {{ trip.name }}
                      </h3>
                      <div class="text-body-2 text-medium-emphasis mb-2">
                        {{ trip.entrance?.name || 'Unknown Entrance' }}
                      </div>
                    </div>
                  </div>
                </div>

                <v-divider class="mt-auto"></v-divider>

                <div class="pa-3 bg-grey-lighten-5">
                  <div class="d-flex align-center flex-wrap ga-1">
                    <v-icon size="small" color="grey" icon="mdi-account-group" class="mr-1"></v-icon>
                    <span v-if="!trip.participants || trip.participants.length === 0" class="text-caption text-grey">No
                      participants</span>
                    <template v-else>
                      <v-chip v-for="(participant, i) in trip.participants.slice(0, 3)" :key="participant.id"
                        size="x-small" variant="flat" class="bg-white" border>
                        {{ participant.name }}
                      </v-chip>
                      <v-chip v-if="trip.participants.length > 3" size="x-small" variant="flat"
                        class="bg-grey-lighten-3">
                        +{{ trip.participants.length - 3 }}
                      </v-chip>
                    </template>
                  </div>
                </div>
              </v-card>
            </v-col>
          </v-row>
        </div>
      </template>
    </v-container>
  </template>

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
</style>

<script setup>
import { useRoute } from 'vue-router'
import { mande } from 'mande'
import moment from 'moment'
import { useAppStore } from '@/stores/app'
import { useTripStore } from '@/stores/trips';

const store = useAppStore()
const tripStore = useTripStore()
const search = ref('')

const filteredTrips = computed(() => {
  if (!search.value.trim()) {
    return tripStore.trips
  }

  const query = search.value.toLowerCase().trim()

  return tripStore.trips.filter(trip => {
    // Search by trip name
    if (trip.name?.toLowerCase().includes(query)) {
      return true
    }

    // Search by entrance name
    if (trip.entrance?.name?.toLowerCase().includes(query)) {
      return true
    }

    // Search by participant names
    if (trip.participants?.some(p => p.name?.toLowerCase().includes(query))) {
      return true
    }

    return false
  })
})

const formatDate = (date) => {
  let parsedDate = moment(date);
  return parsedDate.isValid() ? parsedDate.format('DD-MM-YYYY') : '~'
}

const route = useRoute()
const tripsUser = ref(null)

onMounted(async () => {
  tripStore.loading = true
  await store.getUser() // Check if user is logged in

  const query = { ...route.query }

  if (query.user_id) {
    try {
      const userApi = mande(`/api/users/${query.user_id}`)
      const response = await userApi.get()
      tripsUser.value = response.data || response
    } catch (e) {
      console.error("Failed to load user", e)
    }
  } else {
    // If no user_id in query, default to current user
    query.user_id = store.user?.id
    tripsUser.value = store.user
  }

  await tripStore.getTrips(query)
})
</script>
