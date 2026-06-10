<template>
  <div class="discover-root">
    <!-- ─── Map area ─────────────────────────────────────────── -->
    <div class="map-area">
      <AppMap
        ref="mapRef"
        v-model="mapStyle"
        :center="[-2.5, 53.5]"
        :zoom="5.5"
        :max-zoom="16"
        geolocate
        @map:load="onMapLoad"
      />

      <!-- Top glass overlay: title + stats -->
      <div class="top-overlay">
        <div class="glass-card">
          <div class="glass-title">
            <v-icon :icon="mdiCompass" size="18" class="mr-1" />
            What's happening underground
          </div>
          <div v-if="!loading" class="stats-row">
            <span class="stat-pill">
              <v-icon size="11" :icon="mdiCalendarMonth" class="mr-1" />
              {{ monthlyTripCount }} trip{{ monthlyTripCount !== 1 ? 's' : '' }} this month
            </span>
            <span class="stat-pill">
              <v-icon size="11" :icon="mdiClockOutline" class="mr-1" />
              {{ monthlyHours }}h underground
            </span>
            <span class="stat-pill">
              <v-icon size="11" :icon="mdiAccountGroup" class="mr-1" />
              {{ activeCaversCount }} active caver{{ activeCaversCount !== 1 ? 's' : '' }}
            </span>
          </div>
          <div v-else class="stats-row">
            <v-progress-circular indeterminate color="white" size="14" width="2" />
          </div>
        </div>
      </div>

      <!-- Age legend -->
      <div class="legend-overlay">
        <span class="legend-item"><span class="legend-dot" style="background:#FF6B35;box-shadow:0 0 6px #FF6B35aa;" />This week</span>
        <span class="legend-item"><span class="legend-dot" style="background:#FFB300;" />This month</span>
        <span class="legend-item"><span class="legend-dot" style="background:#26C6DA;" />This year</span>
        <span class="legend-item"><span class="legend-dot" style="background:#78909C;" />Older</span>
      </div>
    </div>

    <!-- ─── Bottom strip ─────────────────────────────────────── -->
    <div class="bottom-strip">
      <div class="strip-header">
        <span class="strip-title">Recent Trips</span>
        <v-btn variant="text" density="compact" size="small" to="/trips" class="text-none text-primary see-all-btn">
          See all →
        </v-btn>
      </div>

      <div v-if="loading" class="d-flex justify-center align-center" style="height: 120px;">
        <v-progress-circular indeterminate color="primary" size="28" />
      </div>

      <div v-else ref="cardsScrollRef" class="cards-scroll">
        <div
          v-for="trip in recentTrips"
          :key="trip.id"
          :data-trip-id="trip.id"
          class="mini-card"
          :class="{ 'mini-card--selected': selectedTripId === trip.id }"
          @click="selectTrip(trip)"
        >
          <div
            class="mini-card-img"
            :style="{ background: getMiniCardBg(trip) }"
          >
            <div class="mini-card-gradient" />
            <div class="mini-card-content">
              <div class="mini-card-name">{{ trip.name }}</div>
              <div class="mini-card-cave">{{ trip.entrance?.name ?? '—' }}</div>
            </div>
            <div v-if="trip.duration" class="mini-card-duration">
              <v-icon size="9" :icon="mdiClockOutline" />
              {{ formatDuration(trip.duration) }}
            </div>
            <div v-if="!trip.entrance?.location_lat" class="mini-card-no-location" title="No map location">
              <v-icon size="11" :icon="mdiMapMarkerOff" />
            </div>
          </div>
          <div class="mini-card-date">{{ formatDate(trip.start_time) }}</div>
        </div>

        <!-- See-all card -->
        <div class="mini-card-see-all" @click="$router.push('/trips')">
          <v-icon size="32" :icon="mdiArrowRight" color="primary" />
          <div class="mini-see-all-label">All trips</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  mdiArrowRight,
  mdiCalendarMonth,
  mdiClockOutline,
  mdiAccountGroup,
  mdiCompass,
  mdiMapMarkerOff,
} from '@mdi/js'
import moment from 'moment'
import maplibregl from 'maplibre-gl'
import AppMap from '@/components/AppMap.vue'
import { api } from '@/plugins/api'

const router = useRouter()

