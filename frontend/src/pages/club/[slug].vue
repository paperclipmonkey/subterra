<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <!-- Club Info Card -->
        <v-card v-if="club">
          <v-card-title>
            <h1>{{ club.name }}</h1>
          </v-card-title>
          <v-card-subtitle v-if="club.location">
            <v-icon start>mdi-map-marker</v-icon> {{ club.location }}
          </v-card-subtitle>
          <v-card-text>
            <div v-if="club.description">
              <vue-markdown :source="club.description" />
            </div>
            <div class="mt-4">
              <v-chip v-if="club.website" color="primary" variant="outlined" :href="club.website" target="_blank">
                <v-icon start>mdi-web</v-icon> Website
              </v-chip>
              <v-chip class="ml-2" color="info" variant="outlined">
                <v-icon start>mdi-account-group</v-icon> {{ club.member_count }} members
              </v-chip>
            </div>
          </v-card-text>
        </v-card>
        <!-- Loading/Error State for Club Info -->
        <v-alert v-else-if="loadingError" type="error" variant="outlined">Club not found or could not be loaded.</v-alert>
        <v-progress-circular v-else indeterminate color="primary"></v-progress-circular>
      </v-col>
    </v-row>

    <!-- Member Specific Content Wrapper -->
    <template v-if="club && !loadingError"> <!-- Only render this section if club loaded -->
      <!-- Loading State for Member Data -->
      <v-row v-if="memberDataLoading">
        <v-col cols="12" class="text-center py-5">
          <v-progress-circular indeterminate color="primary" size="64"></v-progress-circular>
          <p class="mt-3">Loading club activity...</p>
        </v-col>
      </v-row>

      <!-- Member Content (Heatmap, Trips, Members) -->
      <v-row v-else-if="isApprovedMember">
        <!-- Activity Heatmap -->
        <v-col cols="12">
          <v-card>
            <v-card-title>Activity Heatmap</v-card-title>
            <v-card-text>
              <calendar-heatmap
                dark-mode
                :values="heatmapData"
                :end-date="endDate"
                :range-color='["#ebedf0", "#9be9a8", "#40c463", "#30a14e", "#216e39"]'
                tooltip-unit="trips"
                :max="10"

              />
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Recent Trips -->
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Recent Trips</v-card-title>
            <v-list v-if="recentTrips.length > 0">
              <v-list-item
                v-for="trip in recentTrips"
                :key="trip.id"
                :to="`/trip/${trip.id}`"
                :title="trip.name || 'Untitled Trip'"
                :subtitle="`On ${moment(trip.start_time).format('YYYY-MM-DD')}`"
              >
                <template v-slot:prepend>
                  <v-icon>mdi-hiking</v-icon>
                </template>
              </v-list-item>
            </v-list>
            <v-card-text v-else>No recent trips found.</v-card-text>
          </v-card>
        </v-col>

        <!-- Members List -->
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Members</v-card-title>
            <v-list v-if="members.length > 0">
              <v-list-item
                v-for="member in members"
                :key="member.id"
                :to="`/profile/${member.id}`"
                :title="member.name"
              >
                <template v-slot:prepend>
                  <v-avatar size="small" class="mr-2">
                    <!-- TODO: Add a default avatar image to public folder -->
                    <v-img :src="member.profile_photo_url || '/default-avatar.png'" :alt="member.name"></v-img>
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
            <!-- TODO: Add join/request access button if applicable -->
          </v-alert>
        </v-col>
      </v-row>
    </template>

  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'; // Removed computed as it wasn't strictly needed
import { useRoute } from 'vue-router';
import { mande } from 'mande';
import VueMarkdown from 'vue-markdown-render';
import { CalendarHeatmap } from "vue3-calendar-heatmap";
// import { useAuthStore } from '@/stores/auth'; // Assuming you have an auth store

import moment from 'moment';
const route = useRoute();
// const authStore = useAuthStore(); // Keep authStore if needed for user ID or other checks
const club = ref(null);
const recentTrips = ref([]);
const members = ref([]);
const heatmapData = ref([]); // Initialize as empty object for heatmap
const loadingError = ref(false); // Error loading basic club info
const memberDataLoading = ref(true); // Loading state for member-specific data
const isApprovedMember = ref(false); // Is the current user an approved member?
const endDate = ref(new Date()); // For heatmap

onMounted(async () => {
  const clubSlug = route.params.slug;
  loadingError.value = false;
  memberDataLoading.value = true; // Start loading member data
  isApprovedMember.value = false;
  club.value = null;
  // Reset data arrays
  recentTrips.value = [];
  members.value = [];
  heatmapData.value = [];

  try {
    // Fetch basic club details first
    const clubApi = mande(`/api/clubs/${clubSlug}`);
    const clubResponse = await clubApi.get();
    club.value = clubResponse.data || clubResponse;

    // Attempt to load member-specific data ONLY if club loaded
    try {
      const dataApi = mande(`/api/clubs/${clubSlug}`);
      const [tripsResponse, membersResponse, heatmapResponse] = await Promise.all([
        dataApi.get('recent-trips'),
        dataApi.get('members'),
        dataApi.get('activity-heatmap')
      ]);

      recentTrips.value = tripsResponse.data || tripsResponse;
      members.value = membersResponse.data || membersResponse;
      heatmapData.value = heatmapResponse || []; // Ensure it's an object
      isApprovedMember.value = true; // Successfully loaded member data

    } catch (memberDataError) {
      console.warn("Could not load member-specific club data (likely not an approved member):", memberDataError);
      isApprovedMember.value = false; // Failed to load member data
      // No need to set loadingError = true unless the *initial* club fetch failed
    } finally {
        memberDataLoading.value = false; // Finished attempting to load member data
    }

  } catch (e) {
    console.error("Error loading club:", e);
    club.value = null;
    loadingError.value = true; // Set error for the main club loading
    memberDataLoading.value = false; // Stop member loading if club failed
  }
});
</script>

<style scoped>
.v-card {
  margin-bottom: 1rem;
}
/* Ensure heatmap container allows responsiveness if needed */
.v-card-text > div[style*="width"] {
  max-width: 100%;
  overflow-x: auto;
}
</style>
<style>
/* Global styles for heatmap tooltips if needed, or scope them */
.vch__tooltip {
  /* Example: Customize tooltip appearance */
  background-color: #333;
  color: white;
  padding: 5px 10px;
  border-radius: 4px;
  font-size: 0.8rem;
  z-index: 1000; /* Ensure tooltip is above other elements */
}
</style>
