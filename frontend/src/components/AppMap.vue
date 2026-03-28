<template>
  <mgl-map
    :map-style="modelValue"
    :center="center"
    :zoom="zoom"
    :max-zoom="maxZoom"
    :height="height"
    @map:load="onMapLoad"
  >
    <slot />
    <mgl-navigation-control />
    <mgl-fullscreen-control />
    <MglGeolocateControl v-if="geolocate" :track-user-location="true" :show-accuracy-circle="true" />
  </mgl-map>
</template>

<script setup>
import { computed } from 'vue'
import { MapStyleControl } from '@/utilities/MapStyleControl'
import { mdiTerrain, mdiSatelliteVariant } from '@mdi/js'
import {
  MglMap,
  MglNavigationControl,
  MglFullscreenControl,
  MglGeolocateControl,
  useMap
} from '@indoorequal/vue-maplibre-gl'

const props = defineProps({
  modelValue: {
    type: String,
    required: true
  },
  center: {
    type: Array,
    required: true
  },
  zoom: {
    type: Number,
    required: true
  },
  maxZoom: {
    type: Number,
    default: 15
  },
  height: {
    type: String,
    default: '100%'
  },
  geolocate: {
    type: Boolean,
    default: false
  },
  customOptions: {
    type: Array,
    default: () => [
      {
        title: 'Satellite Hybrid',
        value: 'https://api.maptiler.com/maps/hybrid/style.json?key=0gGMv4po9Mjrpd64A528',
        icon: mdiSatelliteVariant
      },
      {
        title: 'OS',
        value: 'https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge',
        icon: mdiTerrain
      }
    ]
  }
})

const emit = defineEmits(['update:modelValue', 'map:load'])

const onMapLoad = (event) => {
  event.map.addControl(
    new MapStyleControl(props.customOptions, props.modelValue, (newStyle) => {
      emit('update:modelValue', newStyle)
    }),
    'top-right'
  )
  emit('map:load', event)
}

// Expose map context to parent via refs
const mapOne = useMap()
defineExpose({
  map: computed(() => mapOne.map),
  isLoaded: computed(() => mapOne.isLoaded)
})
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";
</style>
