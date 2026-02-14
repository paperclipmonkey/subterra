<template>
  <v-card class="map-container">
    <v-card-text class="map-holder">
      <mgl-map ref="map" :map-style="style" :center="lnglat" :zoom="zoom" :max-zoom="15">
        <mgl-marker v-for="hut in huts" :key="hut.id"
                    :coordinates="[hut.location_lng, hut.location_lat]">
          <mgl-popup ref="popupRefs">
            <v-card>
              <v-img v-if="hut.hero_image" :src="hut.hero_image" height="80" cover class="rounded-t">
                <v-card-title class="text-white" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">
                  {{ hut.name }}
                </v-card-title>
                <v-card-subtitle class="text-white" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">
                  {{ hut.club?.name }}
                </v-card-subtitle>
              </v-img>
              <template v-else>
                <v-card-title>
                  {{ hut.name }}
                </v-card-title>
                <v-card-subtitle>
                  {{ hut.club?.name }}
                </v-card-subtitle>
              </template>
              <v-card-text v-if="hut.amenities && hut.amenities.length > 0">
                {{ hut.amenities.slice(0, 3).join(', ') }}{{ hut.amenities.length > 3 ? '...' : '' }}
              </v-card-text>
              <v-card-actions>
                <v-btn @click="$router.push(`/huts/${hut.id}`)">
                  View
                </v-btn>
                <v-btn :href="`https://www.google.com/maps?q=${hut.location_lat},${hut.location_lng}`"
                       target="_blank" icon>
                  <v-icon>mdi-google-maps</v-icon>
                </v-btn>
                <v-btn :href="`https://maps.apple.com/?q=${hut.location_lat},${hut.location_lng}`"
                       target="_blank" icon>
                  <v-icon>mdi-apple</v-icon>
                </v-btn>
              </v-card-actions>
            </v-card>
          </mgl-popup>
        </mgl-marker>
        <mgl-fullscreen-control />
        <mgl-navigation-control />
        <MglGeolocateControl :track-user-location="true" :show-accuracy-circle="true" />
      </mgl-map>
    </v-card-text>
  </v-card>
</template>

<script setup>
// Removed store usage for huts, now coming from props
const props = defineProps({
    huts: {
        type: Array,
        default: () => []
    }
})

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

const style = 'https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge'
const zoom = 5
const lnglat = [-2, 53]

import { onMounted, watch } from 'vue'

const mapOne = useMap()

watch(() => mapOne.isLoaded, (isLoaded) => {
    mapOne.map.resize()

    watch(
        () => props.huts,
        (huts) => {
            if (huts.length > 0 && mapOne.isLoaded) {
                const bounds = new maplibregl.LngLatBounds()
                let hasPoints = false
                huts.forEach((hut) => {
                    if (hut.location_lat && hut.location_lng) {
                        bounds.extend([hut.location_lng, hut.location_lat])
                        hasPoints = true
                    }
                })
                if (hasPoints) {
                    mapOne.map.fitBounds(bounds, { padding: 50 })
                }
            }
        },
        { immediate: true }
    )
})
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

.map-container {
    height: calc(100dvh - 195px);
}

.map-holder {
    margin-left: -20px;
    margin-right: -20px;
    padding-bottom: 0px;
    width: calc(100% + 40px);
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
