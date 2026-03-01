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

        <div class="text-subtitle-1 mb-2">Media</div>
        <v-row>
          <v-col cols="12" md="4">
            <v-card variant="outlined" class="pa-4 h-100 d-flex flex-column">
              <div class="text-subtitle-2 mb-2 d-flex align-center">
                <v-icon start size="small">mdi-video</v-icon>
                Hero Video
              </div>
              
              <v-hover v-slot="{ isHovering, props: hoverProps }">
                <div v-bind="hoverProps" class="position-relative mb-4 bg-grey-lighten-4 rounded overflow-hidden" style="height: 180px;">
                  <video
                    v-if="heroVideoPreview || props.modelValue.hero_video?.preview_url || props.modelValue.hero_video?.url"
                    :src="heroVideoPreview || props.modelValue.hero_video?.preview_url || props.modelValue.hero_video?.url"
                    autoplay muted loop playsinline
                    class="w-100 h-100"
                    style="object-fit: cover; border-radius: 4px;"
                  />
                  <div v-else class="d-flex flex-column align-center justify-center h-100 text-grey">
                    <v-icon size="48">mdi-video-outline</v-icon>
                    <span class="text-caption mt-2">No hero video set</span>
                  </div>
                  <v-overlay
                    :model-value="isHovering && (heroVideoPreview || props.modelValue.hero_video?.url)"
                    contained
                    class="align-center justify-center"
                    scrim="black"
                  >
                    <v-btn
                      v-if="heroVideoFile || props.modelValue.hero_video"
                      color="error"
                      variant="flat"
                      size="small"
                      prepend-icon="mdi-delete"
                      @click="clearHeroVideo"
                    >
                      Remove
                    </v-btn>
                  </v-overlay>
                </div>
              </v-hover>

              <v-file-input
                v-model="heroVideoFile"
                prepend-icon="mdi-video"
                accept="video/*"
                :label="props.modelValue.hero_video ? 'Replace Hero Video' : 'Select Hero Video'"
                chips
                density="compact"
                hide-details
                class="mb-3"
              />
              <v-text-field
                v-model="mediaData.hero_video.title"
                label="Title"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-2"
              />
              <v-text-field
                v-model="mediaData.hero_video.photographer"
                label="Videographer"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-2"
              />
              <v-text-field
                v-model="mediaData.hero_video.copyright"
                label="Copyright / Source"
                density="compact"
                variant="outlined"
                hide-details
              />
            </v-card>
          </v-col>
          <v-col cols="12" md="4">
            <v-card variant="outlined" class="pa-4 h-100 d-flex flex-column">
              <div class="text-subtitle-2 mb-2 d-flex align-center">
                <v-icon start size="small">mdi-star</v-icon>
                Hero Image
              </div>
              
              <v-hover v-slot="{ isHovering, props: hoverProps }">
                <div v-bind="hoverProps" class="position-relative mb-4 bg-grey-lighten-4 rounded overflow-hidden" style="height: 180px;">
                  <v-img
                    v-if="heroImagePreview || props.modelValue.hero_image?.url"
                    :src="heroImagePreview || props.modelValue.hero_image.url"
                    height="180"
                    cover
                  />
                  <div v-else class="d-flex flex-column align-center justify-center h-100 text-grey">
                    <v-icon size="48">mdi-image-outline</v-icon>
                    <span class="text-caption mt-2">No hero image set</span>
                  </div>
                  <v-overlay
                    :model-value="isHovering && (heroImagePreview || props.modelValue.hero_image?.url)"
                    contained
                    class="align-center justify-center"
                    scrim="black"
                  >
                    <v-btn
                      v-if="heroImageFile || props.modelValue.hero_image"
                      color="error"
                      variant="flat"
                      size="small"
                      prepend-icon="mdi-delete"
                      @click="clearHeroImage"
                    >
                      Remove
                    </v-btn>
                  </v-overlay>
                </div>
              </v-hover>

              <v-file-input
                v-model="heroImageFile"
                prepend-icon="mdi-camera"
                accept="image/*"
                :label="props.modelValue.hero_image ? 'Replace Hero Image' : 'Select Hero Image'"
                chips
                density="compact"
                hide-details
                class="mb-3"
              />
              <v-text-field
                v-model="mediaData.hero.title"
                label="Title"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-2"
              />
              <v-text-field
                v-model="mediaData.hero.photographer"
                label="Photographer"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-2"
              />
              <v-text-field
                v-model="mediaData.hero.copyright"
                label="Copyright / Source"
                density="compact"
                variant="outlined"
                hide-details
              />
            </v-card>
          </v-col>
          <v-col cols="12" md="4">
            <v-card variant="outlined" class="pa-4 h-100 d-flex flex-column">
              <div class="text-subtitle-2 mb-2 d-flex align-center">
                <v-icon start size="small">mdi-door-open</v-icon>
                Entrance Image
              </div>

              <v-hover v-slot="{ isHovering, props: hoverProps }">
                <div v-bind="hoverProps" class="position-relative mb-4 bg-grey-lighten-4 rounded overflow-hidden" style="height: 180px;">
                  <v-img
                    v-if="entranceImagePreview || props.modelValue.entrance_image?.url"
                    :src="entranceImagePreview || props.modelValue.entrance_image.url"
                    height="180"
                    cover
                  />
                  <div v-else class="d-flex flex-column align-center justify-center h-100 text-grey">
                    <v-icon size="48">mdi-image-outline</v-icon>
                    <span class="text-caption mt-2">No entrance image set</span>
                  </div>
                  <v-overlay
                    :model-value="isHovering && (entranceImagePreview || props.modelValue.entrance_image?.url)"
                    contained
                    class="align-center justify-center"
                    scrim="black"
                  >
                    <v-btn
                      v-if="entranceImageFile || props.modelValue.entrance_image"
                      color="error"
                      variant="flat"
                      size="small"
                      prepend-icon="mdi-delete"
                      @click="clearEntranceImage"
                    >
                      Remove
                    </v-btn>
                  </v-overlay>
                </div>
              </v-hover>

              <v-file-input
                v-model="entranceImageFile"
                prepend-icon="mdi-camera"
                accept="image/*"
                :label="props.modelValue.entrance_image ? 'Replace Entrance Image' : 'Select Entrance Image'"
                chips
                density="compact"
                hide-details
                class="mb-3"
              />
              <v-text-field
                v-model="mediaData.entrance.title"
                label="Title"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-2"
              />
              <v-text-field
                v-model="mediaData.entrance.photographer"
                label="Photographer"
                density="compact"
                variant="outlined"
                hide-details
                class="mb-2"
              />
              <v-text-field
                v-model="mediaData.entrance.copyright"
                label="Copyright / Source"
                density="compact"
                variant="outlined"
                hide-details
              />
            </v-card>
          </v-col>
        </v-row>
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
const heroImagePreview = ref(null)
const entranceImagePreview = ref(null)
const heroVideoFile = ref(null)
const heroVideoPreview = ref(null)

