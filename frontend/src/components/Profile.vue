<template>
  <div class="profile">
    <img :src="profile.photo" alt="Profile Photo" />
    <h1>{{ profile.name }}</h1>
    <p>ID: {{ profile.id }}</p>
    <div class="stats">
      <p>Trips this month: {{ profile.stats.tripsMonth }}</p>
      <p>Total trips: {{ profile.stats.tripsTotal }}</p>
      <p>Participants: {{ profile.stats.participants }}</p>
    </div>
    <div class="tags">
      <h3>Tags:</h3>
      <ul>
        <v-chip v-for="tag in profile.tags" :key="tag">{{ tag }}</v-chip>
      </ul>
    </div>
    <div class="clubs">
      <h3>Clubs:</h3>
      <ul>
        <v-chip v-for="club in profile.clubs" :key="club.id">{{ club.name }}</v-chip>
      </ul>
    </div>
  </div>
</template>

<script setup>

const profile = ref({
  "name": "John Doe",
  "id": 3211,
  "photo": "/profile/3211.png",
  "stats": {
    "tripsMonth": 11,
    "tripsTotal": 34,
    "participants": 5,
  },
  "tags": ['digging', 'tourist', 'conservation'],
  "clubs": [
    {
      name: "BEC",
      id: 1
    },
    {
      name: "WCC",
      id: 2
    }
  ],
})
</script>
