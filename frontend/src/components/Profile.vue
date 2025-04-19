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
      <!-- <div class="tags">
        <h3>Tags:</h3>
        <v-chip-group>
          <v-chip v-for="tag in profile.tags" :key="tag" outlined>{{ tag }}</v-chip>
        </v-chip-group>
      </div> -->
      <v-divider></v-divider>
      <div class="club">
        <h3>Club:</h3>
        <v-chip-group>
          <v-chip outlined>{{ profile.club }}</v-chip>
          <!-- <v-chip v-for="club in profile.clubb" :key="club.id" outlined>{{ club.name }}</v-chip> -->
        </v-chip-group>
      </div>
      <v-divider></v-divider>
      <div class="bio">
        <h3>Bio:</h3>
        <p>{{ profile.bio }}</p>
      </div>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const profile = ref({
  "name": "",
  "id": 0,
  "photo": "",
  "stats": {},
  "tags": [],
  "clubs": [],
})

onMounted(async () => {
  const response = await fetch(`/api/users/${route.params.id}`)
  profile.value = (await response.json()).data
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
</style>