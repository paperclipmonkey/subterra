<template>
  <v-card class="mb-4">
    <v-card-title class="text-h6">Map Annotations</v-card-title>
    <v-card-subtitle>
      Add parking spots, walking routes, and permission-required locations.
    </v-card-subtitle>
    <v-card-text>
      <div class="d-flex flex-wrap ga-2 mb-3">
        <v-btn-toggle v-model="activeMode" mandatory color="primary" density="compact">
          <v-btn value="select" size="small">
            <v-icon start :icon="mdiCursorDefault" />
            Select
          </v-btn>
          <v-btn value="add_parking" size="small">
            <v-icon start :icon="mdiParking" />
            Parking
          </v-btn>
          <v-btn value="add_house" size="small">
            <v-icon start :icon="mdiHome" />
            House
          </v-btn>
          <v-btn value="linestring" size="small">
            <v-icon start :icon="mdiVectorLine" />
            Route
          </v-btn>
        </v-btn-toggle>
      </div>

      <v-row>
        <v-col cols="12" :md="selectedFeatureId && selectedFeatureProps ? 8 : 12">
          <div ref="mapContainer" class="annotation-editor-map" />
        </v-col>
        <v-col v-if="selectedFeatureId && selectedFeatureProps" cols="12" md="4">
          <v-card variant="outlined">
            <v-card-title class="text-subtitle-1">
              <v-icon
                start
                size="small"
                :icon="selectedFeatureProps.annotation_type === 'parking' ? mdiParking : selectedFeatureProps.annotation_type === 'house' ? mdiHome : mdiVectorLine"
              />
              {{ typeLabel }}
            </v-card-title>
            <v-card-text>
              <v-select
                v-model="selectedFeatureProps.annotation_type"
                :items="annotationTypeOptions"
                item-title="label"
                item-value="value"
                label="Type"
                density="compact"
                variant="outlined"
                class="mb-2"
                @update:model-value="updateFeatureProperties"
              />
              <v-textarea
                v-model="selectedFeatureProps.description"
                label="Description"
                density="compact"
                variant="outlined"
                rows="3"
                auto-grow
                :hint="descriptionHint"
                persistent-hint
                @update:model-value="updateFeatureProperties"
              />
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn
                color="red"
                variant="tonal"
                size="small"
                :prepend-icon="mdiDelete"
                @click="deleteSelected"
              >
                Delete Feature
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed, nextTick } from 'vue'
import { mdiCursorDefault, mdiParking, mdiHome, mdiVectorLine, mdiDelete } from '@mdi/js'
import maplibregl from 'maplibre-gl'
import { MapStyleControl } from '@/utilities/MapStyleControl'
import { mdiSatelliteVariant, mdiTerrain } from '@mdi/js'
import {
  TerraDraw,
  TerraDrawPointMode,
  TerraDrawLineStringMode,
  TerraDrawSelectMode,
  TerraDrawRenderMode,
} from 'terra-draw'
import { TerraDrawMapLibreGLAdapter } from 'terra-draw-maplibre-gl-adapter'

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  },
  center: {
    type: Array,
    default: () => [-2, 53],
  },
  caves: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const mapContainer = ref(null)
const activeMode = ref('select')
const selectedFeatureId = ref(null)
const selectedFeatureProps = ref(null)
// Tracks the annotation_type to assign to the next drawn point
let pendingPointType = null
let mapLoaded = false
let dataLoaded = false
let isLoadingAnnotations = false

let map = null
let draw = null
const caveMarkers = []

const annotationTypeOptions = [
  { label: 'Parking', value: 'parking' },
  { label: 'House (Permission Required)', value: 'house' },
  { label: 'Walking Route', value: 'walking_route' },
]

const typeLabel = computed(() => {
  const t = selectedFeatureProps.value?.annotation_type
  return annotationTypeOptions.find(o => o.value === t)?.label || 'Feature'
})

const descriptionHint = computed(() => {
  if (!selectedFeatureProps.value) return ''
  switch (selectedFeatureProps.value.annotation_type) {
    case 'parking': return 'e.g. "Main car park, space for 10 cars"'
    case 'house': return 'e.g. "Farmhouse - ask permission before caving"'
    case 'walking_route': return 'e.g. "Path from car park to entrance (15 min)"'
    default: return ''
  }
})

// Store a mapping of terra-draw feature IDs to our properties
const featurePropertiesMap = ref(new Map())

