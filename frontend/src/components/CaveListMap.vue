<template>
  <v-card>
    <v-card-text class="map-holder">
      <mgl-map
        :map-style="style"
        :center="lnglat"
        :zoom="zoom"
        height="500px"
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
        <mgl-navigation-control />
      </mgl-map>
    </v-card-text>
  </v-card>
</template>

<script setup>
  import { useCaveStore } from '@/stores/caves'
  const caveStore = useCaveStore()
  import { computed, ref } from 'vue';

  import {
    MglMap,
    MglNavigationControl,
    MglGeoJsonSource,
    MglSymbolLayer,
    MglMarker,
    MglPopup,
  } from '@indoorequal/vue-maplibre-gl';

  const style = 'https://api.maptiler.com/maps/outdoor-v2/style.json?key=0gGMv4po9Mjrpd64A528';
  const zoom = 9;
  const lnglat = [-2.609, 51.501]

  const popupRefs = ref([])

  function closePopup(index) {
    const popup = popupRefs.value[index]
    if (popup) popup.remove()
  }

  const mapRef = ref('map')
  console.log(mapRef)
  window.mapRef = mapRef

  const geojsonSource = computed(()=>{
    return {
      data: {
        "type": "FeatureCollection",
        "features": caveStore.caves.map((cave)=> {
          return {
            "type": "Feature",
            "properties": {
              name: cave.name
            },
            "geometry": {
              "coordinates": [
                cave.location_lng,
                cave.location_lat
              ],
              "type": "Point"
            }
          }
        })
      }
    }
  })

  const iconLayout = {
    "icon-image": "marker",
    "icon-size": 1.5
  }

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

.maplibregl-popup-content{
  padding: 0;
  background: transparent;
}

.maplibregl-popup-close-button {
  right: 6px;
  top: 0px;
}
</style>