const mediaData = ref({
  hero_video: {
    title: props.modelValue.hero_video?.title || '',
    photographer: props.modelValue.hero_video?.photographer || '',
    copyright: props.modelValue.hero_video?.copyright || '',
    data: null
  },
  hero: {
    title: props.modelValue.hero_image?.title || '',
    photographer: props.modelValue.hero_image?.photographer || '',
    copyright: props.modelValue.hero_image?.copyright || '',
    data: null
  },
  entrance: {
    title: props.modelValue.entrance_image?.title || '',
    photographer: props.modelValue.entrance_image?.photographer || '',
    copyright: props.modelValue.entrance_image?.copyright || '',
    data: null
  }
})

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

const syncMediaDataFromModel = (val) => {
  mediaData.value.hero_video = {
    title: val.hero_video?.title || '',
    photographer: val.hero_video?.photographer || '',
    copyright: val.hero_video?.copyright || '',
    data: mediaData.value.hero_video.data // keep current upload data
  }
  mediaData.value.hero = {
    title: val.hero_image?.title || '',
    photographer: val.hero_image?.photographer || '',
    copyright: val.hero_image?.copyright || '',
    data: mediaData.value.hero.data // keep current upload data
  }
  mediaData.value.entrance = {
    title: val.entrance_image?.title || '',
    photographer: val.entrance_image?.photographer || '',
    copyright: val.entrance_image?.copyright || '',
    data: mediaData.value.entrance.data // keep current upload data
  }
}

const getComparable = (caveObj) => {
  if (!caveObj) return null
  const clone = { ...caveObj }

  const getLen = (data) => typeof data === 'string' ? data.length : 0

  if (clone.hero_video) clone.hero_video = { ...clone.hero_video, data: getLen(clone.hero_video.data) }
  if (clone.hero_image) clone.hero_image = { ...clone.hero_image, data: getLen(clone.hero_image.data) }
  if (clone.entrance_image) clone.entrance_image = { ...clone.entrance_image, data: getLen(clone.entrance_image.data) }

  return JSON.stringify(clone)
}

// Watchers for two-way binding
watch(() => props.modelValue, (newVal) => {
  // Only update if fundamentally different to avoid recursion
  if (getComparable(newVal) !== getComparable(internalCave.value)) {
    internalCave.value = { ...newVal }
    if (internalCave.value.location_lng !== coordinates.value.lng || internalCave.value.location_lat !== coordinates.value.lat) {
      coordinates.value = LngLat.convert([internalCave.value.location_lng || 0, internalCave.value.location_lat || 0])
      mapCenter.value = [internalCave.value.location_lng || 0, internalCave.value.location_lat || 0]
    }
    syncTagsFromModel()
    syncMediaDataFromModel(newVal)
  }
}, { deep: true })

