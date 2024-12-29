<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.push({name: '/caves'})">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <v-btn v-if="appStore.user.is_admin" class="float-right" icon @click="$router.push({name: '/cave/[id].edit', params: {id: route.params.id}})">
          <v-icon>mdi-pencil</v-icon>
        </v-btn>
        <v-toolbar-title>{{ cave.name }}</v-toolbar-title>
        <v-divider>{{ cave.system.name }}</v-divider>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-img
            class="align-end text-white"
            height="200"
            :src="cave.hero_image"
            cover
          >
            <v-card-title>{{ cave.name }}</v-card-title>
            <v-card-subtitle>{{ cave.location_name }},  {{ cave.location_country }}</v-card-subtitle>
          </v-img>
          <v-card-text>
            <vue-markdown :source="cave.description" />
            <p> Tags:
              <v-chip v-for="tag in cave.tags" :key="tag" class="ma-1">
                {{ tag.tag }}
              </v-chip>
            </p>            
            <strong>Location:</strong> 
            <p>[{{ cave.location_lat }}, {{ cave.location_lng }}]</p>
            <p><a :href='googleMapsUrl'>{{ cave.location_name }}, {{ cave.location_country }}</a></p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>{{ cave.system.name }}</v-card-title>
          <v-card-subtitle>System</v-card-subtitle>
          <v-btn v-if="appStore.user.is_admin" class="float-right" icon @click="$router.push({name: '/cave_system/[id].edit', params: {id: cave.system.id}})">
            <v-icon>mdi-pencil</v-icon>
          </v-btn>
          <v-card-text>
            <vue-markdown :source="cave.system.description" />
            <p> Tags:
              <v-chip v-for="tag in cave.system.tags" :key="tag" class="ma-1">
                {{ tag.tag }}
              </v-chip>
            </p>
            <p><strong>Length:</strong> {{ Math.round(cave.system.length) }} m</p>
            <p><strong>Vertical Range:</strong> {{ cave.system.vertical_range }} m</p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row v-if="cave.system.caves.length > 1">
      <v-col cols="12">
        <v-card>
          <v-card-title>System Entrances</v-card-title>
          <v-card-text>
            This system has multiple entrances:
            <v-list>
              <v-list-item v-for="system_cave in cave.system.caves" :key="system_cave.id">
                <div>
                  <v-list-item-title><RouterLink :to="{name: '/cave/[id]', params: {id: system_cave.id}}">{{ system_cave.name }}</RouterLink></v-list-item-title>
                  <v-list-item-subtitle>{{ system_cave.description }}</v-list-item-subtitle>
                </div>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row v-if="cave.system.references">
      <v-col cols="12">
        <v-card>
          <v-card-title>System References</v-card-title>
          <v-card-text>
            <vue-markdown :source="cave.system.references" />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>
            Recent Trips
            <v-btn class="float-right" icon @click="$router.push({name: '/create-trip', query: {cave_id: cave.id}})">
              <v-icon>mdi-plus</v-icon>
            </v-btn>
            <template v-if="hasDone">
              <v-btn class="float-right" flat icon>
                <v-icon color="success">mdi-check</v-icon>
              </v-btn>
            </template>
            <template v-else>
              <v-btn class="float-right" icon @click="markAsDone">
                <v-icon>mdi-check</v-icon>
              </v-btn>
            </template>

          </v-card-title>

          <v-card-text>
            <v-list>
              <v-list-item v-for="trip in cave.trips" :key="trip.datetime">
                <div>
                  <v-list-item-title><RouterLink :to="{name: '/trip/[id]', params: {id: trip.id}}">{{ trip.name }}</RouterLink></v-list-item-title>
                  <v-list-item-subtitle>{{ moment(trip.end_time).fromNow() }}</v-list-item-subtitle>
                  <v-list-item-subtitle>{{ trip.description }}</v-list-item-subtitle>
                  <v-list-item-subtitle>Duration: {{ moment(trip.end_time).diff(trip.start_time, 'hours') }} hours</v-list-item-subtitle>
                  <!-- <v-list-item-subtitle>Party: {{ trip.participants }}</v-list-item-subtitle> -->
                  <v-list-item-subtitle>
                    <v-chip v-for="participant in trip.participants" :key="participant.id" class="ma-1">
                      {{ participant.name }}
                    </v-chip>
                  </v-list-item-subtitle>
                </div>
              </v-list-item>
            </v-list>
            <template v-if="cave.trips.length === 0">
              <v-alert>No trips have been recorded for this cave yet.</v-alert>
            </template>
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
import { watch } from "vue"
import { useRoute } from "vue-router"
import { useAppStore } from '@/stores/app'

const appStore = useAppStore()

const route = useRoute()

  const googleMapsUrl = computed(() => {
    return `https://www.google.com/maps/search/?api=1&query=${cave.value.location_lat}%2C${ cave.value.location_lng }`
  })

  const cave = ref({
    name: '',
    description: '',
    system: {
      name: '',
      description: '',
      length: '',
      vertical_range: '',
      caves: []
    },
    location: {
      name: '',
      country: '',
      lat: 0,
      lng: 0,
    },
    trips: []
  })

  const hasDone = computed(() => {
    return cave.value.trips.some(trip => trip.participants.some(participant => participant.email === appStore.user.email))
  })

  const fetchCave = async () => {
    const response = await fetch(`/api/caves/${route.params.id}`)
    cave.value = (await response.json()).data
  }

  const markAsDone = async () => {
    const trip = {
      // id: -1,
      name: 'Marked as Done',
      // description: '',
      // media: [],
      entrance_cave_id: cave.value.id,
      exit_cave_id: cave.value.id,
      // date: '',
      // start_time: '',
      // end_time: '',
      participants: [appStore.user.email],
      cave_system_id: cave.value.system.id,
    }

    const response = await fetch('/api/trips', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(trip)
    })
    if (response.ok) {
      console.log('trip saved')
    } else {
      console.error('failed to save trip')
    }
  }

  onMounted(fetchCave)

  watch(
    () => route.fullPath,
    fetchCave
  )
</script>
