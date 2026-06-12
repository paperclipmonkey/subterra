<template>
  <v-container v-if="loading">
    <v-skeleton-loader type="article, image" />
  </v-container>
  <v-container v-else-if="hut">
    <v-img height="300"
           :src="hut.image_url"
           cover class="mb-4 rounded">
      <div class="position-absolute top-0 left-0 pa-4 d-flex" style="z-index: 1;">
        <v-btn :icon="mdiArrowLeft" variant="tonal" color="white" class="backdrop-blur mr-2"
               @click="$router.push('/huts')" />
      </div>
      <div class="position-absolute top-0 right-0 pa-4" style="z-index: 1;">
        <HutEditModal v-if="canEdit" :hut="hut" class="backdrop-blur" />
      </div>
      <div class="d-flex fill-height align-end">
        <div class="bg-black-transparent pa-4 w-100">
          <h1 class="text-h4 text-white font-weight-bold">{{ hut.name }}</h1>
          <div class="text-subtitle-1 text-white">
            <v-icon color="white" start :icon="mdiHomeGroup" /> 
            <router-link v-if="hut.club" :to="`/club/${hut.club.slug}`" class="text-white text-decoration-none hover-underline">
              {{ hut.club.name }}
            </router-link>
            <span v-else>{{ hut.club?.name }}</span>
          </div>
        </div>
      </div>
    </v-img>

    <v-row>
      <v-col cols="12" md="8">
        <v-card class="mb-4">
          <v-card-title class="d-flex justify-space-between align-center">
            About
            <CorrectionModal entity-type="hut" :entity-id="hut.id" :entity-name="hut.name" />
          </v-card-title>
          <v-card-text>
            <div v-if="hut.description" class="vue-markdown">
              <MarkdownRenderer :source="hut.description" />
            </div>
            <p v-else class="text-grey font-italic">No description available.</p>

            <v-divider class="my-3" />

            <div v-if="hut.external_url" class="d-flex align-center mb-2">
              <v-icon start color="primary" :icon="mdiWeb" />
              <a :href="hut.external_url" target="_blank" class="text-primary text-decoration-none">{{ hut.external_url }}</a>
            </div>

            <div v-if="hut.amenities && hut.amenities.length" class="mt-4">
              <strong>Amenities:</strong>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <v-chip v-for="amenity in hut.amenities" :key="amenity" size="small" color="primary"
                        variant="outlined" class="mr-2 mb-2">
                  {{ amenity }}
                </v-chip>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <v-card>
          <v-card-title>Booking Info</v-card-title>
          <v-card-text>
            <div v-if="hut.booking_info" class="markdown-body">
              <MarkdownRenderer :source="hut.booking_info" />
            </div>
            <span v-else class="text-grey font-italic">Please contact the club for booking information.</span>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card class="mb-4">
          <v-card-title>Reciprocal Clubs</v-card-title>
          <v-card-text>
            <div v-if="hut.reciprocal_clubs && hut.reciprocal_clubs.length">
              <v-list density="compact">
                <v-list-item v-for="club in hut.reciprocal_clubs" :key="club.id" :title="club.name"
                             :to="`/club/${club.slug}`" link :prepend-icon="mdiShieldAccount" />
              </v-list>
            </div>
            <div v-else class="text-grey font-italic">No reciprocal clubs listed.</div>
          </v-card-text>
        </v-card>



        <v-card>
          <v-card-title>Location</v-card-title>
          <v-card-text>
            <div v-if="hut.location_lat && hut.location_lng">
              <v-card class="mb-4 rounded-lg" elevation="1">
                <AppMap ref="mapRef" v-model="style" :center="lnglat" :zoom="zoom" :max-zoom="15" height="300px" @map:load="onMapLoad">
                  <mgl-marker :coordinates="lnglat" color="#cc0000" />
                  
                  
                </AppMap>
                <v-card-text>
                  <div class="d-flex justify-space-between align-center">
                    <div>
                      <div class="text-caption text-grey">Coordinates</div>
                      <div class="font-weight-medium text-body-2">{{
                        hut.location_lat.toFixed(5) }}, {{
                        hut.location_lng.toFixed(5) }}</div>
                    </div>
                    <div class="d-flex">
                      <v-tooltip text="Open in Google Maps" location="top">
                        <template #activator="{ props }">
                          <v-btn :icon="mdiGoogleMaps" size="small" variant="text"
                                 v-bind="props"
                                 :href="`https://www.google.com/maps?q=${hut.location_lat},${hut.location_lng}`"
                                 target="_blank" />
                        </template>
                      </v-tooltip>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </div>
            <div v-else>Location not available</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
  <v-container v-else-if="error" class="fill-height d-flex flex-column justify-center align-center text-center">
    <v-icon :icon="mdiAlertCircleOutline" size="64" color="grey" class="mb-4" />
    <h2 class="text-h5 text-grey-darken-1 mb-2">Oops!</h2>
    <p class="text-body-1 text-grey mb-6">{{ error }}</p>
    <v-btn color="primary" variant="flat" to="/huts" :prepend-icon="mdiArrowLeft">
      Back to Huts
    </v-btn>
  </v-container>

  <v-container v-else>
    <v-alert type="error">Hut not found</v-alert>
  </v-container>
