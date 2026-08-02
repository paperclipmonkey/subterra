<template>
  <div class="map-container">
    <div v-if="!appStore.canSuggest" class="d-flex align-center justify-center bg-grey-lighten-4 h-100 flex-column text-center">
      <v-icon size="64" color="grey" class="mb-4" :icon="mdiLock" />
      <h3 class="text-h6 text-grey-darken-2 mb-2">Map View Locked</h3>
      <p class="text-body-1 text-grey-darken-1 mb-4" style="max-width: 300px;">
        Cave locations and map features are exclusive to approved club members.
      </p>
      <v-btn color="primary" :to="`/profile/${appStore.user.id}`">Join a Club</v-btn>
    </div>
    <AppMap v-else ref="mapRef" v-model="style" geolocate :center="lnglat" :zoom="zoom" :max-zoom="15" @map:load="onMapLoad" />
  </div>
</template>

<script setup>
import AppMap from '@/components/AppMap.vue'
import { mdiLock, mdiDownload } from '@mdi/js'
import { ref, watch, onUnmounted } from 'vue'
import { useAppStore } from '@/stores/app'
import { useCaveStore } from '@/stores/caves'
import { MapButtonControl } from '@/utilities/MapButtonControl'
import { downloadCavesKml } from '@/utilities/caveKml'
import maplibregl from 'maplibre-gl'

const appStore = useAppStore()
const caveStore = useCaveStore()

// Default to the lightweight global vector style — far fewer bytes on slow
// connections than satellite raster tiles. Users can switch to Satellite/OS
// via the style control.
const style = ref('https://api.maptiler.com/maps/topo/style.json?key=0gGMv4po9Mjrpd64A528')
const zoom = 5
const lnglat = [-2, 53]
const mapRef = ref(null)

let map = null
let popup = null

const SOURCE_ID = 'caves'
const CLUSTER_LAYER = 'caves-clusters'
const CLUSTER_COUNT_LAYER = 'caves-cluster-count'
const UNCLUSTERED_LAYER = 'caves-unclustered'

// Build a GeoJSON FeatureCollection from the current cave list
const buildGeoJSON = (caves) => ({
  type: 'FeatureCollection',
  features: caves
    .filter(c => c.location_lat != null && c.location_lng != null)
    .map(c => ({
      type: 'Feature',
      geometry: { type: 'Point', coordinates: [c.location_lng, c.location_lat] },
      properties: {
        id: c.id,
        slug: c.slug,
        name: c.name,
        location_name: c.location_name,
        hero_image_url: c.hero_image?.url || c.entrance_image?.url || null,
        length: c.system?.length ?? null,
        vertical_range: c.system?.vertical_range ?? null,
        lat: c.location_lat,
        lng: c.location_lng,
      },
    })),
})

const PIN_IMAGE_ID = 'cave-pin'

