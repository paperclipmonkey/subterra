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
        <div v-if="route.params.id === 'me'" class="d-flex ga-2">
          <v-btn color="primary" @click="$router.push({name: '/profile/[id].edit', params: {id: profile.id}})">Edit</v-btn>
          <v-btn color="secondary" href="/api/me/trips/download" download="my_trips.csv">Download Trips (CSV)</v-btn>
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

      <v-divider></v-divider>
      <div class="bio pa-4">
        <h3>Bio:</h3>
        <p v-if="profile.bio">{{ profile.bio }}</p>
        <p v-else class="text-grey">No bio provided.</p>
      </div>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { mande } from 'mande'; // Import mande

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

onMounted(async () => {
  try {
    const userApi = mande(`/api/users/${route.params.id}`); // Create mande instance for the specific user
    const response = await userApi.get();
    // Assuming mande returns the data directly or within a data property
    profile.value = response.data || response; 
  } catch (error) {
    console.error(`Error fetching profile for user ${route.params.id}:`, error);
    // Optionally, add user feedback about the error
  }
})

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
<style>
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
</style>