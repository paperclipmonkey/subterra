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
            <mgl-marker :coordinates="lnglat" color="#cc0000" />
            <mgl-navigation-control />
          </mgl-map>
        </v-card-text>
      </v-card>
</template>

<script setup>
import {
  MglMap,
  MglNavigationControl,
  MglMarker,
} from '@indoorequal/vue-maplibre-gl';

const style = 'https://api.maptiler.com/maps/outdoor-v2/style.json?key=0gGMv4po9Mjrpd64A528';
const zoom = 11;

const lnglat = [-2.609, 51.501]

</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

.map-holder {
  margin-left: -20px;
  margin-right: -20px;
  padding-bottom: 0px;
}
</style>