<template>
  <div class="fill-height bg-grey-lighten-4">
    <v-container class="pt-8 px-4" style="max-width: 1200px;">
       <div class="d-flex flex-column flex-md-row align-center">
          <!-- Avatar -->
          <v-avatar size="160" class="elevation-4 bg-white mb-4 mb-md-0">
             <v-img :src="profile.photo || '/default-avatar.png'" cover></v-img>
          </v-avatar>
          
          <!-- Name & Info -->
          <div class="ml-md-6 mb-2 text-center text-md-left">
             <h1 class="text-h4 font-weight-bold text-grey-darken-4 mt-2 mt-md-0">{{ profile.name }}</h1>
             <div class="text-subtitle-1 text-grey-darken-1 d-flex align-center justify-center justify-md-start" v-if="profile.clubs && profile.clubs.length > 0">
                <v-icon icon="mdi-account-group-outline" size="small" class="mr-1"></v-icon>
                {{ profile.clubs[0].name }} {{ profile.clubs.length > 1 ? `+${profile.clubs.length - 1} more` : '' }}
             </div>
          </div>
          
          <v-spacer></v-spacer>
          
          <!-- Action Buttons -->
          <div class="d-flex gap-2 mb-2 mt-4 mt-md-0" v-if="profile.id === user.id">
             <v-btn 
                color="primary" 
                variant="flat" 
                prepend-icon="mdi-pencil"
                @click="$router.push({name: '/profile/[id].edit', params: {id: profile.id}})"
                class="text-body-2 font-weight-bold"
             >
                Edit Profile
             </v-btn>
             <v-btn 
                variant="outlined" 
                color="grey-darken-2" 
                icon="mdi-download"
                href="/api/me/trips/download" 
                download="my_trips.csv"
                title="Export Trips"
             ></v-btn>
             <v-btn 
                variant="outlined" 
                color="error" 
                icon="mdi-logout"
                href="/api/logout"
                title="Logout"
             ></v-btn>
          </div>
       </div>

      <v-divider class="my-6"></v-divider>

      <v-row>
        <!-- Left Sidebar (Stats & Medals) -->
        <v-col cols="12" md="4" order="2" order-md="1">
           <!-- Stats Cards -->
           <v-row dense class="mb-4">
              <v-col cols="6" md="12">
                 <v-card class="py-3 px-4 rounded-lg" elevation="1">
                    <div class="text-overline text-medium-emphasis mb-1">Caves Visited</div>
                    <div class="d-flex align-center">
                       <v-icon color="primary" icon="mdi-mountain" size="large" class="mr-3"></v-icon>
                       <span class="text-h4 font-weight-bold">{{ formatNumber(profile.stats.caves) }}</span>
                    </div>
                 </v-card>
              </v-col>
              <v-col cols="6" md="12">
                 <v-card class="py-3 px-4 rounded-lg" elevation="1">
                    <div class="text-overline text-medium-emphasis mb-1">Total Trips</div>
                    <div class="d-flex align-center">
                       <v-icon color="secondary" icon="mdi-hiking" size="large" class="mr-3"></v-icon>
                       <span class="text-h4 font-weight-bold">{{ formatNumber(profile.stats.trips) }}</span>
                    </div>
                 </v-card>
              </v-col>
              <v-col cols="12">
                 <v-card class="py-3 px-4 rounded-lg" elevation="1">
                    <div class="text-overline text-medium-emphasis mb-1">Time Underground</div>
                    <div class="d-flex align-center">
                       <v-icon color="grey-darken-1" icon="mdi-clock-time-four-outline" size="large" class="mr-3"></v-icon>
                       <span class="text-h5 font-weight-medium">{{ formatDuration(profile.stats.duration) }}</span>
                    </div>
                 </v-card>
              </v-col>
           </v-row>

           <!-- Medals Trophy Case -->
           <v-card class="rounded-lg mb-6" elevation="1" v-if="medals.length > 0">
              <v-card-title class="d-flex align-center font-weight-bold">
                 <v-icon icon="mdi-medal" color="amber-darken-2" class="mr-2"></v-icon>
                 Trophy Case
              </v-card-title>
              <v-divider></v-divider>
              <v-card-text class="pt-4">
                 <div class="medals-grid">
                    <div 
                      v-for="medal in medals" 
                      :key="medal.id" 
                      class="medal-item cursor-pointer" 
                      @click="openMedalModal(medal)"
                    >
                      <img 
                        v-if="medal.image_url" 
                        :src="medal.image_url" 
                        :title="medal.description" 
                        class="medal-img" 
                      />
                      <div class="medal-label mt-1 text-caption font-weight-bold">{{ medal.name }}</div>
                    </div>
                 </div>
              </v-card-text>
           </v-card>
           
           <!-- Bio (Moved to sidebar on desktop if short, or keep here) -->
           <v-card class="rounded-lg mb-6" elevation="1">
              <v-card-title class="d-flex align-center font-weight-bold">
                 <v-icon icon="mdi-information" color="grey-darken-1" class="mr-2"></v-icon>
                 About
              </v-card-title>
              <v-divider></v-divider>
              <v-card-text class="text-body-1 text-grey-darken-1">
                 <p v-if="profile.bio" style="white-space: pre-wrap;">{{ profile.bio }}</p>
                 <p v-else class="font-italic text-grey">No bio currently.</p>
              </v-card-text>
           </v-card>
        </v-col>

        <!-- Main Content (Timeline & Activity) -->
        <v-col cols="12" md="8" order="1" order-md="2">
           <!-- Activity Heatmap -->
           <v-card class="rounded-lg mb-6" elevation="1">
              <v-card-title class="d-flex align-center font-weight-bold">
                 <v-icon icon="mdi-fire" color="orange" class="mr-2"></v-icon>
                 Activity Log
              </v-card-title>
              <v-divider></v-divider>
              <v-card-text class="pa-4">
                 <div class="calendar-wrapper">
                    <calendar-heatmap
                      :values="heatmapData"
                      :end-date="endDate"
                      :range-color='["#ebedf0", "#9be9a8", "#40c463", "#30a14e", "#216e39"]'
                      tooltip-unit="trips"
                    />
                 </div>
              </v-card-text>
           </v-card>

           <!-- Recent Trips Timeline style -->
           <v-card class="rounded-lg" elevation="1">
              <v-card-title class="d-flex align-center font-weight-bold">
                 <v-icon icon="mdi-history" color="primary" class="mr-2"></v-icon>
                 Recent Trips
              </v-card-title>
              <v-divider></v-divider>
              
              <v-list lines="two" v-if="recentTrips.length > 0" class="py-2">
                 <v-list-item
                    v-for="trip in recentTrips"
                    :key="trip.id"
                    :to="`/trip/${trip.id}`"
                    class="py-3"
                    rounded="lg"
                 >
                    <template v-slot:prepend>
                       <v-avatar color="blue-lighten-5" size="48" rounded>
                          <span class="text-h6 font-weight-bold text-blue">{{ moment(trip.start_time).format('D') }}</span>
                       </v-avatar>
                    </template>
                    
                    <v-list-item-title class="text-body-1 font-weight-bold ml-2">
                       {{ trip.name || 'Untitled Trip' }}
                    </v-list-item-title>
                    
                    <v-list-item-subtitle class="ml-2 mt-1 d-flex align-center">
                       <span class="text-primary font-weight-medium mr-2">{{ moment(trip.start_time).format('MMM YYYY') }}</span>
                       <span class="text-caption text-grey"> • {{ trip.system_name || 'System Unknown' }}</span>
                    </v-list-item-subtitle>
                    
                    <template v-slot:append>
                       <v-icon icon="mdi-chevron-right" color="grey"></v-icon>
                    </template>
                 </v-list-item>
              </v-list>
              
              <div v-else class="pa-8 text-center text-grey">
                 <v-icon icon="mdi-hiking" size="48" class="mb-2 opacity-20"></v-icon>
                 <p>No recent trips found for this caver.</p>
              </div>
           </v-card>
        </v-col>
      </v-row>
    </v-container>

    <!-- Medal Details Modal -->
    <v-dialog v-model="isMedalModalOpen" max-width="400px">
      <v-card class="rounded-lg text-center pa-2">
        <v-card-text>
          <img 
            v-if="selectedMedal.image_url" 
            :src="selectedMedal.image_url" 
            alt="Medal" 
            class="mb-4 drop-shadow-lg"
            style="width: 128px; height: 128px; object-fit: contain;"
          />
          <h3 class="text-h5 font-weight-bold mb-2">{{ selectedMedal.name }}</h3>
          <p class="text-body-1 text-grey-darken-1">{{ selectedMedal.description }}</p>
        </v-card-text>
        <v-card-actions class="justify-center">
          <v-btn color="primary" variant="text" @click="isMedalModalOpen = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
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
  "stats": { caves: 0, trips: 0, duration: 0 },
  "tags": [],
  "bio": "",
  "clubs": [], 
})