// ── State ──────────────────────────────────────────────────────────────
const mapStyle = ref('https://api.maptiler.com/maps/hybrid/style.json?key=0gGMv4po9Mjrpd64A528')
const mapRef = ref(null)
const loading = ref(true)
const allTrips = ref([])
const selectedTripId = ref(null)

let map = null
let popup = null
let beaconMarker = null

const SOURCE_ID = 'trips-discover'
const LAYER_HALO = 'trips-halo'
const LAYER_DOT = 'trips-dot'

// ── Computed ────────────────────────────────────────────────────────────
const recentTrips = computed(() => allTrips.value.slice(0, 30))

const tripsWithCoords = computed(() =>
  allTrips.value.filter(t => t.entrance?.location_lat != null && t.entrance?.location_lng != null),
)

const now = Date.now()
const MS_IN_DAY = 86400000

const monthlyTripCount = computed(() => {
  const cutoff = now - 30 * MS_IN_DAY
  return allTrips.value.filter(t => new Date(t.start_time).getTime() >= cutoff).length
})

const monthlyHours = computed(() => {
  const cutoff = now - 30 * MS_IN_DAY
  const totalMinutes = allTrips.value
    .filter(t => new Date(t.start_time).getTime() >= cutoff && t.duration)
    .reduce((sum, t) => sum + t.duration, 0)
  return Math.round(totalMinutes / 60)
})

const activeCaversCount = computed(() => {
  const cutoff = now - 30 * MS_IN_DAY
  const ids = new Set()
  allTrips.value
    .filter(t => new Date(t.start_time).getTime() >= cutoff)
    .forEach(t => (t.participants || []).forEach(p => ids.add(p.id)))
  return ids.size
})

// ── Formatters ──────────────────────────────────────────────────────────
const formatDate = (date) => {
  const m = moment(date)
  return m.isValid() ? m.format('DD MMM YYYY') : '—'
}

const formatDuration = (minutes) => {
  if (!minutes) return ''
  const total = Math.round(minutes) // guard against fractional minutes
  const h = Math.floor(total / 60)
  const m = total % 60
  if (h > 0) return m > 0 ? `${h}h ${m}m` : `${h}h`
  return `${m}m`
}

const CARD_GRADIENTS = [
  'linear-gradient(135deg, #1a237e 0%, #0288d1 100%)',
  'linear-gradient(135deg, #1b5e20 0%, #00897b 100%)',
  'linear-gradient(135deg, #4a148c 0%, #880e4f 100%)',
  'linear-gradient(135deg, #e65100 0%, #f57f17 100%)',
  'linear-gradient(135deg, #0d47a1 0%, #006064 100%)',
]

const getMiniCardBg = (trip) => {
  const img = trip.media?.[0]?.url
    || trip.media?.[0]?.filename
    || trip.entrance_hero_image
    || trip.entrance_entrance_image
  if (img) return `url("${img}") center/cover no-repeat`
  const idx = (typeof trip.id === 'string' ? trip.id.charCodeAt(0) : (trip.id ?? 0)) % CARD_GRADIENTS.length
  return CARD_GRADIENTS[idx]
}

// ── Age color expression for MapLibre ──────────────────────────────────
const AGE_COLOR = [
  'case',
  ['<=', ['get', 'daysAgo'], 7],  '#FF6B35',
  ['<=', ['get', 'daysAgo'], 30], '#FFB300',
  ['<=', ['get', 'daysAgo'], 90], '#26C6DA',
  '#78909C',
]

// ── GeoJSON builder ─────────────────────────────────────────────────────
const buildGeoJSON = (trips) => ({
  type: 'FeatureCollection',
  features: trips
    .filter(t => t.entrance?.location_lat != null && t.entrance?.location_lng != null)
    .map(t => ({
      type: 'Feature',
      geometry: { type: 'Point', coordinates: [t.entrance.location_lng, t.entrance.location_lat] },
      properties: {
        id: t.id,
        name: t.name,
        entrance_name: t.entrance?.name ?? '',
        duration: t.duration ?? null,
        start_time: t.start_time,
        daysAgo: Math.floor((Date.now() - new Date(t.start_time).getTime()) / MS_IN_DAY),
        image_url: t.media?.[0]?.url || t.media?.[0]?.filename || t.entrance_hero_image || t.entrance_entrance_image || null,
        participant_names: (t.participants || []).slice(0, 3).map(p => p.name).join(', '),
        participant_count: (t.participants || []).length,
      },
    })),
})

