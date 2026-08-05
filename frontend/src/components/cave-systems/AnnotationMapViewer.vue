<template>
  <v-card v-if="hasContent" class="annotation-map-container mb-4">
    <v-card-title class="text-h6">Map Annotations</v-card-title>
    <v-card-text class="annotation-map-holder">
      <AppMap
        ref="mapRef"
        v-model="style"
        :center="center"
        :zoom="12"
        :max-zoom="18"
        @map:load="onMapLoad"
      />
      <OverlayTogglePanel
        :overlays="overlayList"
        :visibility="overlays.visibility"
        :loading="overlays.loading"
        @toggle="overlays.toggle"
      />
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import AppMap from '@/components/AppMap.vue'
import maplibregl from 'maplibre-gl'
import OverlayTogglePanel from '@/components/cave-systems/OverlayTogglePanel.vue'
import { useMapOverlays } from '@/composables/useMapOverlays'

const props = defineProps({
  annotation: {
    type: Object,
    default: null,
  },
  caves: {
    type: Array,
    default: () => [],
  },
  overlays: {
    type: Array,
    default: () => [],
  },
})

const style = ref('https://api.maptiler.com/maps/hybrid/style.json?key=0gGMv4po9Mjrpd64A528')
const mapRef = ref(null)
const mapInstance = ref(null)
let activePopup = null

// GeoTIFF overlay rendering (shared with the individual cave map)
const overlays = useMapOverlays(() => mapInstance.value, () => props.overlays)
const overlayList = overlays.overlayList

// Pre-create icon image data (synchronous, reusable)
const parkingIcon = createParkingIcon()
const houseIcon = createHouseIcon()
const caveIcon = createCaveIcon()

const hasAnnotations = computed(() => {
  return props.annotation?.geojson?.features?.length > 0
})

const hasContent = computed(() => {
  return hasAnnotations.value || overlayList.value.length > 0
})

const center = computed(() => {
  // Center on the first cave if available, otherwise first annotation feature
  if (props.caves?.length > 0) {
    const cave = props.caves[0]
    if (cave.location_lng && cave.location_lat) {
      return [cave.location_lng, cave.location_lat]
    }
  }
  if (hasAnnotations.value) {
    const firstFeature = props.annotation.geojson.features[0]
    const geom = firstFeature.geometry
    if (geom.type === 'Point') {
      return geom.coordinates
    }
    if (geom.type === 'LineString' && geom.coordinates.length > 0) {
      return geom.coordinates[0]
    }
  }
  return [-2, 53]
})

function canvasToImageData (canvas) {
  const ctx = canvas.getContext('2d')
  return { width: canvas.width, height: canvas.height, data: new Uint8Array(ctx.getImageData(0, 0, canvas.width, canvas.height).data.buffer) }
}

function createParkingIcon () {
  const size = 64
  const canvas = document.createElement('canvas')
  canvas.width = size
  canvas.height = size
  const ctx = canvas.getContext('2d')
  ctx.beginPath()
  ctx.arc(size / 2, size / 2, size / 2 - 2, 0, Math.PI * 2)
  ctx.fillStyle = '#1976d2'
  ctx.fill()
  ctx.strokeStyle = '#fff'
  ctx.lineWidth = 3
  ctx.stroke()
  ctx.fillStyle = '#fff'
  ctx.font = 'bold 36px Arial'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillText('P', size / 2, size / 2)
  return canvasToImageData(canvas)
}

function createHouseIcon () {
  const size = 64
  const canvas = document.createElement('canvas')
  canvas.width = size
  canvas.height = size
  const ctx = canvas.getContext('2d')
  ctx.beginPath()
  ctx.arc(size / 2, size / 2, size / 2 - 2, 0, Math.PI * 2)
  ctx.fillStyle = '#e65100'
  ctx.fill()
  ctx.strokeStyle = '#fff'
  ctx.lineWidth = 3
  ctx.stroke()
  ctx.fillStyle = '#fff'
  ctx.beginPath()
  ctx.moveTo(size / 2, 14)
  ctx.lineTo(16, 34)
  ctx.lineTo(48, 34)
  ctx.closePath()
  ctx.fill()
  ctx.fillRect(20, 32, 24, 20)
  return canvasToImageData(canvas)
}

