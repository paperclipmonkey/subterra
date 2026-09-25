import { describe, it, expect, beforeEach, vi } from 'vitest'
import { useMapOverlays } from '@/composables/useMapOverlays'

// Control the GeoTIFF decode so the composable can be tested without a real raster.
vi.mock('@/utilities/geotiffOverlay', () => ({
  parseGeoTiff: vi.fn(async () => ({
    dataUrl: 'data:image/png;base64,TEST',
    coordinates: [[0, 1], [1, 1], [1, 0], [0, 0]],
    bounds: [0, 0, 1, 1],
  })),
  boundsToCoordinates: (b) =>
    Array.isArray(b) && b.length === 4
      ? [[b[0], b[3]], [b[2], b[3]], [b[2], b[1]], [b[0], b[1]]]
      : null,
}))

import { parseGeoTiff } from '@/utilities/geotiffOverlay'

const flush = () => new Promise(resolve => setTimeout(resolve, 0))

function makeMap () {
  const layers = new Set()
  const sources = new Set()
  return {
    addSource: vi.fn(id => sources.add(id)),
    addLayer: vi.fn((def) => layers.add(def.id)),
    getLayer: vi.fn(id => (layers.has(id) ? { id } : undefined)),
    getSource: vi.fn(id => (sources.has(id) ? { id } : undefined)),
    removeLayer: vi.fn(id => layers.delete(id)),
    removeSource: vi.fn(id => sources.delete(id)),
    _layers: layers,
    _sources: sources,
  }
}

beforeEach(() => {
  parseGeoTiff.mockClear()
  global.fetch = vi.fn(async () => ({ arrayBuffer: async () => new ArrayBuffer(8) }))
})

