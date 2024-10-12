<template>
  <v-container class="pa-4">
    <v-card class="profile">
      <v-card-title>
        <v-avatar size="64">
          <img :src="profile.photo" alt="Profile Photo" />
        </v-avatar>
        <div class="profile-info">
          <h2>{{ profile.name }}</h2>
          <p>ID: {{ profile.id }}</p>
        </div>
      </v-card-title>
      <v-divider></v-divider>
      <v-list two-line>
        <v-list-item>
          <v-list-item-content>
            <v-list-item-title>Trips this month</v-list-item-title>
            <v-list-item-subtitle>{{ profile.stats.tripsMonth }}</v-list-item-subtitle>
          </v-list-item-content>
        </v-list-item>
        <v-list-item>
          <v-list-item-content>
            <v-list-item-title>Total trips</v-list-item-title>
            <v-list-item-subtitle>{{ profile.stats.tripsTotal }}</v-list-item-subtitle>
          </v-list-item-content>
        </v-list-item>
        <v-list-item>
          <v-list-item-content>
            <v-list-item-title>Participants</v-list-item-title>
            <v-list-item-subtitle>{{ profile.stats.participants }}</v-list-item-subtitle>
          </v-list-item-content>
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
      <div class="clubs">
        <h3>Clubs:</h3>
        <v-chip-group>
          <v-chip v-for="club in profile.clubs" :key="club.id" outlined>{{ club.name }}</v-chip>
        </v-chip-group>
      </div>
    </v-card>
  </v-container>
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
