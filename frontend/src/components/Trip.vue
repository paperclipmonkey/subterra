<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <v-toolbar-title>{{ trip.name }}</v-toolbar-title>
        <v-subheader>{{ trip.system.name }}</v-subheader>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>{{ trip.name }}</v-card-title>
          <v-card-subtitle>Entrance: {{ trip.entrance.name }}</v-card-subtitle>
          <v-card-subtitle>Exit: {{ trip.exit.name }}</v-card-subtitle>
          <v-card-subtitle>System: {{ trip.system.name }}</v-card-subtitle>
          <v-card-subtitle>Start time: {{ trip.start_time }}</v-card-subtitle>
          <v-card-subtitle>End time: {{ trip.end_time }}</v-card-subtitle>
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
          <v-card-text>
            <v-list>
              <v-list-item>
                <v-list-item-content>
                  <!-- <v-list-item-subtitle> -->
                    <v-chip v-for="participant in trip.participants" :key="participant.id" class="ma-1">
                      {{ participant.name }}
                    </v-chip>
                  <!-- </v-list-item-subtitle> -->
                </v-list-item-content>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import moment from 'moment'
import { computed } from 'vue'
import VueMarkdown from 'vue-markdown-render'

const route = useRoute()
  const trip = ref({
    depth: "",
    description: "",
    id: "",
    length: "",
    location: "",
    name: "",
    system: {},
    entrance: {
      location: {},
    },
    exit: {
      location: {},
    },
    trips: [],
  })
  onMounted(async () => {
    const response = await fetch(`/api/trips/${route.params.id}`)
    trip.value = (await response.json()).data
  })
</script>