// ── Map setup ───────────────────────────────────────────────────────────
const setupLayers = () => {
  if (map.getSource(SOURCE_ID)) {
    map.getSource(SOURCE_ID).setData(buildGeoJSON(tripsWithCoords.value))
    return
  }

  map.addSource(SOURCE_ID, {
    type: 'geojson',
    data: buildGeoJSON(tripsWithCoords.value),
    cluster: false,
  })

  // Halo glow ring
  map.addLayer({
    id: LAYER_HALO,
    type: 'circle',
    source: SOURCE_ID,
    paint: {
      'circle-radius': 16,
      'circle-color': AGE_COLOR,
      'circle-opacity': 0.2,
      'circle-stroke-width': 0,
    },
  })

  // Inner solid dot
  map.addLayer({
    id: LAYER_DOT,
    type: 'circle',
    source: SOURCE_ID,
    paint: {
      'circle-radius': 7,
      'circle-color': AGE_COLOR,
      'circle-opacity': 0.95,
      'circle-stroke-width': 2,
      'circle-stroke-color': '#ffffff',
      'circle-stroke-opacity': 0.9,
    },
  })

  // Click: popup
  map.on('click', LAYER_DOT, (e) => {
    const props = e.features[0].properties
    const coords = e.features[0].geometry.coordinates.slice()
    openPopup(coords, props)
    selectedTripId.value = props.id
    scrollToCard(props.id)
  })

  map.on('mouseenter', LAYER_DOT, () => { map.getCanvas().style.cursor = 'pointer' })
  map.on('mouseleave', LAYER_DOT, () => { map.getCanvas().style.cursor = '' })
}

const openPopup = (coords, props) => {
  const img = props.image_url
    ? `<img src="${props.image_url}" style="width:100%;height:96px;object-fit:cover;" />`
    : `<div style="height:60px;background:linear-gradient(135deg,#1a237e,#0288d1);"></div>`

  const duration = props.duration
    ? `<span style="margin-left:8px;">⏱ ${formatDuration(props.duration)}</span>`
    : ''

  const participants = props.participant_names
    ? `<div style="font-size:11px;color:#888;margin-top:4px;">👥 ${props.participant_names}${props.participant_count > 3 ? ` +${props.participant_count - 3}` : ''}</div>`
    : ''

  const html = `
    <div style="min-width:210px;max-width:240px;font-family:sans-serif;border-radius:10px;overflow:hidden;">
      ${img}
      <div style="padding:10px 12px 8px;">
        <div style="font-weight:700;font-size:14px;line-height:1.3;margin-bottom:3px;">${props.name}</div>
        <div style="font-size:12px;color:#666;margin-bottom:6px;">${props.entrance_name}</div>
        <div style="font-size:11px;color:#888;">📅 ${formatDate(props.start_time)}${duration}</div>
        ${participants}
      </div>
      <div style="padding:6px 12px 10px;border-top:1px solid #f0f0f0;">
        <a href="/trips/${props.id}" style="color:#1976D2;font-weight:600;font-size:13px;text-decoration:none;">View trip →</a>
      </div>
    </div>`

  popup?.remove()
  popup = new maplibregl.Popup({ offset: 12, maxWidth: '260px' })
    .setLngLat(coords)
    .setHTML(html)
    .addTo(map)
}

const addBeaconMarker = (trips) => {
  beaconMarker?.remove()
  const newest = trips.find(t => t.entrance?.location_lat != null && t.entrance?.location_lng != null)
  if (!newest) return

  const el = document.createElement('div')
  el.className = 'discover-beacon'
  el.innerHTML = `<div class="beacon-ring"></div><div class="beacon-core"></div>`

  beaconMarker = new maplibregl.Marker({ element: el, anchor: 'center' })
    .setLngLat([newest.entrance.location_lng, newest.entrance.location_lat])
    .addTo(map)

  el.addEventListener('click', () => {
    selectedTripId.value = newest.id
    scrollToCard(newest.id)
    openPopup(
      [newest.entrance.location_lng, newest.entrance.location_lat],
      {
        id: newest.id,
        name: newest.name,
        entrance_name: newest.entrance?.name ?? '',
        duration: newest.duration,
        start_time: newest.start_time,
        image_url: newest.media?.[0]?.url || newest.entrance_hero_image || null,
        participant_names: (newest.participants || []).slice(0, 3).map(p => p.name).join(', '),
        participant_count: (newest.participants || []).length,
      },
    )
  })
}