</template>

<script setup>
import AppMap from '@/components/AppMap.vue'

import { mdiAlertCircleOutline, mdiArrowLeft, mdiGoogleMaps, mdiHomeGroup, mdiShieldAccount, mdiWeb } from '@mdi/js'
import { ref, onMounted, computed } from 'vue'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import { useRoute } from 'vue-router'
import { useHutStore } from '@/stores/huts'

import { useAppStore } from '@/stores/app'
import HutEditModal from '@/components/HutEditModal.vue'
import CorrectionModal from '@/components/CorrectionModal.vue'
import { usePageTitle } from '@/composables/usePageTitle'

const route = useRoute()
const hutStore = useHutStore()
const userStore = useAppStore()

onMounted(() => {
  hutStore.fetchHut(route.params.id)
})

const hut = computed(() => hutStore.currentHut)
const loading = computed(() => hutStore.loading)
const error = computed(() => hutStore.error)

const canEdit = computed(() => {
  if (!userStore.user) return false
  if (userStore.user.is_admin) return true
  if (hut.value && hut.value.club_id && userStore.user.clubs) {
    return userStore.user.clubs.some(c => c.id === hut.value.club_id && c.is_admin)
  }
  return false
})

const pageTitle = computed(() => hut.value?.name)
usePageTitle(pageTitle)


// Map Setup
import {
  MglMap,
  MglNavigationControl,
  MglMarker,
  MglFullscreenControl,
} from '@indoorequal/vue-maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'



const style = ref('https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge')
const zoom = 11
const lnglat = computed(() => {
  if (hut.value && hut.value.location_lat && hut.value.location_lng) {
    return [hut.value.location_lng, hut.value.location_lat]
  }
  return [-2, 53] // Default
})
const onMapLoad = (event) => {

}
</script>

<style scoped>
.bg-black-transparent {
  background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
}

.backdrop-blur {
  backdrop-filter: blur(4px);
  background-color: rgba(0, 0, 0, 0.3) !important;
}

:deep(.vue-markdown) {
  font-family: Roboto, sans-serif;
  font-size: 16px;
  line-height: 1.6;
  color: rgba(0, 0, 0, 0.87);
}

:deep(.vue-markdown h1),
:deep(.vue-markdown h2),
:deep(.vue-markdown h3) {
  font-weight: 500;
  margin-bottom: 16px;
  color: #1a1a1a;
}

:deep(.vue-markdown p) {
  margin-bottom: 16px;
  color: rgba(0, 0, 0, 0.87);
}

:deep(.vue-markdown a) {
  color: #1976D2;
  text-decoration: none;
}

:deep(.vue-markdown a:hover) {
  text-decoration: underline;
}

:deep(.vue-markdown ul),
:deep(.vue-markdown ol) {
  padding-left: 24px;
  margin-bottom: 16px;
  color: rgba(0, 0, 0, 0.87);
}

:deep(.vue-markdown li) {
  margin-bottom: 8px;
}

:deep(.vue-markdown code) {
  background-color: #f5f5f5;
  padding: 2px 4px;
  border-radius: 4px;
  font-family: 'Courier New', monospace;
  color: #d32f2f;
}

:deep(.vue-markdown pre) {
  background-color: #f5f5f5;
  padding: 16px;
  border-radius: 4px;
  overflow-x: auto;
  color: #333;
}
</style>
