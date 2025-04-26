<template>
  <v-card class="map-container">
    <v-card-text class="map-holder">
      <mgl-map
        :map-style="style"
        :center="lnglat"
        :zoom="zoom"
        max-zoom="15"
        ref="map"
      >
        <mgl-marker v-for="(cave, index) in caveStore.caves" :key="cave.id" :coordinates="[cave.location_lng, cave.location_lat]">
          <mgl-popup ref="popupRefs">
            <v-card :title="cave.name" :subtitle="cave.location_name">
              <v-card-text>Length:{{ (Math.round(cave.system.length / 10) * 10 )/ 1000}}km Depth:{{ cave.system.vertical_range }}m</v-card-text>
              <v-card-actions>
                <v-btn @click="$router.push({name: '/cave/[id]', params: {id: cave.slug}})">
                  View
                </v-btn>
                <v-btn :href="`https://www.google.com/maps?q=${cave.location_lat},${cave.location_lng}`" target="_blank" icon>
                  <v-icon>mdi-google-maps</v-icon>
                </v-btn>
                <v-btn :href="`https://maps.apple.com/?q=${cave.location_lat},${cave.location_lng}`" target="_blank" icon>
                  <v-icon>mdi-apple</v-icon>
                </v-btn>
              </v-card-actions>
            </v-card>
          </mgl-popup>
        </mgl-marker>
        <mgl-fullscreen-control/>
        <mgl-navigation-control />
        <MglGeolocateControl :track-user-location="true" :showAccuracyCircle="true"/>
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

  const style = 'https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge';
  const zoom = 5;
  const lnglat = [-2, 53]

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