function createCaveIcon () {
  const size = 64
  const canvas = document.createElement('canvas')
  canvas.width = size
  canvas.height = size
  const ctx = canvas.getContext('2d')
  // Dark circle background
  ctx.beginPath()
  ctx.arc(size / 2, size / 2, size / 2 - 2, 0, Math.PI * 2)
  ctx.fillStyle = '#d32f2f'
  ctx.fill()
  ctx.strokeStyle = '#fff'
  ctx.lineWidth = 3
  ctx.stroke()
  // Cave opening shape (arch/triangle)
  ctx.fillStyle = '#fff'
  ctx.beginPath()
  ctx.moveTo(18, 48)
  ctx.lineTo(32, 16)
  ctx.lineTo(46, 48)
  ctx.quadraticCurveTo(32, 38, 18, 48)
  ctx.closePath()
  ctx.fill()
  return canvasToImageData(canvas)
}

function showPopup (map, lngLat, html, offset = 16) {
  if (activePopup) {
    activePopup.remove()
    activePopup = null
  }
  activePopup = new maplibregl.Popup({ offset, className: 'annotation-popup', maxWidth: '280px' })
    .setLngLat(lngLat)
    .setHTML(html)
    .addTo(map)
  activePopup.on('close', () => { activePopup = null })
}

function onMapLoad (e) {
  mapInstance.value = e.map
  overlays.render()
  renderAnnotations()
}

function addLayerEvent (map, event, layer, handler) {
  map.on(event, layer, handler)
}

