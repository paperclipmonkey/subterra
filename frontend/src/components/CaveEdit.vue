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
    <v-form @submit.prevent="saveCave">
    <v-row>
      <v-col cols="12">
          <v-card>
            <v-card-title>
              <v-text-field v-model="cave.name" label="Cave Name" required></v-text-field>
            </v-card-title>
            <v-card-text>
              <sub>Entrance information. E.g. Where to find it, where to park. Information about access.</sub>
              <v-textarea v-model="cave.description" label="Description" required></v-textarea>
              <v-text-field v-model="cave.location_name" label="Location Name" required></v-text-field>
              <v-text-field v-model="cave.location_country" label="Country" required></v-text-field>

              <mgl-map
                :map-style="style"
                :center="initmapCentre"
                :zoom="zoom"
                height="350px"
              >
                <mgl-marker :draggable="true" v-model:coordinates="coordinates" color="#cc0000" />
                <mgl-navigation-control />
                <MglGeolocateControl/>
              </mgl-map>
              <v-text-field v-model="cave.location_lat" label="Latitude" required></v-text-field>
              <v-text-field v-model="cave.location_lng" label="Longitude" required></v-text-field>
              <v-file-input
                prepend-icon="mdi-camera"
                accept="image/*"
                label="Hero Image"
                v-model="cave.hero_image"
                chips
              ></v-file-input>
              <v-file-input
                prepend-icon="mdi-camera"
                accept="image/*"
                label="Entrance Image"
                v-model="cave.entrance_image"
                chips
              ></v-file-input>
            </v-card-text>
          </v-card>
      </v-col>
    </v-row>

    <v-row>
      <v-col>
        <v-card>
            <v-card-title>Tags</v-card-title>
            <v-card-text>
          <template v-for="(groupItems, groupName) in tagsAvailable">

            <template v-if="groupItems.some(item => item.assignable)">
              <h2 class="text-h6 mb-2 tagGroupTitle">{{groupName}}</h2>

              <v-chip-group
                v-model="selectedTags[groupName]"
                column
                :multiple="true"
              >
                <template v-for="tag in groupItems">
                  <v-chip
                    v-if="tag.assignable"
                    :text="tag.tag"
                    variant="outlined"
                    :value="tag.tag"
                    filter
                  ></v-chip>
                </template>
              </v-chip-group>
            </template>
          </template>
        </v-card-text>
        <v-divider class="mt-2"></v-divider>
          </v-card>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>System</v-card-title>
          <v-card-subtitle>{{ cave.system.name }}</v-card-subtitle>
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

    <v-row>
      <v-col>
        <v-card-text>
          <v-btn type="submit" color="primary">Save</v-btn>
        </v-card-text>
      </v-col>
    </v-row>
  </v-form>

  </v-container>
</template>

<script setup>
import VueMarkdown from 'vue-markdown-render'
import { computed, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import { convertFileToBase64 } from '@/utilities.js'

import {
  MglMap,
  MglNavigationControl,
  MglMarker,
  MglGeolocateControl,
} from '@indoorequal/vue-maplibre-gl';
import { LngLat } from 'maplibre-gl';

const style = 'https://api.maptiler.com/maps/outdoor-v2/style.json?key=0gGMv4po9Mjrpd64A528';
const zoom = 16;


const router = useRouter()
const route = useRoute()

const selectedTags = ref({})

const cave = ref({
  name: '',
  description: '',
  hero_image: '',
  entrance_image: '',
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
  trips: [],
  location_lat: 0,
  location_lng: 0,
})

const tagsAvailable = ref({});

const initmapCentre = ref([0,0])


const coordinates = ref(LngLat.convert([cave.value.location_lng, cave.value.location_lat]));

watch(coordinates, (newCoordinates) => {
  cave.value.location_lng = roundToMetre(newCoordinates['lng']);
  cave.value.location_lat = roundToMetre(newCoordinates['lat']);
});

// https://en.wikipedia.org/wiki/Decimal_degrees
const roundToMetre = (val) => {
  return Math.round(val * 100000) / 100000 // round to 5 decimal places
}

const fetchCave = async () => {
  const response = await fetch(`/api/caves/${route.params.id}`)
  cave.value = (await response.json()).data

  coordinates.value = LngLat.convert([cave.value.location_lng, cave.value.location_lat])
  initmapCentre.value = [cave.value.location_lng, cave.value.location_lat]

  selectedTags.value = cave.value.tags.reduce((acc, tag) => {
    if(tag.type !== 'cave') { // Only show cave tags
      return acc
    }
    if (!acc[tag.category]) {
      acc[tag.category] = []
    }
    acc[tag.category].push(tag.tag)
    return acc
  }, {})

  const tagsresponse = await fetch('/api/tags');
  tagsAvailable.value = (await tagsresponse.json())
  
  // TODO: remove tags that aren't related to a cave
}

const saveCave = async () => {
  // Re-add tags to cave
  cave.value.tags = Object.entries(selectedTags.value).reduce((acc, [category, tags]) => {
    return acc.concat(tags.map(tag => ({ category, tag })))
  }, [])

  if(cave.value.hero_image instanceof File) {
    cave.value.hero_image = await convertFileToBase64(cave.value.hero_image);
  }

  if(cave.value.entrance_image instanceof File) {
    cave.value.entrance_image = await convertFileToBase64(cave.value.entrance_image);
  }

  const response = await fetch(`/api/caves/${route.params.id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(cave.value),
  })
  if (response.ok) {
    router.push({ name: '/cave/[id]', params: { id: cave.value.slug } })
  }
}

onMounted(fetchCave)

watch(
  () => route.fullPath,
  fetchCave
)
</script>
<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";
</style>