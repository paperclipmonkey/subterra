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
            <div 
            v-for="medal in medals" 
            :key="medal.id" 
            class="medal-item" 
            @click="openMedalModal(medal)"
          >
            <img 
              v-if="medal.image_url" 
              :src="medal.image_url" 
              :title="medal.description" 
              class="medal-img" 
            />
            <div class="medal-label">{{ medal.name }}</div>
          </div>
        </div>

        <v-dialog v-model="isMedalModalOpen" max-width="500px">
          <v-card>
            <v-card-text>
              <img 
          v-if="selectedMedal.image_url" 
          :src="selectedMedal.image_url" 
          alt="Medal Image" 
          class="medal-img" 
          style="width: 100%; height: auto; margin-bottom: 16px;"
              />
                <p style="text-align: center;">{{ selectedMedal.description }}</p>
            </v-card-text>
            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn color="primary" text @click="isMedalModalOpen = false">Close</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
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
let user = ref({});

onMounted(async () => {
  try {
    const userApi = mande(`/api/users/${route.params.id}`); // Create mande instance for the specific user
    const response = await userApi.get();
    user = await useAppStore().getUser()
    profile.value = response.data || response;
    medals.value = (profile.value.medals || []);

    // Fetch recent trips and heatmap data
    const [recentTripsResp, heatmapResp] = await Promise.all([
      mande(`/api/users/${route.params.id}/recent-trips`).get(),
      mande(`/api/users/${route.params.id}/activity-heatmap`).get()
    ]);
    recentTrips.value = recentTripsResp.data || recentTripsResp;
    heatmapData.value = heatmapResp || [];
  } catch (error) {
    console.error(`Error fetching profile or activity for user ${route.params.id}:`, error);
  }
})

const openMedalModal = (medal) => {
  selectedMedal.value = medal;
  isMedalModalOpen.value = true;
}
const selectedMedal = ref({});
const isMedalModalOpen = ref(false);

const formatNumber = (num) => {
  return new Intl.NumberFormat().format(num || 0)
}

const formatDuration = (minutes) => {
  if (!minutes) return '0 minutes'
  if (minutes < 60) return `${minutes} minutes`
  const hours = Math.floor(minutes / 60)
  const remainingMinutes = minutes % 60
  return `${hours}h ${remainingMinutes}m`
}
</script>

<style scoped>
.v-card {
  margin-bottom: 1rem;
}
.v-card-text > div[style*="width"] {
  max-width: 100%;
  overflow-x: auto;
}
</style>
<style>
.vch__tooltip {
  background-color: #333;
  color: white;
  padding: 5px 10px;
  border-radius: 4px;
  font-size: 0.8rem;
  z-index: 1000;
}
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.metric-title {
  font-weight: bold;
}

.metric-subtitle {
  font-size: 1.2em;
}

.bio p {
  white-space: pre-wrap; /* Preserve line breaks in bio */
}

.medals-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  justify-items: center;
  align-items: start;
}
.medal-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.medal-img {
  width: 96px;
  height: 96px;
  object-fit: contain;
  margin-bottom: 8px;
  transition: transform 0.2s cubic-bezier(0.4,0,0.2,1);
  filter: drop-shadow(0px 0px 6px #eee);
}
.medal-item:hover .medal-img {
  transform: scale(1.1);
  z-index: 2;
}
.medal-label {
  font-size: 0.95em;
  font-weight: 500;
  word-break: break-word;
}
@media (max-width: 600px) {
  .medals-grid {
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
  }
  .medal-img {
    width: 48px;
    height: 48px;
  }
}
</style>