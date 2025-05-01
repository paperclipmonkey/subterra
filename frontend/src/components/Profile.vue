<template>
  <v-container class="pa-4">
    <v-card class="profile">
      <v-card-title>
        <v-avatar size="64">
          <img :src="profile.photo" alt="Profile Photo" />
        </v-avatar>
        <div class="profile-info">
          <h2>{{ profile.name }}</h2>
        </div>
        <v-spacer></v-spacer>
        <div v-if="profile.id === user.id" class="d-flex ga-2">
          <v-btn color="primary" @click="$router.push({name: '/profile/[id].edit', params: {id: profile.id}})">Edit</v-btn>
          <v-btn color="secondary" href="/api/me/trips/download" download="my_trips.csv">Export Trips</v-btn>
          <v-btn color="error" href="/api/logout">Logout</v-btn>
        </div>
      </v-card-title>
      <v-divider></v-divider>
      <v-list class="metrics-grid">
        <v-list-item>
          <div>
            <v-list-item-title class="metric-title">Caves visited</v-list-item-title>
            <v-list-item-subtitle class="metric-subtitle">{{ formatNumber(profile.stats.caves) }}</v-list-item-subtitle>
          </div>
        </v-list-item>
        <v-list-item>
          <div>
            <v-list-item-title class="metric-title">Total trips</v-list-item-title>
            <v-list-item-subtitle class="metric-subtitle">{{ formatNumber(profile.stats.trips) }}</v-list-item-subtitle>
          </div>
        </v-list-item>
        <v-list-item>
          <div>
            <v-list-item-title class="metric-title">Time underground</v-list-item-title>
            <v-list-item-subtitle class="metric-subtitle">{{ formatDuration(profile.stats.duration) }}</v-list-item-subtitle>
          </div>
        </v-list-item>
        <v-list-item>
          <div>
            <v-list-item-title class="metric-title">Avg. Trip Duration</v-list-item-title>
            <v-list-item-subtitle class="metric-subtitle">{{ formatDuration(averageTripDurationMinutes) }}</v-list-item-subtitle>
          </div>
        </v-list-item>
      </v-list>
      <v-divider></v-divider>
      <!-- Club Membership Section -->
      <div v-if="profile.clubs" class="pa-4">
        <h3>Clubs:</h3>
        <v-chip-group>
          <template           
             v-for="club in profile.clubs"
            :key="club.id">
            <v-chip
              v-if="club.status === 'approved'"
              :to="{ name: '/club/[slug]', params: { slug: club.slug } }"
              color="primary"
              variant="outlined"
            >
              {{ club.name }}
            </v-chip>
          </template>
        </v-chip-group>
      </div>
      <v-divider v-if="profile.clubs && profile.clubs.length > 0"></v-divider>
      <!-- <div class="tags">
        <h3>Tags:</h3>
        <v-chip-group>
          <v-chip v-for="tag in profile.tags" :key="tag" outlined>{{ tag }}</v-chip>
        </v-chip-group>
      </div> -->
      <v-divider></v-divider>
      <!-- Medals Section -->
      <div v-if="medals.length > 0" class="pa-4">
        <h3>Medals Awarded:</h3>
        <div class="medals-grid">
          <div v-for="medal in medals" :key="medal.id" class="medal-item">
            <img v-if="medal.image_url" :src="medal.image_url" :title="medal.description" class="medal-img" />
            <div class="medal-label">{{ medal.name }}</div>
          </div>
        </div>
      </div>
      <v-divider v-if="medals.length > 0"></v-divider>
      <div class="bio pa-4">
        <h3>Bio:</h3>
        <p v-if="profile.bio">{{ profile.bio }}</p>
        <p v-else class="text-grey">No bio provided.</p>
      </div>
    </v-card>
    <v-card-text>
      <h3>Logged Trips Heatmap</h3>
      <calendar-heatmap
        dark-mode
        :values="heatmapData"
        :end-date="endDate"
        :range-color='["#ebedf0", "#9be9a8", "#40c463", "#30a14e", "#216e39"]'
        tooltip-unit="trips"
        :max="10"
      />
    </v-card-text>
    <v-divider></v-divider>
    <!-- Recent Trips -->
    <v-card-text>
      <h3>Recent Trips</h3>
      <v-list v-if="recentTrips.length > 0">
        <v-list-item
          v-for="trip in recentTrips"
          :key="trip.id"
          :to="`/trip/${trip.id}`"
          :title="trip.name || 'Untitled Trip'"
          :subtitle="`On ${moment(trip.start_time).format('YYYY-MM-DD')}`"
        >
        </v-list-item>
      </v-list>
      <div v-else class="text-grey">No recent trips found.</div>
    </v-card-text>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { mande } from 'mande'; // Import mande
import { CalendarHeatmap } from "vue3-calendar-heatmap";
import moment from 'moment';
import { useAppStore } from '@/stores/app'

const route = useRoute()

const profile = ref({
  "name": "",
  "id": 0,
  "photo": "",
  "stats": {},
  "tags": [],
  "bio": "",
  "clubs": [], // Add clubs array
})

const recentTrips = ref([]);
const heatmapData = ref([]);
const endDate = ref(new Date());
const medals = ref([]);
const averageTripDurationMinutes = ref(0); // Add state for average duration
let user = ref({});

onMounted(async () => {
  try {
    const userId = route.params.id;
    const userApi = mande(`/api/users/${userId}`); // Create mande instance for the specific user
    const response = await userApi.get();
    user = await useAppStore().getUser()
    profile.value = response.data || response;
    medals.value = (profile.value.medals || []);

    // Fetch recent trips, heatmap data, and average duration
    const [recentTripsResp, heatmapResp, avgDurationResp] = await Promise.all([
      mande(`/api/users/${userId}/recent-trips`).get(),
      mande(`/api/users/${userId}/activity-heatmap`).get(),
      mande(`/api/users/${userId}/average-trip-duration`).get() // Fetch average duration
    ]);
    recentTrips.value = recentTripsResp.data || recentTripsResp;
    heatmapData.value = heatmapResp || [];
    averageTripDurationMinutes.value = avgDurationResp?.average_duration_minutes || 0; // Store average duration

  } catch (error) {
    console.error(`Error fetching profile or activity for user ${route.params.id}:`, error);
    // Consider setting default/error states for fetched data
  }
})

const formatNumber = (num) => {
  return new Intl.NumberFormat().format(num || 0)
}

const formatDuration = (minutes) => {
  if (!minutes) return '0 minutes'
  if (minutes < 60) return `${minutes} minute${minutes !== 1 ? 's' : ''}`
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  return `${hours} hour${hours !== 1 ? 's' : ''} ${mins} minute${mins !== 1 ? 's' : ''}`
}
</script>

<style scoped>


.metrics-grid {
  display: grid;
  /* Adjust grid columns to fit 4 items */
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

.metric-title {
  font-weight: 500;
  margin-bottom: 4px;
}

.metric-subtitle {
  font-size: 1.25rem;
  font-weight: 700;
}

.clubs-section,
.medals-section,
.bio {
  margin-top: 24px;
}

.chip {
  margin: 4px;
}

.medals-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
  gap: 8px;
}

.medal-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.medal-img {
  width: 40px;
  height: 40px;
  object-fit: contain;
}

.medal-label {
  margin-top: 4px;
  font-size: 0.875rem;
  text-align: center;
}

.bio {
  line-height: 1.6;
}

.pa-4 {
  padding: 16px !important;
}

@media (max-width: 600px) {
  .metrics-grid {
    /* Adjust for smaller screens if needed, e.g., 2 columns */
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>