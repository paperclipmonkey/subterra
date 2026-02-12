<template>
  <div>
    <v-card class="mb-4">
      <v-card-title>
        <v-text-field
          v-model="internalCave.name"
          label="Cave Name"
          :rules="[v => !!v || 'Name is required']"
          required
        />
      </v-card-title>
      <v-card-text>
        <sub>Entrance information. E.g. Where to find it, where to park. Information about access.</sub>
        <div class="text-subtitle-2 mt-4 mb-1">Description</div>
        <MilkdownEditor
          v-model="internalCave.description"
          placeholder="Detailed description of the cave..."
          class="mb-4"
        />
        <div class="text-subtitle-2 mt-4 mb-1">Access Info</div>
        <MilkdownEditor
          v-model="internalCave.access_info"
          placeholder="Entrance information. E.g. Where to find it, where to park."
          class="mb-4"
        />
        <v-text-field
          v-model="internalCave.location_name"
          label="Location Name"
          :rules="[v => !!v || 'Location name is required']"
          required
        />
        <v-text-field
          v-model="internalCave.location_country"
          label="Country"
          :rules="[v => !!v || 'Country is required']"
          required
        />

        <mgl-map
          :map-style="style"
          :center="mapCenter"
          :zoom="zoom"
          height="350px"
          @load="onMapLoad"
        >
          <mgl-marker
            v-model:coordinates="coordinates"
            :draggable="true"
            color="#cc0000"
          />
          <mgl-navigation-control />
          <MglGeolocateControl />
        </mgl-map>

        <v-row>
          <v-col cols="12" md="4">
            <v-text-field
              v-model.number="internalCave.location_lat"
              label="Latitude"
              type="number"
              :rules="[v => v !== null && v !== undefined || 'Latitude is required']"
              required
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model.number="internalCave.location_lng"
              label="Longitude"
              type="number"
              :rules="[v => v !== null && v !== undefined || 'Longitude is required']"
              required
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model.number="internalCave.location_alt"
              label="Altitude (m)"
              type="number"
            />
          </v-col>
        </v-row>

        <v-text-field
          v-model="internalCave.slug"
          label="Slug"
          :placeholder="'e.g. region_cave-name'"
          :hint="'Lowercase, a-z, 0-9, _ and - only'"
          persistent-hint
        />

        <v-file-input
          v-model="heroImageFile"
          prepend-icon="mdi-camera"
          accept="image/*"
          label="Hero Image"
          chips
        />
        <v-file-input
          v-model="entranceImageFile"
          prepend-icon="mdi-camera"
          accept="image/*"
          label="Entrance Image"
          chips
        />
      </v-card-text>
    </v-card>

    <v-card class="mb-4">
      <v-card-title>Tags</v-card-title>
      <v-card-text>
        <template v-for="(groupItems, groupName) in tagsAvailable" :key="groupName">
          <template v-if="groupItems.some(item => item.assignable)">
            <h2 class="text-h6 mb-2 mt-4">{{ groupName }}</h2>
            <v-chip-group v-model="selectedTags[groupName]" column multiple>
              <template v-for="tag in groupItems" :key="tag.tag">
                <v-chip
                  v-if="tag.assignable"
                  :text="tag.tag"
                  variant="outlined"
                  :value="tag.tag"
                  filter
                />
              </template>
            </v-chip-group>
          </template>
        </template>
        <v-alert
          type="info"
          variant="tonal"
          density="compact"
          class="mt-4 text-caption"
        >
          By submitting this data, you confirm that you have the right to share this information and media, and that it does not infringe on any third-party rights.
        </v-alert>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import {
  MglMap,
  MglNavigationControl,
  MglMarker,
  MglGeolocateControl,
} from '@indoorequal/vue-maplibre-gl'
import { LngLat } from 'maplibre-gl'
import { convertFileToBase64 } from '@/utilities.js'
import MilkdownEditor from '@/components/MilkdownEditor.vue'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['update:modelValue'])

