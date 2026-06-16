import { reactive, computed } from 'vue'
import { parseGeoTiff, boundsToCoordinates } from '@/utilities/geotiffOverlay'

/**
 * Shared GeoTIFF-overlay rendering for any MapLibre map (the cave-system map
 * viewer and the individual cave map both use this).
 *
 * @param {() => import('maplibre-gl').Map|null} getMap getter for the live map instance
 * @param {() => Array} getOverlays getter for the overlay records (from the API)
 */
export function useMapOverlays (getMap, getOverlays) {
  // overlayId -> bool (user/default visibility) and -> bool (decoding in flight)
  const visibility = reactive({})
  const loading = reactive({})
  // overlayId -> { dataUrl, coordinates } so toggling/style-switching never re-fetches
  const cache = new Map()

  const overlayList = computed(() => getOverlays() || [])

  const layerId = (id) => `geotiff-overlay-${id}`
  const sourceId = (id) => `geotiff-overlay-${id}`

  // Keep overlays beneath annotation markers/lines so those stay clickable on top
  function beforeLayer (map) {
    const candidates = [
      'annotation-caves-layer',
      'annotation-lines-layer',
      'annotation-parking-layer',
      'annotation-houses-layer',
    ]
    return candidates.find(id => map.getLayer(id))
  }

  async function addOverlay (overlay) {
    const map = getMap()
    if (!map || !overlay?.url) return
    if (map.getLayer(layerId(overlay.id))) return // already added

    let decoded = cache.get(overlay.id)
    if (!decoded) {
      loading[overlay.id] = true
      try {
        const buffer = await (await fetch(overlay.url)).arrayBuffer()
        const parsed = await parseGeoTiff(buffer)
        decoded = { dataUrl: parsed.dataUrl, coordinates: parsed.coordinates }
        cache.set(overlay.id, decoded)
      } catch (err) {
        console.error(`Failed to load GeoTIFF overlay "${overlay.name}"`, err)
        loading[overlay.id] = false
        visibility[overlay.id] = false
        return
      }
      loading[overlay.id] = false
    }

    // The style may have been torn down while we were decoding
    const m = getMap()
    if (!m || m.getSource(sourceId(overlay.id))) return

    m.addSource(sourceId(overlay.id), {
      type: 'image',
      url: decoded.dataUrl,
      coordinates: decoded.coordinates,
    })
    m.addLayer({
      id: layerId(overlay.id),
      type: 'raster',
      source: sourceId(overlay.id),
      paint: {
        'raster-opacity': overlay.opacity ?? 0.8,
        'raster-fade-duration': 0,
      },
    }, beforeLayer(m))
  }

  function removeOverlay (id) {
    const map = getMap()
    if (!map) return
    if (map.getLayer(layerId(id))) map.removeLayer(layerId(id))
    if (map.getSource(sourceId(id))) map.removeSource(sourceId(id))
  }

  // Add every overlay that should currently be visible (idempotent)
  function render () {
    const map = getMap()
    if (!map) return
    overlayList.value.forEach(ov => {
      if (visibility[ov.id] === undefined) {
        visibility[ov.id] = ov.visible_by_default !== false
      }
      if (visibility[ov.id]) addOverlay(ov)
    })
  }

  async function toggle (id, visible) {
    visibility[id] = visible
    if (visible) {
      const overlay = overlayList.value.find(o => o.id === id)
      if (overlay) await addOverlay(overlay)
    } else {
      removeOverlay(id)
    }
  }

  // Extend a maplibregl.LngLatBounds with overlay extents (uses stored bounds —
  // no decode required). Returns true if anything was added.
  function extendBounds (bounds) {
    let extended = false
    overlayList.value.forEach(ov => {
      const coords = boundsToCoordinates(ov.bounds)
      if (coords) {
        coords.forEach(c => bounds.extend(c))
        extended = true
      }
    })
    return extended
  }

  return { overlayList, visibility, loading, render, toggle, removeOverlay, extendBounds }
}
