<template>
  <div class="map-container">
    <AppMap ref="mapRef" v-model="style" :center="lnglat" :zoom="zoom" :max-zoom="15">
      <mgl-marker v-for="(callout) in validCallouts" :key="callout.id"
                  :coordinates="[callout.lng, callout.lat]">
        <mgl-popup>
          <v-card width="200px">
            <v-card-title class="subtitle-2">{{ callout.cave_name || 'Unknown Location' }}</v-card-title>
          </v-card>
        </mgl-popup>
      </mgl-marker>
    </AppMap>
  </div>
</template>

<script setup>
import AppMap from '@/components/AppMap.vue'
import { MapStyleControl } from '@/utilities/MapStyleControl'

import { computed, ref, watch } from 'vue'
import {
  MglMap,
  MglFullscreenControl,
  MglNavigationControl,
  MglMarker,
  MglPopup,
  useMap
} from '@indoorequal/vue-maplibre-gl'
import maplibregl from 'maplibre-gl'

const props = defineProps({
  callouts: {
    type: Array,
    default: () => []
  }
})

const validCallouts = computed(() => {
  return props.callouts
    .filter(c => c && (c.lat || c.location_lat) && (c.lng || c.location_lng))
    .map(c => ({
      ...c,
      lat: Number(c.lat || c.location_lat),
      lng: Number(c.lng || c.location_lng)
    }))
})

const style = ref('https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge')
const zoom = 6
const lnglat = [-2.5, 54.2]


 // Rough center of UK caving areas

const mapRef = ref(null)

const updateBounds = () => {
  if (validCallouts.value.length > 0 && mapRef.value?.isLoaded) {
    const bounds = new maplibregl.LngLatBounds()
    validCallouts.value.forEach((c) => {
      bounds.extend([c.lng, c.lat])
    })

    if (!bounds.isEmpty()) {
      mapRef.value?.map.fitBounds(bounds, { padding: 50, maxZoom: 8 })
    }
  }
}

watch(() => mapRef.value?.isLoaded, (isLoaded) => {
  if (isLoaded) {
    mapRef.value?.map.resize()
    updateBounds()
  }
})

watch(() => validCallouts.value, () => {
  updateBounds()
}, { deep: true, immediate: true })
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

.map-container {
  height: 400px;
  width: 100%;
  position: relative;

  &:fullscreen {
    z-index: 9999;
    background: white;
  }

  &:-webkit-full-screen {
    z-index: 9999;
    background: white;
  }

  &::backdrop {
    background-color: white;
  }
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
