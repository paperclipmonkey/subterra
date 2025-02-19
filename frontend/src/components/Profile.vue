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
        <div v-if="route.params.id === 'me'">
          <v-btn color="primary" @click="$router.push({name: '/profile/[id].edit', params: {id: profile.id}})">Edit</v-btn>
        </div>
      </v-card-title>
      <v-divider></v-divider>
      <v-list two-line>
        <v-list-item class="metric-item">
          <div>
            <v-list-item-title class="metric-title">Total caves visited</v-list-item-title>
            <v-list-item-subtitle class="metric-subtitle">{{ profile.stats.caves }}</v-list-item-subtitle>
          </div>
        </v-list-item>
        <v-list-item class="metric-item">
          <div>
            <v-list-item-title class="metric-title">Total trips</v-list-item-title>
            <v-list-item-subtitle class="metric-subtitle">{{ profile.stats.trips }}</v-list-item-subtitle>
          </div>
        </v-list-item>
        <v-list-item class="metric-item">
          <div>
            <v-list-item-title class="metric-title">Time spent underground</v-list-item-title>
            <v-list-item-subtitle class="metric-subtitle">{{ profile.stats.duration }} minutes</v-list-item-subtitle>
          </div>
        </v-list-item>
      </v-list>
      <v-divider></v-divider>
      <div class="tags">
        <h3>Tags:</h3>
        <v-chip-group>
          <v-chip v-for="tag in profile.tags" :key="tag" outlined>{{ tag }}</v-chip>
        </v-chip-group>
      </div>
      <v-divider></v-divider>
      <div class="club">
        <h3>Club:</h3>
        <v-chip-group>
          <v-chip outlined>{{ profile.club }}</v-chip>
          <!-- <v-chip v-for="club in profile.clubb" :key="club.id" outlined>{{ club.name }}</v-chip> -->
        </v-chip-group>
      </div>
      <div>
        <a href="/api/logout">Logout</a>
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
</script>