const internalCave = ref({ ...props.modelValue })
const style = 'https://api.maptiler.com/maps/topo/style.json?key=0gGMv4po9Mjrpd64A528'
const zoom = ref(internalCave.value.location_lat ? 16 : 5)
const defaultLng = -1.5
const defaultLat = 52

const getInitialLng = () => internalCave.value.location_lng || defaultLng
const getInitialLat = () => internalCave.value.location_lat || defaultLat

const mapCenter = ref([getInitialLng(), getInitialLat()])
const coordinates = ref(LngLat.convert([getInitialLng(), getInitialLat()]))
const tagsAvailable = ref({})
const selectedTags = ref({})
const heroImageFile = ref(null)
const entranceImageFile = ref(null)

// Initialize tags
const fetchTags = async () => {
  const response = await fetch('/api/tags', { headers: { 'Accept': 'application/json' } })
  tagsAvailable.value = await response.json()
  syncTagsFromModel()
}

const syncTagsFromModel = () => {
  if (!internalCave.value.tags) return

  const newSelectedTags = {}
  internalCave.value.tags.forEach(tag => {
    if (tag.type === 'cave' || !tag.type) {
      if (!newSelectedTags[tag.category]) {
        newSelectedTags[tag.category] = []
      }
      newSelectedTags[tag.category].push(tag.tag)
    }
  })
  selectedTags.value = newSelectedTags
}

// Watchers for two-way binding
watch(() => props.modelValue, (newVal) => {
  // Only update if fundamentally different to avoid recursion
  if (JSON.stringify(newVal) !== JSON.stringify(internalCave.value)) {
    internalCave.value = { ...newVal }
    if (internalCave.value.location_lng !== coordinates.value.lng || internalCave.value.location_lat !== coordinates.value.lat) {
      coordinates.value = LngLat.convert([internalCave.value.location_lng || 0, internalCave.value.location_lat || 0])
      mapCenter.value = [internalCave.value.location_lng || 0, internalCave.value.location_lat || 0]
    }
    syncTagsFromModel()
  }
}, { deep: true })

watch(internalCave, (newVal) => {
  // Only emit if fundamentally different to avoid recursion
  if (JSON.stringify(newVal) !== JSON.stringify(props.modelValue)) {
    emit('update:modelValue', newVal)
  }
}, { deep: true })

// Map logic
const roundToMetre = (val) => {
  return Math.round(val * 100000) / 100000
}

watch(coordinates, (newCoords) => {
  internalCave.value.location_lng = roundToMetre(newCoords.lng)
  internalCave.value.location_lat = roundToMetre(newCoords.lat)
})

watch(() => internalCave.value.location_lat, (newLat) => {
  if (roundToMetre(coordinates.value.lat) !== roundToMetre(newLat)) {
    coordinates.value = LngLat.convert([internalCave.value.location_lng, newLat])
  }
})

watch(() => internalCave.value.location_lng, (newLng) => {
  if (roundToMetre(coordinates.value.lng) !== roundToMetre(newLng)) {
    coordinates.value = LngLat.convert([newLng, internalCave.value.location_lat])
  }
})

// Tags update
watch(selectedTags, (newTags) => {
  internalCave.value.tags = Object.entries(newTags).reduce((acc, [category, tags]) => {
    return acc.concat(tags.map(tag => ({ category, tag, type: 'cave' })))
  }, [])
}, { deep: true })

// Image handling
watch(heroImageFile, async (file) => {
  if (file) {
    internalCave.value.hero_image = await convertFileToBase64(file)
  }
})

watch(entranceImageFile, async (file) => {
  if (file) {
    internalCave.value.entrance_image = await convertFileToBase64(file)
  }
})

const onMapLoad = (event) => {
  // Ensure map is centered on current coordinates if they exist
  if (internalCave.value.location_lng || internalCave.value.location_lat) {
    event.map.setCenter([internalCave.value.location_lng, internalCave.value.location_lat])
  }
}

onMounted(() => {
  fetchTags()
})
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";
</style>