watch(internalCave, (newVal) => {
  // Only emit if fundamentally different to avoid recursion
  if (getComparable(newVal) !== getComparable(props.modelValue)) {
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
    heroImagePreview.value = URL.createObjectURL(file)
    mediaData.value.hero.data = file // Store raw file temporarily
    internalCave.value.hero_image = {
      ...(internalCave.value.hero_image || {}),
      ...mediaData.value.hero
    }
  } else if (file === null && internalCave.value.hero_image?.data) {
    heroImagePreview.value = null
    mediaData.value.hero.data = null
    if (props.modelValue.hero_image) {
      internalCave.value.hero_image = {
        ...props.modelValue.hero_image,
        title: mediaData.value.hero.title,
        photographer: mediaData.value.hero.photographer,
        copyright: mediaData.value.hero.copyright,
        data: null
      }
    } else {
      internalCave.value.hero_image = null
    }
  }
})

watch(heroVideoFile, async (file) => {
  if (file) {
    heroVideoPreview.value = URL.createObjectURL(file)
    mediaData.value.hero_video.data = file // Store raw file temporarily
    internalCave.value.hero_video = {
      ...(internalCave.value.hero_video || {}),
      ...mediaData.value.hero_video
    }
  } else if (file === null && internalCave.value.hero_video?.data) {
    heroVideoPreview.value = null
    mediaData.value.hero_video.data = null
    if (props.modelValue.hero_video) {
      internalCave.value.hero_video = {
        ...props.modelValue.hero_video,
        title: mediaData.value.hero_video.title,
        photographer: mediaData.value.hero_video.photographer,
        copyright: mediaData.value.hero_video.copyright,
        data: null
      }
    } else {
      internalCave.value.hero_video = null
    }
  }
})

watch(entranceImageFile, async (file) => {
  if (file) {
    entranceImagePreview.value = URL.createObjectURL(file)
    mediaData.value.entrance.data = file // Store raw file temporarily
    internalCave.value.entrance_image = {
      ...(internalCave.value.entrance_image || {}),
      ...mediaData.value.entrance
    }
  } else if (file === null && internalCave.value.entrance_image?.data) {
    entranceImagePreview.value = null
    mediaData.value.entrance.data = null
    if (props.modelValue.entrance_image) {
      internalCave.value.entrance_image = {
        ...props.modelValue.entrance_image,
        title: mediaData.value.entrance.title,
        photographer: mediaData.value.entrance.photographer,
        copyright: mediaData.value.entrance.copyright,
        data: null
      }
    } else {
      internalCave.value.entrance_image = null
    }
  }
})

const clearHeroVideo = () => {
  heroVideoFile.value = null
  heroVideoPreview.value = null
  mediaData.value.hero_video.data = null
  internalCave.value.hero_video = null
}

const clearHeroImage = () => {
  heroImageFile.value = null
  heroImagePreview.value = null
  mediaData.value.hero.data = null
  internalCave.value.hero_image = null
}

const clearEntranceImage = () => {
  entranceImageFile.value = null
  entranceImagePreview.value = null
  mediaData.value.entrance.data = null
  internalCave.value.entrance_image = null
}

// Metadata handling (updates internalCave when fields change without file change)
watch(mediaData, (newVal) => {
  if (typeof internalCave.value.hero_video === 'object' && internalCave.value.hero_video !== null) {
    Object.assign(internalCave.value.hero_video, newVal.hero_video)
  } else if (newVal.hero_video && Object.values(newVal.hero_video).some(v => v !== '' && v !== null)) {
    internalCave.value.hero_video = { ...newVal.hero_video }
  }

  if (typeof internalCave.value.hero_image === 'object' && internalCave.value.hero_image !== null) {
    Object.assign(internalCave.value.hero_image, newVal.hero)
  } else if (newVal.hero && Object.values(newVal.hero).some(v => v !== '' && v !== null)) {
    internalCave.value.hero_image = { ...newVal.hero }
  }

  if (typeof internalCave.value.entrance_image === 'object' && internalCave.value.entrance_image !== null) {
    Object.assign(internalCave.value.entrance_image, newVal.entrance)
  } else if (newVal.entrance && Object.values(newVal.entrance).some(v => v !== '' && v !== null)) {
    internalCave.value.entrance_image = { ...newVal.entrance }
  }
}, { deep: true })

defineExpose({
  prepareForSubmit: async () => {
    return internalCave.value
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