describe('useMapOverlays', () => {
  it('renders overlays that are visible by default and skips hidden ones', async () => {
    const map = makeMap()
    const overlays = [
      { id: 1, name: 'A', url: 'a.tif', opacity: 0.5, visible_by_default: true },
      { id: 2, name: 'B', url: 'b.tif', visible_by_default: false },
    ]
    const o = useMapOverlays(() => map, () => overlays)
    o.render()
    await flush()

    expect(o.visibility[1]).toBe(true)
    expect(o.visibility[2]).toBe(false)
    expect(map.getSource('geotiff-overlay-1')).toBeTruthy()
    expect(map.getSource('geotiff-overlay-2')).toBeFalsy()
    // opacity from the record is applied
    expect(map.addLayer).toHaveBeenCalledWith(
      expect.objectContaining({ id: 'geotiff-overlay-1', paint: expect.objectContaining({ 'raster-opacity': 0.5 }) }),
      undefined,
    )
  })

  it('defaults opacity to 0.8 when the record omits it', async () => {
    const map = makeMap()
    const o = useMapOverlays(() => map, () => [{ id: 1, name: 'A', url: 'a.tif', visible_by_default: true }])
    o.render()
    await flush()
    expect(map.addLayer).toHaveBeenCalledWith(
      expect.objectContaining({ paint: expect.objectContaining({ 'raster-opacity': 0.8 }) }),
      undefined,
    )
  })

  it('toggles an overlay on and off', async () => {
    const map = makeMap()
    const overlays = [{ id: 3, name: 'C', url: 'c.tif', visible_by_default: false }]
    const o = useMapOverlays(() => map, () => overlays)

    await o.toggle(3, true)
    expect(o.visibility[3]).toBe(true)
    expect(map.getLayer('geotiff-overlay-3')).toBeTruthy()

    await o.toggle(3, false)
    expect(o.visibility[3]).toBe(false)
    expect(map.getLayer('geotiff-overlay-3')).toBeFalsy()
  })

  it('does not re-add an overlay that is already on the map', async () => {
    const map = makeMap()
    const overlays = [{ id: 1, name: 'A', url: 'a.tif', visible_by_default: true }]
    const o = useMapOverlays(() => map, () => overlays)
    await o.toggle(1, true)
    map.addSource.mockClear()
    await o.toggle(1, true)
    expect(map.addSource).not.toHaveBeenCalled()
  })

  it('shares a single fetch + decode across concurrent adds (in-flight de-dup)', async () => {
    const map = makeMap()
    const overlays = [{ id: 1, name: 'A', url: 'a.tif', visible_by_default: false }]
    const o = useMapOverlays(() => map, () => overlays)

    await Promise.all([o.toggle(1, true), o.toggle(1, true)])

    expect(parseGeoTiff).toHaveBeenCalledTimes(1)
    expect(global.fetch).toHaveBeenCalledTimes(1)
  })

  it('marks an overlay hidden when decoding fails', async () => {
    const map = makeMap()
    parseGeoTiff.mockRejectedValueOnce(new Error('bad tiff'))
    const errSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
    const overlays = [{ id: 9, name: 'Broken', url: 'x.tif', visible_by_default: true }]
    const o = useMapOverlays(() => map, () => overlays)

    o.render()
    await flush()

    expect(o.visibility[9]).toBe(false)
    expect(map.getLayer('geotiff-overlay-9')).toBeFalsy()
    errSpy.mockRestore()
  })

  it('inserts overlays beneath existing annotation layers', async () => {
    const map = makeMap()
    map._layers.add('annotation-parking-layer')
    const o = useMapOverlays(() => map, () => [{ id: 1, name: 'A', url: 'a.tif', visible_by_default: true }])
    o.render()
    await flush()
    expect(map.addLayer).toHaveBeenCalledWith(
      expect.objectContaining({ id: 'geotiff-overlay-1' }),
      'annotation-parking-layer',
    )
  })

  it('extendBounds extends a bounds object and reports whether anything was added', () => {
    const map = makeMap()
    const withBounds = useMapOverlays(() => map, () => [{ id: 1, bounds: [0, 0, 1, 1] }])
    const bounds = { extend: vi.fn() }
    expect(withBounds.extendBounds(bounds)).toBe(true)
    expect(bounds.extend).toHaveBeenCalledTimes(4)

    const noBounds = useMapOverlays(() => map, () => [{ id: 2, bounds: null }])
    const bounds2 = { extend: vi.fn() }
    expect(noBounds.extendBounds(bounds2)).toBe(false)
    expect(bounds2.extend).not.toHaveBeenCalled()
  })

  it('is a no-op when there is no map yet', async () => {
    const o = useMapOverlays(() => null, () => [{ id: 1, url: 'a.tif', visible_by_default: true }])
    expect(() => o.render()).not.toThrow()
    await o.toggle(1, false) // removeOverlay with no map
    await flush()
    expect(parseGeoTiff).not.toHaveBeenCalled()
  })

  it('tolerates a null overlay list', () => {
    const map = makeMap()
    const o = useMapOverlays(() => map, () => null)
    expect(o.overlayList.value).toEqual([])
    expect(() => o.render()).not.toThrow()
  })

  it('skips an overlay record with no url', async () => {
    const map = makeMap()
    const o = useMapOverlays(() => map, () => [{ id: 1, name: 'No file', visible_by_default: true }])
    o.render()
    await flush()
    expect(parseGeoTiff).not.toHaveBeenCalled()
    expect(map.addSource).not.toHaveBeenCalled()
  })

  it('ignores toggling an id that is not in the overlay list', async () => {
    const map = makeMap()
    const o = useMapOverlays(() => map, () => [{ id: 1, url: 'a.tif', visible_by_default: false }])
    await o.toggle(999, true)
    expect(map.addSource).not.toHaveBeenCalled()
    expect(o.visibility[999]).toBe(true)
  })

  it('re-uses the decoded cache when an overlay is toggled off then on again', async () => {
    const map = makeMap()
    const o = useMapOverlays(() => map, () => [{ id: 1, url: 'a.tif', visible_by_default: false }])
    await o.toggle(1, true)
    await o.toggle(1, false)
    await o.toggle(1, true)
    // Decoded exactly once despite three toggles
    expect(parseGeoTiff).toHaveBeenCalledTimes(1)
    expect(map.getLayer('geotiff-overlay-1')).toBeTruthy()
  })

  it('render is idempotent across repeated calls', async () => {
    const map = makeMap()
    const o = useMapOverlays(() => map, () => [{ id: 1, url: 'a.tif', visible_by_default: true }])
    o.render()
    await flush()
    o.render()
    await flush()
    expect(parseGeoTiff).toHaveBeenCalledTimes(1)
  })
})