const recentTrips = ref([]);
const heatmapData = ref([]);
const endDate = ref(new Date());
const medals = ref([]);
let user = ref({});

onMounted(async () => {
  try {
    const userApi = mande(`/api/users/${route.params.id}`); 
    const response = await userApi.get();
    user.value = await useAppStore().getUser() || {} // Ensure valid object
    profile.value = response.data || response;
    // Ensure stats object exists
    if (!profile.value.stats) profile.value.stats = { caves: 0, trips: 0, duration: 0 };
    
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
  if (!minutes) return '0m'
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  // const remainingMinutes = minutes % 60
  // returning simple hours for cleaner profile stat
  return `${hours}h+`
}
</script>

<style scoped>
.text-shadow {
   text-shadow: 0 2px 4px rgba(0,0,0,0.5);
}
.border-lg {
   border: 4px solid white !important;
}

.medals-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  justify-items: center;
  align-items: start;
}

@media (max-width: 960px) {
  .medals-grid {
     grid-template-columns: repeat(3, 1fr);
  }
}
@media (max-width: 600px) {
  .medals-grid {
     grid-template-columns: repeat(4, 1fr);
     gap: 8px;
  }
}

.medal-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  transition: transform 0.2s ease;
}
.medal-item:hover {
   transform: translateY(-4px);
}

.medal-img {
  width: 64px;
  height: 64px;
  object-fit: contain;
  filter: drop-shadow(0 4px 4px rgba(0,0,0,0.1));
}

.drop-shadow-lg {
   filter: drop-shadow(0 10px 8px rgba(0,0,0,0.2));
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