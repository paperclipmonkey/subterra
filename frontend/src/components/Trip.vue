<template>
  <v-container>
    <v-row>
      <v-col cols="12" class="align-items-center">
        <v-btn icon @click="$router.go(-1)">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <template v-if="currentUserWasOnTrip">
          <v-btn class="float-right" icon @click="deleteTrip">
            <v-icon>mdi-delete</v-icon>
          </v-btn>
          <v-btn class="float-right" icon @click="$router.push({name: '/trip/[id].edit', params: {id: trip.id}})">
            <v-icon>mdi-pencil</v-icon>
          </v-btn>
        </template>
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
            <v-card>
              <v-card-actions>
                <v-list-item class="w-100">
                  <template v-slot:prepend>
                    <v-avatar
                      color="grey-darken-2"
                      :image="participant.photo"
                    ></v-avatar>
                  </template>
                  <v-list-item-title>{{ participant.name }}</v-list-item-title>
                  <v-list-item-subtitle>{{ participant.club || "-" }}</v-list-item-subtitle>

                  <!-- <template v-slot:append>
                    <div class="justify-self-end">
                      <v-icon class="me-1" icon="mdi-heart"></v-icon>
                      <span class="subheading me-2">256</span>
                      <span class="me-1">·</span>
                      <v-icon class="me-1" icon="mdi-share-variant"></v-icon>
                      <span class="subheading">45</span>
                    </div>
                  </template> -->
                </v-list-item>
              </v-card-actions>
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
            <v-row>
              <v-col
                v-for="media in trip.media"
                :key="media.filename"
                class="d-flex child-flex"
                cols="4"
              >
                  <a :href="media.url" target="_blank">
                    <img class="media" :src="media.url" alt="filename" />
                  </a>
                </v-col>
            </v-row>
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
import { useAppStore } from '@/stores/app'; 
import { computed } from 'vue';

const appStore = useAppStore()

const router = useRouter()
const route = useRoute()

const formatDate = (date) => {
  return moment(date).format('YYYY-MM-DD HH:mm')
}

const currentUserWasOnTrip = computed(()=> {
  return trip.value.participants.some((participant) => participant.id === appStore.user.id)
})

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
    participants: [],
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