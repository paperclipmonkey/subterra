<template>
  <v-container>
    <v-row>
      <v-col cols="12" class="d-flex align-items-center">
        <v-btn icon @click="$router.go(-1)">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <div>
          <v-toolbar-title>{{ trip.name }}</v-toolbar-title>
          <span>{{ trip.system.name }}</span>
        </div>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>{{ trip.name }}</v-card-title>
          <v-card-subtitle>
            Entrance: <router-link :to="{name: '/cave/[id]', params: {id: trip.entrance.id}}"> {{ trip.entrance.name }} </router-link>
          </v-card-subtitle>
          <v-card-subtitle v-if="trip.entrance.id !== trip.exit.id">
            Exit: <router-link :to="{name: '/cave/[id]', params: {id: trip.exit.id}}">{{ trip.exit.name }}</router-link>
          </v-card-subtitle>
          <v-card-subtitle>System: {{ trip.system.name }}</v-card-subtitle>
          <v-card-subtitle>Start time: {{ formatDate(trip.start_time) }}</v-card-subtitle>
          <v-card-subtitle>End time: {{ formatDate(trip.end_time) }}</v-card-subtitle>
          <v-card-subtitle>Duration: {{ moment(trip.end_time).diff(trip.start_time, 'hours')}} hours</v-card-subtitle>
          <v-card-text>
            <vue-markdown :source="trip.description" />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>Participants</v-card-title>
          <v-col cols="12" md="6" v-for="participant in trip.participants" :key="participant.id">
            <v-card
              :prepend-avatar="participant.photo"
              class="mx-auto"
              :subtitle="participant.club"
              :title="participant.name"
            >
            </v-card>
          </v-col>
        </v-card>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>Media</v-card-title>
          <v-card-text>
            <v-list>
              <v-list-item>
                <div>
                  <!-- <v-list-item-subtitle> -->
                  <img class="media" v-for="media in trip.media" :key="media.filename" :src="media.url" alt="filename" />
                  <!-- </v-list-item-subtitle> -->
                </div>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>Actions</v-card-title>
          <v-card-text>
            <v-btn color="primary" @click="$router.push({name: '/trip/[id].edit', params: {id: trip.id}})">Edit</v-btn>
            <v-btn color="error" @click="deleteTrip">Delete</v-btn>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import moment from 'moment'
import VueMarkdown from 'vue-markdown-render'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()

const formatDate = (date) => {
  return moment(date).format('YYYY-MM-DD HH:mm')
}

const deleteTrip = async () => {
  const response = await fetch(`/api/trips/${route.params.id}`, {
    method: 'DELETE',
  })
  if (response.ok) {
    router.push({ name: '/trips' })
  }
}

  const trip = ref({
    description: "",
    id: "",
    name: "",
    media: [],
    system: {},
    entrance: {
      location: {},
    },
    exit: {
      location: {},
    },
  })

  onMounted(async () => {
    const response = await fetch(`/api/trips/${route.params.id}`)
    trip.value = (await response.json()).data
  })
</script>

<style scoped>
 .media {
   max-width: 100%;
   max-height: 100%;
 }
</style>