<template>
  <v-container>
    <v-row>
      <v-col>
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
        <v-alert v-else type="error" variant="outlined">Club not found.</v-alert>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { mande } from 'mande';
import VueMarkdown from 'vue-markdown-render'

const route = useRoute();
const club = ref(null);

onMounted(async () => {
  try {
    const api = mande(`/api/clubs/${route.params.slug}`);
    const response = await api.get();
    club.value = response.data || response;
  } catch (e) {
    club.value = null;
  }
});
</script>