const fitMapBounds = (trips) => {
  if (!map || !trips.length) return
  const bounds = new maplibregl.LngLatBounds()
  trips.forEach(t => bounds.extend([t.entrance.location_lng, t.entrance.location_lat]))
  if (!bounds.isEmpty()) {
    map.fitBounds(bounds, { padding: 60, maxZoom: 9, animate: true, duration: 1200 })
  }
}

const onMapLoad = ({ map: m }) => {
  map = m
  map.on('style.load', setupLayers)
  setupLayers()
  if (tripsWithCoords.value.length) {
    addBeaconMarker(allTrips.value)
    fitMapBounds(tripsWithCoords.value)
  }
}

// ── Card interaction ─────────────────────────────────────────────────────
const cardsScrollRef = ref(null)

const scrollToCard = (id) => {
  if (!cardsScrollRef.value) return
  const el = cardsScrollRef.value.querySelector(`[data-trip-id="${id}"]`)
  el?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' })
}

const selectTrip = (trip) => {
  selectedTripId.value = trip.id
  if (!map) return

  if (trip.entrance?.location_lat != null && trip.entrance?.location_lng != null) {
    map.flyTo({
      center: [trip.entrance.location_lng, trip.entrance.location_lat],
      zoom: 12,
      duration: 1400,
      essential: true,
    })
    const props = {
      id: trip.id,
      name: trip.name,
      entrance_name: trip.entrance?.name ?? '',
      duration: trip.duration,
      start_time: trip.start_time,
      image_url: trip.media?.[0]?.url || trip.entrance_hero_image || null,
      participant_names: (trip.participants || []).slice(0, 3).map(p => p.name).join(', '),
      participant_count: (trip.participants || []).length,
    }
    setTimeout(() => {
      openPopup([trip.entrance.location_lng, trip.entrance.location_lat], props)
    }, 800)
  } else {
    router.push(`/trips/${trip.id}`)
  }
}

// ── Data fetching ────────────────────────────────────────────────────────
const loadTrips = async () => {
  loading.value = true
  try {
    const res = await api.get('/api/trips')
    allTrips.value = res.data?.data ?? res.data ?? []
  } catch (e) {
    console.error('Failed to load trips', e)
  } finally {
    loading.value = false
    if (map) {
      if (map.getSource(SOURCE_ID)) {
        map.getSource(SOURCE_ID).setData(buildGeoJSON(tripsWithCoords.value))
      } else {
        setupLayers()
      }
      addBeaconMarker(allTrips.value)
      fitMapBounds(tripsWithCoords.value)
    }
  }
}

loadTrips()

// ── Cleanup ───────────────────────────────────────────────────────────────
onUnmounted(() => {
  popup?.remove()
  beaconMarker?.remove()
  map = null
})
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

/* Popup chrome reset */
.maplibregl-popup .maplibregl-popup-content {
  padding: 0;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
  overflow: hidden;
}

.maplibregl-popup-close-button {
  font-size: 18px;
  padding: 4px 8px;
  color: #555;
  z-index: 10;
}
</style>

<style scoped lang="scss">
/* ── Root layout ─────────────────────────────────────── */
.discover-root {
  display: flex;
  flex-direction: column;
  height: calc(100dvh - 56px);
  overflow: hidden;
}

/* ── Map area ────────────────────────────────────────── */
.map-area {
  flex: 1;
  position: relative;
  min-height: 0;
}

/* ── Top glass overlay ───────────────────────────────── */
.top-overlay {
  position: absolute;
  top: 12px;
  left: 12px;
  /* Leave the right side clear for map controls (~50px) */
  right: 56px;
  z-index: 10;
  pointer-events: none;
}

.glass-card {
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 12px;
  padding: 10px 14px 10px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  pointer-events: auto;
  max-width: 480px;
}

