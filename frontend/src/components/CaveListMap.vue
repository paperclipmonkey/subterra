<template>
      <v-card>
        <v-card-title>Map</v-card-title>
        <v-card-text class="map-holder">
          <mgl-map
            :map-style="style"
            :center="lnglat"
            :zoom="zoom"
            height="500px"
          >
          <mgl-geo-json-source
            source-id="geojson"
            :data="geojsonSource.data"
          >
            <mgl-symbol-layer
              layer-id="geojson"
              :layout="layout"
              :paint="paint"
            />
          </mgl-geo-json-source>
          <mgl-navigation-control />
          </mgl-map>
        </v-card-text>
      </v-card>
</template>

<script setup>
  import { useCaveStore } from '@/stores/caves'
  const caveStore = useCaveStore()
  import { computed } from 'vue';
  

import {
  MglMap,
  MglNavigationControl,
  MglGeoJsonSource,
  MglSymbolLayer,
} from '@indoorequal/vue-maplibre-gl';

const style = 'https://api.maptiler.com/maps/outdoor-v2/style.json?key=0gGMv4po9Mjrpd64A528';
const zoom = 9;

const lnglat = [-2.609, 51.501]

const geojsonSource = computed(()=>{
  return {
    data: {
      "type": "FeatureCollection",
      "features": [
        {
          "type": "Feature",
          "properties": {},
          "geometry": {
            "coordinates": [
              -2.6837168915738516,
              51.236467389561284
            ],
            "type": "Point"
          }
        }
      ]
    }
  }
  // caveStore.caves.map()
})


const layout = {
  // 'line-join': 'round',
  // 'line-cap' : 'round'
};

const paint = {
  // 'line-color': '#FF0000',
  // 'line-width': 8
};


</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

.map-holder {
  margin-left: -20px;
  margin-right: -20px;
  padding-bottom: 0px;
}
</style>