const addPinImage = () => new Promise((resolve) => {
  if (map.hasImage(PIN_IMAGE_ID)) return resolve()
  const size = 48
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 48 48">
      <path d="M24 4C16.268 4 10 10.268 10 18c0 10.667 14 26 14 26s14-15.333 14-26C38 10.268 31.732 4 24 4z"
            fill="#1976D2" stroke="#fff" stroke-width="2"/>
      <circle cx="24" cy="18" r="5" fill="#fff"/>
    </svg>`
  const img = new Image(size, size)
  img.onload = () => {
    if (!map.hasImage(PIN_IMAGE_ID)) map.addImage(PIN_IMAGE_ID, img, { pixelRatio: 2 })
    resolve()
  }
  img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg)
})

const addLayerDefs = () => {
  // Cluster circles
  map.addLayer({
    id: CLUSTER_LAYER,
    type: 'circle',
    source: SOURCE_ID,
    filter: ['has', 'point_count'],
    paint: {
      'circle-color': ['step', ['get', 'point_count'], '#4CAF50', 10, '#FF9800', 100, '#F44336'],
      'circle-radius': ['step', ['get', 'point_count'], 18, 10, 24, 100, 32],
      'circle-stroke-width': 2,
      'circle-stroke-color': '#fff',
    },
  })

  // Cluster count labels
  map.addLayer({
    id: CLUSTER_COUNT_LAYER,
    type: 'symbol',
    source: SOURCE_ID,
    filter: ['has', 'point_count'],
    layout: {
      'text-field': '{point_count_abbreviated}',
      'text-size': 13,
      'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
    },
    paint: { 'text-color': '#fff' },
  })

  // Individual cave pins (symbol layer — image must already be registered)
  map.addLayer({
    id: UNCLUSTERED_LAYER,
    type: 'symbol',
    source: SOURCE_ID,
    filter: ['!', ['has', 'point_count']],
    layout: {
      'icon-image': PIN_IMAGE_ID,
      'icon-size': 0.9,
      'icon-anchor': 'bottom',
      'icon-allow-overlap': true,
    },
  })
}

// Called on initial load and after every style swap
const setupLayers = async () => {
  // Wait for the pin image to be registered before touching layers
  await addPinImage()

  if (map.getSource(SOURCE_ID)) {
    // Source survived (shouldn't happen after a style swap, but be safe)
    map.getSource(SOURCE_ID).setData(buildGeoJSON(caveStore.caves))
    return
  }

  map.addSource(SOURCE_ID, {
    type: 'geojson',
    data: buildGeoJSON(caveStore.caves),
    cluster: true,
    clusterMaxZoom: 8,
    clusterRadius: 30,
  })

  addLayerDefs()

  // Click cluster → zoom in
  map.on('click', CLUSTER_LAYER, (e) => {
    const features = map.queryRenderedFeatures(e.point, { layers: [CLUSTER_LAYER] })
    if (!features.length) return
    const clusterId = features[0].properties.cluster_id
    map.getSource(SOURCE_ID).getClusterExpansionZoom(clusterId, (err, expandZoom) => {
      if (err) return
      map.easeTo({ center: features[0].geometry.coordinates, zoom: expandZoom })
    })
  })

  // Click individual cave → popup
  map.on('click', UNCLUSTERED_LAYER, (e) => {
    const props = e.features[0].properties
    const coords = e.features[0].geometry.coordinates.slice()

    const escHtml = (str) => String(str ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

    popup?.remove()
    popup = new maplibregl.Popup({ offset: 10 })
      .setLngLat(coords)
      .setHTML(`
        <div style="min-width:200px; font-family: sans-serif;">
          ${props.hero_image_url
            ? `<img src="${escHtml(props.hero_image_url)}" style="width:100%;height:80px;object-fit:cover;border-radius:4px 4px 0 0;" />`
            : ''}
          <div style="padding:8px 10px 4px;">
            <strong style="font-size:14px;">${escHtml(props.name)}</strong><br>
            <span style="font-size:12px;color:#666;">${escHtml(props.location_name ?? '')}</span>
          </div>
          <div style="padding:0 10px 6px;font-size:12px;color:#444;">
            ${props.length != null ? `Length: ${Math.round(props.length / 100) / 10} km` : ''}
            ${props.vertical_range != null ? ` &nbsp;Depth: ${escHtml(props.vertical_range)} m` : ''}
          </div>
          <div style="padding:4px 10px 10px;display:flex;gap:8px;align-items:center;">
            <a href="/caves/${encodeURIComponent(props.slug)}" style="font-size:12px;color:#1976D2;font-weight:600;text-decoration:none;">View</a>
            <a href="https://www.google.com/maps?q=${encodeURIComponent(props.lat)},${encodeURIComponent(props.lng)}" target="_blank" style="font-size:12px;color:#1976D2;text-decoration:none;">Google Maps</a>
            <a href="https://maps.apple.com/?q=${encodeURIComponent(props.lat)},${encodeURIComponent(props.lng)}" target="_blank" style="font-size:12px;color:#1976D2;text-decoration:none;">Apple Maps</a>
          </div>
        </div>
      `)
      .addTo(map)
  })

  // Pointer cursor helpers
  map.on('mouseenter', CLUSTER_LAYER, () => { map.getCanvas().style.cursor = 'pointer' })
  map.on('mouseleave', CLUSTER_LAYER, () => { map.getCanvas().style.cursor = '' })
  map.on('mouseenter', UNCLUSTERED_LAYER, () => { map.getCanvas().style.cursor = 'pointer' })
  map.on('mouseleave', UNCLUSTERED_LAYER, () => { map.getCanvas().style.cursor = '' })
}

const updateSource = (caves) => {
  const src = map?.getSource(SOURCE_ID)
  if (src) {
    src.setData(buildGeoJSON(caves))
  }
}

const fitBounds = (caves) => {
  const points = caves.filter(c => c.location_lat != null && c.location_lng != null)
  if (!points.length || !map) return
  const bounds = new maplibregl.LngLatBounds()
  points.forEach(c => bounds.extend([c.location_lng, c.location_lat]))
  map.fitBounds(bounds, { padding: 50, animate: false })
}

const onMapLoad = ({ map: loadedMap }) => {
  map = loadedMap

  // Export control — sits in the top-right stack, beneath the layer switcher
  map.addControl(
    new MapButtonControl({
      title: 'Export caves to Google Earth (KML)',
      iconSvg: `<svg style="width:20px;height:20px;margin:5px;" viewBox="0 0 24 24"><path fill="currentColor" d="${mdiDownload}" /></svg>`,
      onClick: () => downloadCavesKml(caveStore.caves),
    }),
    'top-right',
  )

  // style.load fires once per style (initial + every swap). Re-run full setup each time.
  map.on('style.load', setupLayers)

  // Initial load: style is already loaded when map:load fires, so call directly
  setupLayers()
  fitBounds(caveStore.caves)
}

// Keep source in sync when filters change
watch(() => caveStore.caves, (caves) => {
  updateSource(caves)
  fitBounds(caves)
})

onUnmounted(() => {
  popup?.remove()
  map = null
})
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

// Fills the flex column set up by the caves page in map mode; the
// min-height is a fallback so the map stays usable if that ever breaks.
.map-container {
  height: 100%;
  min-height: 420px;
}

// The map runs underneath the floating nav dock — keep MapLibre's
// attribution and bottom controls visible above it.
.map-container .maplibregl-ctrl-bottom-left,
.map-container .maplibregl-ctrl-bottom-right {
  bottom: 74px;
}

.maplibregl-popup .maplibregl-popup-content {
  padding: 0;
  background: #fff;
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,0.25);
}

.maplibregl-popup-content .maplibregl-popup-close-button {
  right: 6px;
  top: 4px;
  font-size: 16px;
  color: #fff;
  text-shadow: 0 1px 3px rgba(0,0,0,0.6);
}
</style>