.glass-title {
  color: #fff;
  font-weight: 700;
  font-size: 13px;
  display: flex;
  align-items: center;
  margin-bottom: 8px;
  letter-spacing: 0.01em;
}

.stats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.stat-pill {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  padding: 3px 9px;
  font-size: 11px;
  color: rgba(255, 255, 255, 0.95);
  display: flex;
  align-items: center;
  white-space: nowrap;
}

/* ── Age legend ──────────────────────────────────────── */
.legend-overlay {
  position: absolute;
  bottom: 10px;
  left: 10px;
  z-index: 10;
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border-radius: 8px;
  padding: 8px 10px;
  pointer-events: none;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 10px;
  color: rgba(255, 255, 255, 0.9);
}

.legend-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
  border: 1.5px solid rgba(255, 255, 255, 0.5);
}

/* ── Bottom strip ────────────────────────────────────── */
.bottom-strip {
  flex-shrink: 0;
  background: rgb(var(--v-theme-surface));
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08);
}

.strip-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px 4px;
}

.strip-title {
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.01em;
}

.see-all-btn {
  font-size: 12px !important;
}

/* ── Cards scroll strip ──────────────────────────────── */
.cards-scroll {
  display: flex;
  flex-direction: row;
  overflow-x: auto;
  gap: 10px;
  padding: 6px 12px 12px;
  scrollbar-width: none;

  &::-webkit-scrollbar {
    display: none;
  }
}

/* ── Mini trip card ──────────────────────────────────── */
.mini-card {
  flex-shrink: 0;
  width: 120px;
  cursor: pointer;
  transition: transform 0.15s ease, opacity 0.15s ease;

  &:hover {
    transform: translateY(-2px);
  }

  &--selected .mini-card-img {
    outline: 3px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }
}

.mini-card-img {
  width: 120px;
  height: 100px;
  border-radius: 8px;
  overflow: hidden;
  position: relative;
}

.mini-card-gradient {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.75) 0%, rgba(0, 0, 0, 0.1) 60%, transparent 100%);
}

.mini-card-content {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 5px 6px 6px;
}

.mini-card-name {
  font-size: 10px;
  font-weight: 700;
  color: #fff;
  line-height: 1.25;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.mini-card-cave {
  font-size: 9px;
  color: rgba(255, 255, 255, 0.8);
  margin-top: 1px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mini-card-duration {
  position: absolute;
  top: 5px;
  right: 5px;
  background: rgba(0, 0, 0, 0.55);
  border-radius: 8px;
  padding: 2px 5px;
  font-size: 9px;
  color: #fff;
  display: flex;
  align-items: center;
  gap: 2px;
}

.mini-card-no-location {
  position: absolute;
  top: 5px;
  left: 5px;
  background: rgba(0, 0, 0, 0.45);
  border-radius: 6px;
  padding: 2px 4px;
  color: rgba(255, 255, 255, 0.6);
  display: flex;
  align-items: center;
}

.mini-card-date {
  font-size: 10px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  margin-top: 4px;
  text-align: center;
}

/* ── See-all card ────────────────────────────────────── */
.mini-card-see-all {
  flex-shrink: 0;
  width: 80px;
  height: 100px;
  border-radius: 8px;
  border: 2px dashed rgba(var(--v-theme-primary), 0.4);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.05);
  }
}

.mini-see-all-label {
  font-size: 10px;
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
  margin-top: 4px;
}

/* ── Beacon HTML marker (outside scoped, injected into map DOM) ── */
</style>

<!-- Beacon styles must be global as they're injected into the map DOM -->
<style lang="scss">
.discover-beacon {
  width: 24px;
  height: 24px;
  position: relative;
  cursor: pointer;
}

.beacon-ring {
  position: absolute;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #FF6B35;
  opacity: 0;
  top: 0;
  left: 0;
  animation: beacon-pulse 2s ease-out infinite;
}

.beacon-core {
  position: absolute;
  width: 14px;
  height: 14px;
  background: #FF6B35;
  border: 2.5px solid #fff;
  border-radius: 50%;
  top: 5px;
  left: 5px;
  box-shadow: 0 0 8px rgba(255, 107, 53, 0.7);
}

@keyframes beacon-pulse {
  0%   { transform: scale(0.8); opacity: 0.7; }
  100% { transform: scale(2.8); opacity: 0; }
}
</style>
