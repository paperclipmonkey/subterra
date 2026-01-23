<template>
   <div class="fill-height bg-grey-lighten-5">
      <v-container class="py-8 px-4" style="max-width: 1200px;">

         <!-- Profile Header Card -->
         <v-card class="rounded-xl mb-6 overflow-hidden" elevation="0" border>
            <div class="bg-gradient-primary px-6 pt-4 pb-12 pt-sm-10 pb-sm-16"></div>
            <div class="px-6 pb-6 mt-n10 mt-sm-n12 d-flex flex-column flex-sm-row align-end">

               <v-avatar size="100" class="border-lg elevation-2 bg-white flex-shrink-0 mx-auto mx-sm-0 d-sm-none">
                  <v-img :src="profile.photo || '/default-avatar.png'" cover></v-img>
               </v-avatar>
               <v-avatar size="140"
                  class="border-lg elevation-2 bg-white flex-shrink-0 mx-auto mx-sm-0 d-none d-sm-flex">
                  <v-img :src="profile.photo || '/default-avatar.png'" cover></v-img>
               </v-avatar>

               <div class="ml-sm-6 mt-4 mt-sm-0 flex-grow-1 text-center text-sm-left" style="min-width: 0;">
                  <h1 class="text-h4 font-weight-bold text-grey-darken-4 mb-1 text-truncate">{{ profile.name }}</h1>
                  <div class="d-flex align-center justify-center justify-sm-start flex-wrap gap-2">
                     <v-chip v-if="profile.clubs && profile.clubs.length > 0" color="primary" variant="flat"
                        size="small" prepend-icon="mdi-account-group-outline" class="font-weight-medium">
                        {{ profile.clubs[0].name }}
                        <span v-if="profile.clubs.length > 1" class="ml-1 opacity-70">+{{ profile.clubs.length - 1
                           }}</span>
                     </v-chip>
                     <div class="text-body-2 text-medium-emphasis text-truncate" v-if="profile.bio"
                        style="max-width: 100%;">
                        {{ profile.bio }}
                     </div>
                  </div>
               </div>

               <!-- Actions -->
               <div class="d-flex gap-1 mt-4 mt-sm-0 flex-wrap justify-center" v-if="profile.id === user.id">
                  <!-- Edit -->
                  <v-btn icon variant="text" color="grey-darken-1"
                     @click="$router.push('/profile/' + profile.id + '/edit')" v-tooltip="'Edit Profile'">
                     <v-icon>mdi-pencil</v-icon>
                  </v-btn>

                  <!-- Download -->
                  <v-btn icon variant="text" color="grey-darken-1" href="/api/me/trips/download" download="my_trips.csv"
                     v-tooltip="'Export Trips'">
                     <v-icon>mdi-download</v-icon>
                  </v-btn>

                  <!-- Logout -->
                  <v-btn icon variant="text" color="error" href="/api/logout" v-tooltip="'Logout'">
                     <v-icon>mdi-logout</v-icon>
                  </v-btn>
               </div>
            </div>
         </v-card>

         <!-- Stats Row -->
         <v-row class="mb-2">
            <v-col cols="12" md="4">
               <v-card class="py-4 px-6 rounded-xl h-100 d-flex align-center" elevation="0" border>
                  <v-avatar color="blue-lighten-5" size="56" class="mr-4">
                     <v-icon color="blue" icon="mdi-mountain" size="32"></v-icon>
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
                     <v-icon color="orange-darken-1" icon="mdi-hiking" size="32"></v-icon>
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
                     <v-icon color="purple" icon="mdi-clock-time-four-outline" size="32"></v-icon>
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
            <v-col cols="12" md="4" v-if="medals.length > 0">
               <v-card class="rounded-xl h-100" elevation="0" border>
                  <v-card-title class="d-flex align-center py-4 px-6">
                     <v-icon icon="mdi-medal-outline" color="amber-darken-2" class="mr-2"></v-icon>
                     <span class="text-h6 font-weight-bold">Trophy Case</span>
                     <v-spacer></v-spacer>
                     <v-chip size="x-small" color="amber" variant="flat">{{ medals.length }}</v-chip>
                  </v-card-title>
                  <v-divider></v-divider>
                  <v-card-text class="pa-6">
                     <div class="medals-grid">
                        <div v-for="medal in medals" :key="medal.id" class="medal-item" @click="openMedalModal(medal)"
                           v-tooltip="medal.name">
                           <img v-if="medal.image_url" :src="medal.image_url" class="medal-img" />
                           <v-icon v-else icon="mdi-medal-outline" size="32" color="grey-lighten-2"></v-icon>
                        </div>
                     </div>
                  </v-card-text>
               </v-card>
            </v-col>

            <!-- Main Content -->
            <v-col cols="12" :md="medals.length > 0 ? 8 : 12">
               <!-- Heatmap -->
               <v-card class="rounded-xl mb-6" elevation="0" border>
                  <v-card-title class="py-4 px-6 d-flex align-center">
                     <v-icon icon="mdi-fire" color="orange" class="mr-2"></v-icon>
                     <span class="text-h6 font-weight-bold">Activity Log</span>
                  </v-card-title>
                  <v-divider></v-divider>
                  <div class="pa-4 pt-6 overflow-x-auto">
                     <div class="calendar-wrapper">
                        <calendar-heatmap :values="heatmapData" :end-date="endDate"
                           :range-color='["#f3f4f6", "#d1fae5", "#34d399", "#10b981", "#059669"]' tooltip-unit="trips"
                           class="heatmap-scale" />
                     </div>
                  </div>
               </v-card>


               <!-- Clubs -->
               <v-card class="rounded-xl mb-6" elevation="0" border v-if="profile.clubs && profile.clubs.length > 0">
                  <v-card-title class="py-4 px-6 d-flex align-center">
                     <v-icon icon="mdi-account-group" color="indigo" class="mr-2"></v-icon>
                     <span class="text-h6 font-weight-bold">Clubs</span>
                  </v-card-title>
                  <v-divider></v-divider>
                  <v-list lines="one" class="py-0">
                     <template v-for="(club, index) in profile.clubs" :key="club.slug">
                        <v-divider v-if="index > 0" inset></v-divider>
                        <v-list-item :to="`/club/${club.slug}`" class="py-3 px-6 hover-bg">
                           <template v-slot:prepend>
                              <v-avatar color="indigo-lighten-5" class="mr-4">
                                 <v-icon color="indigo" icon="mdi-shield-account"></v-icon>
                              </v-avatar>
                           </template>

                           <v-list-item-title class="text-body-1 font-weight-bold">
                              {{ club.name }}
                           </v-list-item-title>

                           <template v-slot:append>
                              <v-chip v-if="club.is_admin" color="primary" size="x-small" variant="flat" class="mr-2">
                                 Admin
                              </v-chip>
                              <v-icon icon="mdi-chevron-right" color="grey-lighten-1"></v-icon>
                           </template>
                        </v-list-item>
                     </template>
                  </v-list>
               </v-card>

               <!-- Recent Trips -->
               <v-card class="rounded-xl" elevation="0" border>
                  <v-card-title class="py-4 px-6 d-flex align-center">
                     <v-icon icon="mdi-history" color="primary" class="mr-2"></v-icon>
                     <span class="text-h6 font-weight-bold">Recent Trips</span>
                     <v-spacer></v-spacer>
                     <v-btn variant="text" size="small" color="primary" to="/trips" append-icon="mdi-arrow-right">View
                        All</v-btn>
                  </v-card-title>

                  <v-list lines="two" v-if="recentTrips.length > 0" class="py-0">
                     <template v-for="(trip, index) in recentTrips" :key="trip.id">
                        <v-divider v-if="index > 0" inset></v-divider>
                        <v-list-item :to="`/trips/${trip.id}`" class="py-4 px-6 hover-bg">
                           <template v-slot:prepend>
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
                              <v-icon size="small" icon="mdi-map-marker" class="mr-1"></v-icon>
                              {{ trip.entrance?.name || 'Unknown Entrance' }}
                           </v-list-item-subtitle>

                           <template v-slot:append>
                              <v-icon icon="mdi-chevron-right" color="grey-lighten-1"></v-icon>
                           </template>
                        </v-list-item>
                     </template>
                  </v-list>
                  <div v-else class="pa-12 text-center text-medium-emphasis">
                     <v-icon icon="mdi-hiking" size="64" class="mb-4 opacity-20"></v-icon>
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
               <img v-if="selectedMedal.image_url" :src="selectedMedal.image_url" alt="Medal" class="medal-modal-img" />
            </div>
            <h3 class="text-h5 font-weight-black text-grey-darken-3 mb-2">{{ selectedMedal.name }}</h3>
            <p class="text-body-1 text-grey-darken-1 mb-6">{{ selectedMedal.description }}</p>
            <v-btn color="primary" variant="flat" block rounded="lg" size="large"
               @click="isMedalModalOpen = false">Close</v-btn>
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