function renderAnnotations () {
  const map = mapInstance.value
  if (!map) return

  // Clean up existing layers/sources/images
  cleanupMap()

  const geojson = props.annotation?.geojson
  const hasGeoJson = geojson?.features?.length > 0

  // Separate features by type
  const parkingFeatures = []
  const houseFeatures = []
  const lineFeatures = []

  if (hasGeoJson) {
    geojson.features.forEach(feature => {
      const annotationType = feature.properties?.annotation_type
      if (feature.geometry.type === 'Point' && annotationType === 'parking') {
        parkingFeatures.push(feature)
      } else if (feature.geometry.type === 'Point' && annotationType === 'house') {
        houseFeatures.push(feature)
      } else if (feature.geometry.type === 'LineString') {
        lineFeatures.push(feature)
      }
    })
  }

  // Add cave markers
  const caveFeatures = (props.caves || [])
    .filter(c => c.location_lng && c.location_lat)
    .map(c => ({
      type: 'Feature',
      geometry: { type: 'Point', coordinates: [c.location_lng, c.location_lat] },
      properties: { name: c.name, id: c.id },
    }))

  if (caveFeatures.length > 0) {
    if (!map.hasImage('cave-icon')) map.addImage('cave-icon', caveIcon)
    map.addSource('annotation-caves', {
      type: 'geojson',
      data: { type: 'FeatureCollection', features: caveFeatures },
    })
    map.addLayer({
      id: 'annotation-caves-layer',
      type: 'symbol',
      source: 'annotation-caves',
      layout: { 'icon-image': 'cave-icon', 'icon-size': 0.5, 'icon-allow-overlap': true },
    })
    addLayerEvent(map, 'click', 'annotation-caves-layer', (e) => {
      const f = e.features[0]
      const coords = f.geometry.coordinates.slice()
      const name = escapeHtml(f.properties?.name || 'Cave')
      showPopup(map, coords, `<div style="font-family:sans-serif;max-width:240px;">
        <div style="display:flex;align-items:center;gap:6px;">
          <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:#d32f2f;border-radius:50%;color:#fff;font-size:11px;font-weight:bold;">▲</span>
          <strong>${name}</strong>
        </div>
      </div>`)
    })
    addLayerEvent(map, 'mouseenter', 'annotation-caves-layer', () => { map.getCanvas().style.cursor = 'pointer' })
    addLayerEvent(map, 'mouseleave', 'annotation-caves-layer', () => { map.getCanvas().style.cursor = '' })
  }

  // Add walking routes
  if (lineFeatures.length > 0) {
    map.addSource('annotation-lines', {
      type: 'geojson',
      data: { type: 'FeatureCollection', features: lineFeatures },
    })
    map.addLayer({
      id: 'annotation-lines-layer',
      type: 'line',
      source: 'annotation-lines',
      paint: {
        'line-color': '#ff9800',
        'line-width': 4,
        'line-dasharray': [2, 2],
      },
    })
    // Invisible wider hit area for easier clicking
    map.addLayer({
      id: 'annotation-lines-hit',
      type: 'line',
      source: 'annotation-lines',
      paint: {
        'line-color': 'transparent',
        'line-width': 16,
      },
    })

    addLayerEvent(map, 'click', 'annotation-lines-hit', (e) => {
      const feature = e.features[0]
      const desc = feature.properties?.description
      showPopup(map, e.lngLat, `<div style="font-family:sans-serif;max-width:240px;">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
          <span style="display:inline-block;width:24px;height:3px;background:#ff9800;border-radius:2px;border:1px dashed #ff9800;"></span>
          <strong>Walking Route</strong>
        </div>
        ${desc ? `<p style="margin:0;color:#555;">${escapeHtml(desc)}</p>` : '<p style="margin:0;color:#888;font-style:italic;">No description</p>'}
      </div>`, 8)
    })
    addLayerEvent(map, 'mouseenter', 'annotation-lines-hit', () => { map.getCanvas().style.cursor = 'pointer' })
    addLayerEvent(map, 'mouseleave', 'annotation-lines-hit', () => { map.getCanvas().style.cursor = '' })
  }

  // Add parking icons
  if (!map.hasImage('parking-icon')) {
    map.addImage('parking-icon', parkingIcon)
  }
  if (parkingFeatures.length > 0) {
    map.addSource('annotation-parking', {
      type: 'geojson',
      data: { type: 'FeatureCollection', features: parkingFeatures },
    })
    map.addLayer({
      id: 'annotation-parking-layer',
      type: 'symbol',
      source: 'annotation-parking',
      layout: {
        'icon-image': 'parking-icon',
        'icon-size': 0.5,
        'icon-allow-overlap': true,
      },
    })

    addLayerEvent(map, 'click', 'annotation-parking-layer', (e) => {
      const feature = e.features[0]
      const coords = feature.geometry.coordinates.slice()
      const desc = feature.properties?.description || 'Parking'
      const [lng, lat] = coords

      showPopup(map, coords, `<div style="font-family:sans-serif;max-width:240px;">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
          <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:#1976d2;border-radius:50%;color:#fff;font-weight:bold;font-size:13px;">P</span>
          <strong>Parking</strong>
        </div>
        <p style="margin:0 0 8px;color:#555;">${escapeHtml(desc)}</p>
        <div style="display:flex;gap:8px;">
          <a href="https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#f5f5f5;border-radius:4px;text-decoration:none;color:#333;font-size:12px;">
            <img src="https://www.google.com/favicon.ico" width="14" height="14" alt="" style="border-radius:2px;" />Google Maps
          </a>
          <a href="https://maps.apple.com/?daddr=${lat},${lng}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#f5f5f5;border-radius:4px;text-decoration:none;color:#333;font-size:12px;">
            Apple Maps
          </a>
        </div>
      </div>`)
    })
    addLayerEvent(map, 'mouseenter', 'annotation-parking-layer', () => { map.getCanvas().style.cursor = 'pointer' })
    addLayerEvent(map, 'mouseleave', 'annotation-parking-layer', () => { map.getCanvas().style.cursor = '' })
  }

  // Add house icons
  if (!map.hasImage('house-icon')) {
    map.addImage('house-icon', houseIcon)
  }
  if (houseFeatures.length > 0) {
    map.addSource('annotation-houses', {
      type: 'geojson',
      data: { type: 'FeatureCollection', features: houseFeatures },
    })
    map.addLayer({
      id: 'annotation-houses-layer',
      type: 'symbol',
      source: 'annotation-houses',
      layout: {
        'icon-image': 'house-icon',
        'icon-size': 0.5,
        'icon-allow-overlap': true,
      },
    })

    addLayerEvent(map, 'click', 'annotation-houses-layer', (e) => {
      const feature = e.features[0]
      const coords = feature.geometry.coordinates.slice()
      const desc = feature.properties?.description || 'Permission required'

      showPopup(map, coords, `<div style="font-family:sans-serif;max-width:240px;">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
          <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:#e65100;border-radius:50%;font-size:14px;">🏠</span>
          <strong style="color:#e65100;">Permission Required</strong>
        </div>
        <p style="margin:0;color:#555;">${escapeHtml(desc)}</p>
      </div>`)
    })
    addLayerEvent(map, 'mouseenter', 'annotation-houses-layer', () => { map.getCanvas().style.cursor = 'pointer' })
    addLayerEvent(map, 'mouseleave', 'annotation-houses-layer', () => { map.getCanvas().style.cursor = '' })
  }

  // Fit bounds to annotations + caves
  fitBounds()
}

