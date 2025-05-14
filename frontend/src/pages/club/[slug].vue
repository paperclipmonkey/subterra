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
              <!-- Edit Club Button (Club Admins Only) -->
              <v-btn
                v-if="isClubAdmin"
                class="ml-2"
                color="primary"
                variant="outlined"
                @click="openEditClubModal('details')"
                size="small"
              >
                <v-icon start>mdi-pencil</v-icon> Edit Club
              </v-btn>
              <v-btn
                v-if="isClubAdmin"
                class="ml-2"
                color="info"
                variant="outlined"
                @click="openEditClubModal('pending')"
                size="small"
              >
                <v-icon start>mdi-account-clock</v-icon> Pending Requests
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
    <!-- Club Edit Modal -->
    <ClubEditModal
      v-if="club"
      v-model="showEditClubModal"
      :clubSlug="club.slug"
      :initialTab="editClubTab"
      @saved="onClubEditSaved"
    />
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
            <v-card-title>Logged Trips Heatmap</v-card-title>
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
                    <v-img :src="member.photo || '/default-avatar.png'" :alt="member.name"></v-img>
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
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { mande } from 'mande';
import VueMarkdown from 'vue-markdown-render';
import { CalendarHeatmap } from "vue3-calendar-heatmap";
import ClubEditModal from '@/components/ClubEditModal.vue';
import { useAppStore } from '@/stores/app';
import moment from 'moment';

const route = useRoute();
const router = useRouter();
const club = ref(null);
const recentTrips = ref([]);
const members = ref([]);
const heatmapData = ref([]);
const loadingError = ref(false);
const memberDataLoading = ref(true);
const isApprovedMember = ref(false);
const endDate = ref(new Date());

// Modal state
const showEditClubModal = ref(false);
const editClubTab = ref('details');

// Pinia user store
const appStore = useAppStore();
const user = computed(() => appStore.user);

// Determine if user is a club admin for this club (use is_admin property)
const isClubAdmin = computed(() => {
  if (!user.value || !club.value || !user.value.clubs) return false;
  const clubEntry = (user.value.clubs || []).find(c => c.slug === club.value.slug);
  return clubEntry && clubEntry.is_admin;
});

function openEditClubModal(tab = 'details') {
  editClubTab.value = tab;
  showEditClubModal.value = true;
}

function onClubEditSaved() {
  // Refetch club data after save
  fetchClubData();
}

async function fetchClubData() {
  const clubSlug = route.params.slug;
  loadingError.value = false;
  memberDataLoading.value = true;
  isApprovedMember.value = false;
  club.value = null;
  recentTrips.value = [];
  members.value = [];
  heatmapData.value = [];
  try {
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
      heatmapData.value = heatmapResponse || [];
      isApprovedMember.value = true;
    } catch (memberDataError) {
      isApprovedMember.value = false;
    } finally {
      memberDataLoading.value = false;
    }
  } catch (e) {
    club.value = null;
    loadingError.value = true;
    memberDataLoading.value = false;
  }
}

onMounted(async () => {
  await appStore.getUser();
  await fetchClubData();
  // Check for ?editClub=1&tab=pending in query
  const { editClub, tab } = route.query;
  if (editClub && isClubAdmin.value) {
    openEditClubModal(tab === 'pending' ? 'pending' : 'details');
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
