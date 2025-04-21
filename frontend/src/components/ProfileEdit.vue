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
      </v-card-title>
      <v-divider></v-divider>
      <div class="tags">
        <h3>Tags:</h3>
        <v-chip-group v-if="profile.tags && profile.tags.length">
          <v-chip v-for="tag in profile.tags" :key="tag" outlined>{{ tag }}</v-chip>
        </v-chip-group>
        <p v-else>No tags yet.</p>
      </div>
      <v-divider></v-divider>

      <!-- Display User's Clubs -->
      <div class="clubs pa-4">
        <h3>My Clubs:</h3>
        <v-list lines="one" v-if="profile.clubs && profile.clubs.length">
          <v-list-item
            v-for="club in profile.clubs"
            :key="club.id"
            :title="club.name"
          >
            <template v-slot:append>
              <v-chip :color="getClubStatusColor(club.status)" size="small">
                {{ club.status }}
              </v-chip>
            </template>
          </v-list-item>
        </v-list>
        <p v-else>You are not a member of any clubs yet.</p>
        <v-btn @click="openJoinClubModal" color="primary" class="mt-2">Request to Join Club</v-btn>
      </div>

      <v-divider></v-divider>
      <div class="bio pa-4">
        <h3>Bio:</h3>
        <v-textarea
          label="Bio"
          v-model="profile.bio"
          outlined
          rows="4"
        ></v-textarea>
      </div>
      <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn @click="save" color="success">Save Profile</v-btn>
      </v-card-actions>

    </v-card>

    <!-- Join Club Modal -->
    <v-dialog v-model="showJoinClubModal" persistent max-width="600px">
      <v-card>
        <v-card-title>
          <span class="text-h5">Request to Join Club</span>
        </v-card-title>
        <v-card-text>
          <p class="mb-4">Please note: You should already be an official member of the club you are requesting to join online. A club administrator will need to approve your request before you gain full access.</p>
          <v-autocomplete
            label="Select Club to Join"
            :items="availableClubs"
            item-title="name"
            item-value="id" 
            v-model="selectedClubToJoinId"
            return-object
            :loading="loadingClubs"
            clearable
          >
             <template v-slot:item="{ props, item }">
              <v-list-item
                v-bind="props"
                :title="item.raw.name"
              ></v-list-item>
            </template>
             <template v-slot:no-data>
              <v-list-item>
                <v-list-item-title>
                  No clubs available or matching your search.
                </v-list-item-title>
              </v-list-item>
            </template>
          </v-autocomplete>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="blue-darken-1" variant="text" @click="closeJoinClubModal">
            Cancel
          </v-btn>
          <v-btn color="blue-darken-1" variant="text" @click="requestToJoinClub" :disabled="!selectedClubToJoinId">
            Submit Request
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </v-container>
</template>

<script setup>
import router from '@/router';
import { ref, onMounted, computed } from 'vue' // Import computed
import { useRoute } from 'vue-router'

const route = useRoute()

const profile = ref({
  "name": "",
  "id": 0,
  "photo": "",
  "stats": {},
  "tags": [],
  "clubs": [], // Expect clubs data here from API
  "bio": "", // Added bio to initial structure
})

const allClubs = ref([]) // To store all clubs fetched from API
const loadingClubs = ref(false)
const showJoinClubModal = ref(false)
const selectedClubToJoinId = ref(null)

// Filter clubs the user can request to join (not already a member or pending)
const availableClubs = computed(() => {
  if (!profile.value.clubs || !allClubs.value) return []
  const userClubIds = profile.value.clubs.map(c => c.slug);
  return allClubs.value.filter(club => !userClubIds.includes(club.slug));
});

const save = async () => {
  try {
    const response = await fetch(`/api/users/${route.params.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json', // Good practice
      },
      body: JSON.stringify({
        bio: profile.value.bio,
      }),
    })
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    const updatedProfile = (await response.json()).data
    // Merge updated data carefully, especially if API doesn't return full profile
    profile.value.bio = updatedProfile.bio 
    // Optionally re-fetch profile to get latest club status if save affects it indirectly
    // await fetchProfile(); 
    // Consider showing a success message
    router.push({ name: '/profile/[id]', params: { id: 'me'} }) // Redirect only if necessary
  } catch (error) {
    console.error("Error saving profile:", error);
    // Show error message to user
  }
}

const fetchProfile = async () => {
  try {
    const response = await fetch(`/api/users/${route.params.id}`)
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    profile.value = (await response.json()).data
  } catch (error) {
    console.error("Error fetching profile:", error);
    // Handle error (e.g., show message, redirect)
  }
}

const fetchAllClubs = async () => {
  loadingClubs.value = true;
  try {
    const response = await fetch('/api/clubs'); // Assuming this endpoint exists
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    allClubs.value = (await response.json()).data; // Assuming API returns { data: [...] }
  } catch (error) {
    console.error("Error fetching clubs:", error);
    allClubs.value = []; // Reset on error
  } finally {
    loadingClubs.value = false;
  }
}

const openJoinClubModal = () => {
  selectedClubToJoinId.value = null; // Reset selection
  showJoinClubModal.value = true;
  // Fetch clubs if not already loaded or needs refresh
  if (allClubs.value.length === 0) {
      fetchAllClubs();
  }
}

const closeJoinClubModal = () => {
  showJoinClubModal.value = false;
}

const requestToJoinClub = async () => {
  if (!selectedClubToJoinId.value) return;

  try {
    const response = await fetch(`/api/clubs/${selectedClubToJoinId.value.slug}/join`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ club_id: selectedClubToJoinId.value.id }), // Send club ID
    });

    if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
    }

    // Success!
    closeJoinClubModal();
    // Re-fetch profile data to show the new pending request
    await fetchProfile(); 
    // Show success message to user

  } catch (error) {
    console.error("Error requesting to join club:", error);
    // Show error message to user within the modal or via a toast
  }
}

// Helper to get chip color based on club membership status
const getClubStatusColor = (status) => {
  switch (status) {
    case 'approved': return 'success';
    case 'pending': return 'warning';
    case 'rejected': return 'error';
    default: return 'grey';
  }
}

onMounted(async () => {
  await fetchProfile();
  // Fetch all clubs needed for the join request modal
  // We don't necessarily need to wait for this for the initial profile display
  fetchAllClubs(); 
})
</script>

<style scoped>
.profile {
  max-width: 800px;
  margin: auto;
}

.profile-info {
  margin-left: 16px;
}

.tags,
.clubs,
.bio {
  padding: 16px;
}

h3 {
  margin-bottom: 8px;
}

.v-chip {
  margin: 4px;
}

.v-card-actions {
    justify-content: flex-end;
}
</style>
