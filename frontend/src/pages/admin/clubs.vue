<template>
  <v-container>
    <v-row>
      <v-col>
        <h1>Club Administration</h1>
        <v-row justify="space-between" class="mb-4">
          <v-col cols="12" md="6">
            <v-text-field
              v-model="search"
              append-inner-icon="mdi-magnify"
              label="Search Clubs (Name, Location)"
              single-line
              hide-details
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="auto" class="d-flex align-center justify-end">
             <v-btn color="primary" @click="openCreateDialog">
               <v-icon start>mdi-plus</v-icon> Create Club
             </v-btn>
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="clubs"
          :loading="loading"
          :search="search"
          :items-per-page="1000"
          :sort-by="[{ key: 'name', order: 'asc' }]"
          hide-default-footer
          class="elevation-1"
          item-value="id"
          @click:row="handleRowClick"
        >
          <template v-slot:item.is_active="{ item }">
            <v-btn
              icon
              variant="text"
              size="small"
              @click.stop="toggleEnabled(item)"
              :loading="item.loadingEnabled"
              :color="item.is_active ? 'green' : 'red'"
            >
              <v-icon>
                {{ item.is_active ? 'mdi-check-circle' : 'mdi-close-circle' }}
              </v-icon>
              <v-tooltip activator="parent" location="top">{{ item.is_active ? 'Disable Club' : 'Enable Club' }}</v-tooltip>
            </v-btn>
          </template>
           <template v-slot:item.website="{ item }">
             <a :href="item.website" target="_blank" @click.stop>{{ item.website }}</a>
           </template>
           <template v-slot:item.description="{ item }">
             <div class="text-truncate" style="max-width: 200px;" :title="item.description">{{ item.description }}</div>
           </template>
           <!-- Action column removed -->
        </v-data-table>
      </v-col>
    </v-row>

    <CreateClubDialog v-model="createDialogVisible" @clubCreated="handleClubCreated" />

  </v-container>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'; // Added watch
import { mande } from 'mande'; // Ensure mande is imported
import { useRouter } from 'vue-router';
import CreateClubDialog from '@/components/admin/CreateClubDialog.vue'; // Import the dialog component

// --- API Setup ---
const clubsApi = mande('/api/admin/clubs');

// --- Data Fetching ---
const clubs = ref([]);
const loading = ref(false);
const search = ref('');
const router = useRouter();
const createDialogVisible = ref(false); // State for dialog visibility

const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Slug', key: 'slug', sortable: true },
  { title: 'Description', key: 'description', sortable: false, width: '250px' }, // Give description more space
  { title: 'Website', key: 'website', sortable: false },
  { title: 'Location', key: 'location', sortable: true },
  { title: 'Members', key: 'member_count', sortable: true, align: 'center' },
  { title: 'Enabled', key: 'is_active', sortable: true, align: 'center' },
];

const fetchClubs = async () => {
  loading.value = true;
  try {
    // Use actual API
    const response = await clubsApi.get();
    // Adjust based on actual API response structure (e.g., response.data if nested)
    clubs.value = (response.data || response).map(club => ({
      ...club,
      loadingEnabled: false, // Initialize loading flag for UI
    }));
  } catch (error) {
    console.error('Error fetching clubs:', error);
    // Handle error display (e.g., snackbar)
  } finally {
    loading.value = false;
  }
};

const openCreateDialog = () => {
  createDialogVisible.value = true;
};

const handleClubCreated = (newClub) => {
  // Add the new club to the list or refetch
  // For simplicity, refetching the list to include the new club
  fetchClubs();
  // Alternatively, push to the existing list if the newClub object is complete
  // clubs.value.push({ ...newClub, loadingEnabled: false });
};

const updateClubInList = (updatedClub) => {
  // Ensure the updatedClub from the API response is used
  const clubData = updatedClub.data || updatedClub;
  const index = clubs.value.findIndex(c => c.id === clubData.id);
  if (index !== -1) {
    clubs.value[index] = {
      ...clubData,
      loadingEnabled: false, // Reset loading flag
    };
  } else {
     clubs.value.push({
       ...clubData,
       loadingEnabled: false,
     });
  }
};

const toggleEnabled = async (club) => {
  club.loadingEnabled = true;
  try {
    // Use slug instead of id
    const toggleApi = mande(`/api/admin/clubs/${club.slug}/toggle-active`);
    const updatedClub = await toggleApi.put();
    updateClubInList(updatedClub); // Update list with response data
  } catch (error) {
    console.error(`Error toggling enabled status for club ${club.slug}:`, error);
    club.loadingEnabled = false; // Reset loading on error
  }
  // No finally needed if updateClubInList handles the flag on success
};

const handleRowClick = (event, { item }) => {
  // Prevent navigation if clicking interactive elements
  let target = event.target;
  while (target && target !== event.currentTarget) {
    if (
      target.tagName === 'BUTTON' ||
      target.closest('button') ||
      target.classList.contains('v-icon') ||
      target.tagName === 'A'
    ) {
      return;
    }
    target = target.parentNode;
  }
  // Navigate to the club's public page using its slug
  router.push({ name: '/club/[slug]', params: { slug: item.slug } });
};

onMounted(() => {
  fetchClubs();
});

</script>

<style scoped>
.v-data-table :deep(tbody tr) {
  cursor: pointer; /* Keep pointer cursor for row click to edit */
}
</style>