function fitBounds () {
  const map = mapInstance.value
  if (!map) return

  const bounds = new maplibregl.LngLatBounds()
  let hasPoints = false

  if (props.annotation?.geojson?.features) {
    props.annotation.geojson.features.forEach(feature => {
      const geom = feature.geometry
      if (geom.type === 'Point') {
        bounds.extend(geom.coordinates)
        hasPoints = true
      } else if (geom.type === 'LineString') {
        geom.coordinates.forEach(coord => {
          bounds.extend(coord)
          hasPoints = true
        })
      }
    })
  }

  // Also include caves in bounds
  props.caves?.forEach(cave => {
    if (cave.location_lng && cave.location_lat) {
      bounds.extend([cave.location_lng, cave.location_lat])
      hasPoints = true
    }
  })

  // Include overlay extents (uses stored WGS84 bounds — no decode required)
  if (overlays.extendBounds(bounds)) {
    hasPoints = true
  }

  if (hasPoints) {
    map.fitBounds(bounds, { padding: 50, maxZoom: 15 })
  }
}

function cleanupMap () {
  const map = mapInstance.value
  if (!map) return

  // Remove active popup
  if (activePopup) {
    activePopup.remove()
    activePopup = null
  }

  const layers = ['annotation-caves-layer', 'annotation-lines-layer', 'annotation-lines-hit', 'annotation-parking-layer', 'annotation-houses-layer']
  const sources = ['annotation-caves', 'annotation-lines', 'annotation-parking', 'annotation-houses']
  const images = ['cave-icon', 'parking-icon', 'house-icon']

  layers.forEach(id => {
    if (map.getLayer(id)) map.removeLayer(id)
  })
  sources.forEach(id => {
    if (map.getSource(id)) map.removeSource(id)
  })
  images.forEach(id => {
    if (map.hasImage(id)) map.removeImage(id)
  })
}

function escapeHtml (str) {
  const div = document.createElement('div')
  div.textContent = str
  return div.innerHTML
}

watch(() => props.annotation, () => {
  if (mapInstance.value) {
    renderAnnotations()
  }
}, { deep: true })

// Re-render annotations after a style switch
watch(style, () => {
  const map = mapInstance.value
  if (!map) return
  map.once('style.load', () => {
    overlays.render()
    renderAnnotations()
  })
})

// Re-render overlays when the overlay list changes (e.g. data loads after mount)
watch(() => props.overlays, () => {
  if (mapInstance.value) {
    overlays.render()
    fitBounds()
  }
}, { deep: true })

onBeforeUnmount(() => {
  if (activePopup) activePopup.remove()
})
</script>

<style scoped>
.annotation-map-container {
  height: 400px;
}

.annotation-map-holder {
  padding: 0 !important;
  height: calc(100% - 48px);
  position: relative;
}
</style>

<style>
.annotation-popup .maplibregl-popup-content {
  background: #fff;
  border-radius: 8px;
  padding: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.annotation-popup .maplibregl-popup-tip {
  border-top-color: #fff;
}
</style>