function initMap () {
  const defaultStyle = 'https://api.maptiler.com/maps/hybrid/style.json?key=0gGMv4po9Mjrpd64A528'

  map = new maplibregl.Map({
    container: mapContainer.value,
    style: defaultStyle,
    center: props.center,
    zoom: 13,
  })

  map.addControl(new maplibregl.NavigationControl(), 'top-right')
  map.addControl(
    new MapStyleControl(
      [
        { title: 'Satellite Hybrid', value: 'https://api.maptiler.com/maps/hybrid/style.json?key=0gGMv4po9Mjrpd64A528', icon: mdiSatelliteVariant },
        { title: 'OS', value: 'https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge', icon: mdiTerrain },
      ],
      defaultStyle,
      (newStyle) => { map.setStyle(newStyle) },
    ),
    'top-right',
  )

  map.on('load', () => {
    mapLoaded = true
    initTerraDraw()
    addCaveMarkers()
    tryLoadExistingAnnotations()
  })

  // Re-add cave markers after a style switch (they are HTML overlays but just in case)
  map.on('style.load', () => {
    // Cave markers are DOM overlays — they survive style changes
    // But terra-draw re-renders its own layers automatically via its adapter
  })
}

function addCaveMarkers () {
  // Clear existing markers
  caveMarkers.forEach(m => m.remove())
  caveMarkers.length = 0

  if (!map || !props.caves?.length) return

  props.caves.forEach(cave => {
    if (!cave.location_lng || !cave.location_lat) return
    const el = document.createElement('div')
    el.className = 'cave-pin-marker'
    el.title = cave.name || 'Cave'
    const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
      .setLngLat([cave.location_lng, cave.location_lat])
      .addTo(map)
    caveMarkers.push(marker)
  })
}

function fitMapToCavesAndAnnotations () {
  if (!map) return
  const bounds = new maplibregl.LngLatBounds()
  let hasCoords = false

  // Include cave locations
  if (props.caves?.length) {
    props.caves.forEach(cave => {
      if (cave.location_lng && cave.location_lat) {
        bounds.extend([cave.location_lng, cave.location_lat])
        hasCoords = true
      }
    })
  }

  // Include annotation features
  if (draw) {
    const snapshot = draw.getSnapshot()
    snapshot.forEach(feature => {
      if (feature.geometry.type === 'Point') {
        bounds.extend(feature.geometry.coordinates)
        hasCoords = true
      } else if (feature.geometry.type === 'LineString') {
        feature.geometry.coordinates.forEach(coord => {
          bounds.extend(coord)
          hasCoords = true
        })
      }
    })
  }

  if (hasCoords) {
    map.fitBounds(bounds, { padding: 60, maxZoom: 15 })
  }
}

function initTerraDraw () {
  draw = new TerraDraw({
    adapter: new TerraDrawMapLibreGLAdapter({ map }),
    modes: [
      new TerraDrawPointMode(),
      new TerraDrawLineStringMode({
        allowSelfIntersections: true,
      }),
      new TerraDrawSelectMode({
        flags: {
          point: {
            feature: {
              draggable: true,
            },
          },
          linestring: {
            feature: {
              draggable: true,
              coordinates: {
                midpoints: true,
                draggable: true,
                deletable: true,
              },
            },
          },
        },
      }),
      new TerraDrawRenderMode({ modeName: 'static' }),
    ],
  })

  draw.start()
  draw.setMode('select')

  draw.on('change', () => {
    emitGeoJSON()
  })

  draw.on('select', (id) => {
    selectedFeatureId.value = id
    const stored = featurePropertiesMap.value.get(id)
    if (stored) {
      selectedFeatureProps.value = { ...stored }
    } else {
      const snapshot = draw.getSnapshot()
      const feature = snapshot.find(f => f.id === id)
      const defaultType = feature?.geometry?.type === 'LineString' ? 'walking_route' : 'parking'
      const newProps = { annotation_type: defaultType, description: '' }
      featurePropertiesMap.value.set(id, { ...newProps })
      selectedFeatureProps.value = { ...newProps }
    }
  })

  draw.on('deselect', () => {
    selectedFeatureId.value = null
    selectedFeatureProps.value = null
  })

  draw.on('finish', (id) => {
    // Assign the correct type based on which button was used
    const type = pendingPointType || 'walking_route'
    if (!featurePropertiesMap.value.has(id)) {
      featurePropertiesMap.value.set(id, { annotation_type: type, description: '' })
    }
    pendingPointType = null
    nextTick(() => {
      activeMode.value = 'select'
    })
  })
}

