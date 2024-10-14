<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <v-toolbar-title>{{ cave.name }}</v-toolbar-title>
        <v-divider>{{ cave.system.name }}</v-divider>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>{{ cave.name }}</v-card-title>
          <v-card-subtitle>Entrance</v-card-subtitle>
          <v-card-text>
            <vue-markdown :source="cave.description" />
            <p> Tags:
              <v-chip v-for="tag in cave.tags" :key="tag" class="ma-1">
                {{ tag.tag }}
              </v-chip>
            </p>            
            <p><strong>Location:</strong> <a :href='googleMapsUrl'>{{ cave.location.name }}, {{ cave.location.country }}</a></p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>{{ cave.system.name }}</v-card-title>
          <v-card-subtitle>System</v-card-subtitle>
          <v-card-text>
            <vue-markdown :source="cave.description" />
            <p>System:</p>
            <p>{{ cave.system.description }}</p>
            <p> Tags:
              <v-chip v-for="tag in cave.system.tags" :key="tag" class="ma-1">
                {{ tag.tag }}
              </v-chip>
            </p>
            <p><strong>Length:</strong> {{ Math.round(cave.length/1000) }} km</p>
            <p><strong>Depth:</strong> {{ cave.depth }} m</p>
            <p><strong>Location:</strong> <a :href='googleMapsUrl'>{{ cave.location.name }}, {{ cave.location.country }}</a></p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>System Entrances</v-card-title>
          <v-card-text>
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
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>Recent Trips</v-card-title>
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

const route = useRoute()

  const googleMapsUrl = computed(() => {
    return `https://www.google.com/maps/search/?api=1&query=${cave.value.location.lng}%2C${ cave.value.location.lat }`
  })

  const cave = ref({
    name: '',
    description: '',
    system: {
      name: '',
      description: '',
    },
    length: '',
    depth: '',
    location: {
      name: '',
      country: '',
      lat: 0,
      lng: 0,
    },
    trips: []
  })

  const fetchCave = async () => {
    const response = await fetch(`/api/caves/${route.params.id}`)
    cave.value = (await response.json()).data
  }

  onMounted(fetchCave)

  watch(
    () => route.fullPath,
    fetchCave
  )
</script>
