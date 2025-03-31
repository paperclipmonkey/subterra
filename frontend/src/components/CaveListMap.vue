<template>
  <v-card class="map-container">
    <v-card-text class="map-holder">
      <mgl-map
        :map-style="style"
        :center="lnglat"
        :zoom="zoom"
        ref="map"
      >
        <mgl-marker v-for="(cave, index) in caveStore.caves" :key="cave.id" :coordinates="[cave.location_lng, cave.location_lat]">
          <mgl-popup ref="popupRefs">
            <v-card :title="cave.name" :subtitle="cave.location_name">
              <v-card-text>Length: {{ cave.system.length }} Depth: {{ cave.system.vertical_range }}</v-card-text>
              <v-card-actions>
                <v-btn @click="$router.push({name: '/cave/[id]', params: {id: cave.slug}})">
                  View
                </v-btn>
              </v-card-actions>
            </v-card>
          </mgl-popup>
        </mgl-marker>
        <mgl-fullscreen-control/>
        <mgl-navigation-control />
        <MglGeolocateControl/>
      </mgl-map>
    </v-card-text>
  </v-card>
</template>

<script setup>
  import { useCaveStore } from '@/stores/caves'
  const caveStore = useCaveStore()

  import {
    MglMap,
    MglFullscreenControl,
    MglNavigationControl,
    MglMarker,
    MglPopup,
    useMap,
    MglGeolocateControl,
  } from '@indoorequal/vue-maplibre-gl';

  import maplibregl from 'maplibre-gl';

  const style = 'https://api.maptiler.com/maps/outdoor-v2/style.json?key=0gGMv4po9Mjrpd64A528';
  const zoom = 9;
  const lnglat = [-2.609, 51.501]

  import { onMounted, watch } from 'vue';

  const mapOne = useMap();

  watch(() => mapOne.isLoaded, (isLoaded) => { 
    mapOne.map.resize()

    watch(
      () => caveStore.caves,
      (caves) => {
        if (caves.length > 0 && mapOne.isLoaded) {
          const bounds = new maplibregl.LngLatBounds();
          caves.forEach((cave) => {
            bounds.extend([cave.location_lng, cave.location_lat]);
          });
          mapOne.map.fitBounds(bounds, { padding: 50 });
        }
      },
      { immediate: true }
    );
  })

  // mapOne.redraw()
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

.map-container {
  height: calc(100vh - 195px);
}

.map-holder {
  margin-left: -20px;
  margin-right: -20px;
  padding-bottom: 0px;
  width: calc(100% + 40px);
  height: 100%;
}

.maplibregl-popup .maplibregl-popup-content{
  padding: 0;
  background: transparent;
}

.maplibregl-popup-close-button {
  right: 6px;
  top: 0px;
}
</style>