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
        <v-chip-group>
          <v-chip v-for="tag in profile.tags" :key="tag" outlined>{{ tag }}</v-chip>
        </v-chip-group>
      </div>
      <v-divider></v-divider>
      <div class="clubs">
        <h3>Club:</h3>
        <v-autocomplete
        label="Club"
        :items="clubs"
        item-title="name"
        item-value="name"
        v-model="profile.club"
      >
        <template v-slot:chip="{ props, item }">
          <v-chip
            v-bind="props"
            :text="item.raw.name"
          ></v-chip>
        </template>
        <template v-slot:item="{ props, item }">
          <v-list-item
            v-bind="props"
            :title="item.raw.name"
          ></v-list-item>
        </template>
        <template v-slot:no-data>
          <v-chip>
            Can't find your club? <strong> Add them!</strong>
          </v-chip>
        </template>
    </v-autocomplete>
    <v-btn @click="updateClub">Update Club</v-btn>
      </div>
      <div>
        <a href="/api/logout">Logout</a>
      </div>
    </v-card>
  </v-container>
</template>

<script setup>
import router from '@/router';
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const clubs = ref([
  {
    "name": "BEC",
    "id": 1,
  },
  {
    "name": "UBSS",
    "id": 2,
  },
  {
    "name": "WCC",
    "id": 3,
  },
  {
    "name": "SUSS",
    "id": 4,
  },
  {
    "name": "RUCC",
    "id": 5,
  },
  {
    "name": "SMCC",
    "id": 6,
  },
  {
    "name": "MCG",
    "id": 7,
  },
])

const profile = ref({
  "name": "",
  "id": 0,
  "photo": "",
  "stats": {},
  "tags": [],
  "clubs": [],
})

const updateClub = async () => {
  const response = await fetch(`/api/users/${route.params.id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      club: profile.value.club,
    }),
  })
  profile.value = (await response.json()).data

  router.push({ name: '/profile/[id]', params: { id: route.params.id } })
}

onMounted(async () => {
  const response = await fetch(`/api/users/${route.params.id}`)
  profile.value = (await response.json()).data
})
</script>