function tryLoadExistingAnnotations () {
  if (!mapLoaded || !draw) return
  if (!props.modelValue?.features?.length) {
    // No annotations yet — just fit to caves
    fitMapToCavesAndAnnotations()
    return
  }

  isLoadingAnnotations = true

  // Clear any previously loaded features
  const existing = draw.getSnapshot()
  if (existing.length) {
    draw.removeFeatures(existing.map(f => f.id))
    featurePropertiesMap.value.clear()
  }

  props.modelValue.features.forEach(feature => {
    // terra-draw requires a `mode` property to know which mode owns the feature
    const mode = feature.geometry.type === 'Point' ? 'point' : 'linestring'
    const results = draw.addFeatures([{
      type: 'Feature',
      geometry: feature.geometry,
      properties: { mode },
    }])

    // addFeatures returns StoreValidation[] with { id, valid, reason }
    if (results?.[0]?.valid && results[0].id) {
      featurePropertiesMap.value.set(results[0].id, {
        annotation_type: feature.properties?.annotation_type || (mode === 'point' ? 'parking' : 'walking_route'),
        description: feature.properties?.description || '',
      })
    }
  })

  isLoadingAnnotations = false
  dataLoaded = true
  fitMapToCavesAndAnnotations()
}

function updateFeatureProperties () {
  if (!selectedFeatureId.value || !selectedFeatureProps.value) return
  featurePropertiesMap.value.set(selectedFeatureId.value, { ...selectedFeatureProps.value })
  emitGeoJSON()
}

function deleteSelected () {
  if (!selectedFeatureId.value || !draw) return
  const id = selectedFeatureId.value
  draw.removeFeatures([id])
  featurePropertiesMap.value.delete(id)
  selectedFeatureId.value = null
  selectedFeatureProps.value = null
  emitGeoJSON()
}

function emitGeoJSON () {
  if (!draw || isLoadingAnnotations) return
  const snapshot = draw.getSnapshot()
  const drawnIds = new Set(featurePropertiesMap.value.keys())

  const features = snapshot
    .filter(f =>
      (f.geometry.type === 'Point' || f.geometry.type === 'LineString') &&
      drawnIds.has(f.id),
    )
    .map(f => {
      const storedProps = featurePropertiesMap.value.get(f.id) || {}
      return {
        type: 'Feature',
        geometry: f.geometry,
        properties: {
          annotation_type: storedProps.annotation_type || (f.geometry.type === 'LineString' ? 'walking_route' : 'parking'),
          description: storedProps.description || '',
        },
      }
    })

  emit('update:modelValue', {
    type: 'FeatureCollection',
    features,
  })
}

watch(activeMode, (mode) => {
  if (!draw) return
  try {
    if (mode === 'add_parking' || mode === 'add_house') {
      pendingPointType = mode === 'add_parking' ? 'parking' : 'house'
      draw.setMode('point')
    } else if (mode === 'select') {
      draw.setMode('select')
    } else if (mode === 'linestring') {
      pendingPointType = null
      draw.setMode('linestring')
    } else {
      draw.setMode('static')
    }
  } catch {
    draw.setMode('static')
  }
})

// Watch for modelValue changes (data may load after map init)
watch(() => props.modelValue, (val) => {
  if (val?.features?.length && mapLoaded && draw && !dataLoaded) {
    tryLoadExistingAnnotations()
  }
}, { deep: true })

// Watch for caves changes
watch(() => props.caves, () => {
  if (mapLoaded) {
    addCaveMarkers()
  }
}, { deep: true })

onMounted(() => {
  nextTick(() => {
    initMap()
  })
})

onBeforeUnmount(() => {
  caveMarkers.forEach(m => m.remove())
  caveMarkers.length = 0
  if (draw) {
    draw.stop()
  }
  if (map) {
    map.remove()
  }
})
</script>

<style scoped>
.annotation-editor-map {
  width: 100%;
  height: 400px;
  border-radius: 4px;
  overflow: hidden;
}
</style>

<style>
@import "maplibre-gl/dist/maplibre-gl.css";

.cave-pin-marker {
  width: 12px;
  height: 12px;
  background: #f44336;
  border: 2px solid #fff;
  border-radius: 50%;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.4);
  cursor: default;
  pointer-events: none;
}
</style>
