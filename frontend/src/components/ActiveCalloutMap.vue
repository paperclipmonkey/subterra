<template>
  <v-card class="map-container">
    <v-card-text class="map-holder">
      <mgl-map :map-style="style" :center="lnglat" :zoom="zoom" :max-zoom="15" ref="map">
      <mgl-marker v-if="mapOne.isLoaded" v-for="(callout) in validCallouts" :key="callout.id"
          :coordinates="{ lng: callout.lng, lat: callout.lat }">
          <mgl-popup>
            <v-card width="200px">
              <v-card-title class="subtitle-2">{{ callout.cave_name || 'Unknown Location' }}</v-card-title>
              <v-card-text class="caption pa-2">
                <!-- Team Size removed as per request -->
                <!-- Exiting time removed as per request -->
              </v-card-text>
            </v-card>
          </mgl-popup>
        </mgl-marker>
        <mgl-fullscreen-control />
        <mgl-navigation-control />
      </mgl-map>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { computed, watch } from 'vue';
import moment from 'moment';
import {
  MglMap,
  MglFullscreenControl,
  MglNavigationControl,
  MglMarker,
  MglPopup,
  useMap
} from '@indoorequal/vue-maplibre-gl';
import maplibregl from 'maplibre-gl';

const props = defineProps({
  callouts: {
    type: Array,
    default: () => []
  }
});

const validCallouts = computed(() => {
  return props.callouts.filter(c => c && c.lat && c.lng).map(c => ({
    ...c,
    lat: Number(c.lat),
    lng: Number(c.lng)
  }));
});

const style = 'https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge';
const zoom = 6;
const lnglat = [-2.5, 54.2] // Rough center of UK caving areas

const mapOne = useMap();

const updateBounds = () => {
  if (validCallouts.value.length > 0 && mapOne.isLoaded) {
    const bounds = new maplibregl.LngLatBounds();
    validCallouts.value.forEach((c) => {
      bounds.extend([c.lng, c.lat]);
    });

    if (!bounds.isEmpty()) {
      mapOne.map.fitBounds(bounds, { padding: 50, maxZoom: 12 });
    }
  }
};

watch(() => mapOne.isLoaded, (isLoaded) => {
  if (isLoaded) {
    mapOne.map.resize();
    updateBounds();
  }
});

watch(() => props.callouts, () => {
  updateBounds();
}, { deep: true });
</script>

<style scoped>
@import "maplibre-gl/dist/maplibre-gl.css";

.map-container {
  height: 400px;
  width: 100%;
}

.map-holder {
  padding: 0;
  width: 100%;
  height: 100%;
}
</style>
