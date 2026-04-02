<template>
  <v-card class="map-container">
    <v-card-text class="map-holder">
      <AppMap ref="mapRef" v-model="style" geolocate :center="lnglat" :zoom="zoom" :max-zoom="15">
        <mgl-marker v-for="cave in caves" :key="cave.id"
                    :coordinates="[cave.location_lng, cave.location_lat]">
          <mgl-popup ref="popupRefs">
            <v-card>
              <v-img v-if="cave.hero_image || cave.entrance_image" :src="cave.hero_image?.url || cave.entrance_image?.url" height="80" cover class="rounded-t">
                <v-card-title class="text-white" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">
                  {{ cave.name }}
                </v-card-title>
                <v-card-subtitle class="text-white" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">
                  {{ cave.location_name }}
                </v-card-subtitle>
              </v-img>
              <template v-else>
                <v-card-title>
                  {{ cave.name }}
                </v-card-title>
                <v-card-subtitle>
                  {{ cave.location_name }}
                </v-card-subtitle>
              </template>
              <v-card-text>
                Depth: {{ cave.system?.vertical_range ?? '?' }}m | Length: {{ cave.system?.length ?? '?' }}m
              </v-card-text>
              <v-card-actions>
                <v-btn :to="`/caves/${cave.slug}`">
                  View
                </v-btn>
                <v-btn :href="`https://www.google.com/maps?q=${cave.location_lat},${cave.location_lng}`"
                       target="_blank" icon>
                  <v-icon :icon="mdiGoogleMaps" />
                </v-btn>
                <v-btn :href="`https://maps.apple.com/?q=${cave.location_lat},${cave.location_lng}`"
                       target="_blank" icon>
                  <v-icon :icon="mdiApple" />
                </v-btn>
              </v-card-actions>
            </v-card>
          </mgl-popup>
        </mgl-marker>
        
        
        
      </AppMap>
    </v-card-text>
  </v-card>
</template>

<script setup>
import AppMap from '@/components/AppMap.vue'

import { mdiApple, mdiGoogleMaps } from '@mdi/js'

import {
  MglMap,
  MglFullscreenControl,
  MglNavigationControl,
  MglMarker,
  MglPopup,
  useMap,
  MglGeolocateControl,
} from '@indoorequal/vue-maplibre-gl'

import maplibregl from 'maplibre-gl'
import { onMounted, watch, computed } from 'vue'

const props = defineProps({
  caves: {
    type: Array,
    required: true
  }
})

const style = ref('https://api.maptiler.com/maps/hybrid/style.json?key=0gGMv4po9Mjrpd64A528')
const zoom = 5
// Default center
const lnglat = [-2, 53]




const mapRef = ref(null)

watch(() => mapRef.value?.isLoaded, (isLoaded) => {
  if (!isLoaded) {
    return
  }

  mapRef.value?.map?.resize()


  watch(
    () => props.caves,
    (caves) => {
      if (caves.length > 0 && mapRef.value?.isLoaded) {
        const bounds = new maplibregl.LngLatBounds()
        let hasPoints = false
        caves.forEach((cave) => {
          if (cave.location_lat && cave.location_lng) {
            bounds.extend([cave.location_lng, cave.location_lat])
            hasPoints = true
          }
        })
        if (hasPoints) {
          mapRef.value?.map.fitBounds(bounds, { padding: 50, maxZoom: 15 })
        }
      }
    },
    { immediate: true }
  )
})
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

// Assuming similar style requirements as other maps
.map-container {
  height: 600px;
  /* Or calculated height */
}

.map-holder {
  padding: 0px !important; // override v-card-text padding
  width: 100%;
  height: 100%;
}

.maplibregl-popup .maplibregl-popup-content {
  padding: 0;
  background: transparent;
}

.maplibregl-popup-content .maplibregl-popup-close-button {
  right: 6px;
  top: 0px;
}
</style>
