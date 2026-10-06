import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { writeArrayBuffer } from 'geotiff'
import { parseGeoTiff, boundsToCoordinates } from '@/utilities/geotiffOverlay'

// parseGeoTiff rasterises through a <canvas>, which jsdom does not implement.
// Stub the 2D context + toDataURL + ImageData so the real decode/reproject/warp
// logic runs while the pixel output is captured harmlessly.
let originalGetContext
let originalToDataURL
let originalImageData

beforeAll(() => {
  originalGetContext = HTMLCanvasElement.prototype.getContext
  originalToDataURL = HTMLCanvasElement.prototype.toDataURL
  originalImageData = global.ImageData

  HTMLCanvasElement.prototype.getContext = () => ({ putImageData: () => {} })
  HTMLCanvasElement.prototype.toDataURL = () => 'data:image/png;base64,STUB'
  global.ImageData = class {
    constructor (data, width, height) {
      this.data = data
      this.width = width
      this.height = height
    }
  }
})

afterAll(() => {
  HTMLCanvasElement.prototype.getContext = originalGetContext
  HTMLCanvasElement.prototype.toDataURL = originalToDataURL
  global.ImageData = originalImageData
})

/**
 * Build a small in-memory GeoTIFF for a given CRS and extent.
 */
async function makeTiff ({
  width = 16,
  height = 16,
  samples = 3,
  bits = 8,
  geoKeys,
  west,
  south,
  east,
  north,
}) {
  const Ctor = bits === 16 ? Uint16Array : Uint8Array
  const values = new Ctor(width * height * samples)
  for (let i = 0; i < values.length; i++) {
    values[i] = bits === 16 ? (i * 257) % 65535 : i % 256
  }
  const metadata = {
    width,
    height,
    SamplesPerPixel: samples,
    BitsPerSample: Array(samples).fill(bits),
    PhotometricInterpretation: samples === 1 ? 1 : 2,
    ModelPixelScale: [(east - west) / width, (north - south) / height, 0],
    ModelTiepoint: [0, 0, 0, west, north, 0],
    GTRasterTypeGeoKey: 1,
    ...geoKeys,
  }
  return writeArrayBuffer(values, metadata)
}

const WGS84_KEYS = { GeographicTypeGeoKey: 4326, GTModelTypeGeoKey: 2 }
const BNG_KEYS = { ProjectedCSTypeGeoKey: 27700, GTModelTypeGeoKey: 1 }
const UTM30N_KEYS = { ProjectedCSTypeGeoKey: 32630, GTModelTypeGeoKey: 1 }
const UNKNOWN_KEYS = { ProjectedCSTypeGeoKey: 2154, GTModelTypeGeoKey: 1 } // French Lambert-93, unregistered

describe('parseGeoTiff', () => {
  it('decodes a WGS84 RGB GeoTIFF and returns corners + bounds', async () => {
    const buf = await makeTiff({ geoKeys: WGS84_KEYS, west: -2.63, south: 51.82, east: -2.6, north: 51.85 })
    const { dataUrl, coordinates, bounds } = await parseGeoTiff(buf)

    expect(dataUrl.startsWith('data:image/png')).toBe(true)
    expect(coordinates).toHaveLength(4)
    // corners are [lng, lat] pairs; ordered TL, TR, BR, BL
    coordinates.forEach(c => {
      expect(c[0]).toBeCloseTo(c[0], 5)
      expect(typeof c[1]).toBe('number')
    })
    const [west, south, east, north] = bounds
    expect(west).toBeLessThan(east)
    expect(south).toBeLessThan(north)
    // Roughly where we placed it
    expect(west).toBeGreaterThan(-3)
    expect(east).toBeLessThan(-2)
    expect(north).toBeGreaterThan(51)
  })

  it('reprojects a British National Grid (EPSG:27700) raster into WGS84 lng/lat', async () => {
    // A box around central England in BNG metres
    const buf = await makeTiff({ geoKeys: BNG_KEYS, west: 330000, south: 180000, east: 430000, north: 280000 })
    const { bounds } = await parseGeoTiff(buf)
    const [west, south, east, north] = bounds
    // Should land in a plausible GB lng/lat range, not raw metres
    expect(west).toBeGreaterThan(-6)
    expect(east).toBeLessThan(2)
    expect(south).toBeGreaterThan(50)
    expect(north).toBeLessThan(56)
  })

  it('supports UTM zones (auto-derived proj definition)', async () => {
    const buf = await makeTiff({ geoKeys: UTM30N_KEYS, west: 400000, south: 5700000, east: 500000, north: 5800000 })
    const { bounds } = await parseGeoTiff(buf)
    expect(bounds[0]).toBeLessThan(bounds[2])
    expect(bounds[1]).toBeLessThan(bounds[3])
  })

  it('throws a helpful error for an unsupported CRS', async () => {
    const buf = await makeTiff({ geoKeys: UNKNOWN_KEYS, west: 100000, south: 6000000, east: 200000, north: 6100000 })
    await expect(parseGeoTiff(buf)).rejects.toThrow(/Unsupported coordinate system/)
  })

  it('handles single-band (grayscale) rasters', async () => {
    const buf = await makeTiff({ samples: 1, geoKeys: WGS84_KEYS, west: -1, south: 52, east: 0, north: 53 })
    const { dataUrl } = await parseGeoTiff(buf)
    expect(dataUrl.startsWith('data:image/png')).toBe(true)
  })

  it('handles 4-band (RGBA) rasters', async () => {
    const buf = await makeTiff({ samples: 4, geoKeys: WGS84_KEYS, west: -1, south: 52, east: 0, north: 53 })
    const { dataUrl } = await parseGeoTiff(buf)
    expect(dataUrl.startsWith('data:image/png')).toBe(true)
  })

  it('normalises 16-bit samples without throwing', async () => {
    const buf = await makeTiff({ bits: 16, geoKeys: WGS84_KEYS, west: -1, south: 52, east: 0, north: 53 })
    const { dataUrl } = await parseGeoTiff(buf)
    expect(dataUrl.startsWith('data:image/png')).toBe(true)
  })

  it('handles a tall extent (portrait mercator aspect)', async () => {
    const buf = await makeTiff({ geoKeys: WGS84_KEYS, west: -0.1, south: 50, east: 0.1, north: 58 })
    const { bounds } = await parseGeoTiff(buf)
    expect(bounds[3] - bounds[1]).toBeGreaterThan(bounds[2] - bounds[0])
  })
})

describe('boundsToCoordinates', () => {
  it('converts [west, south, east, north] to TL, TR, BR, BL corners', () => {
    const coords = boundsToCoordinates([-2.65, 51.82, -2.60, 51.85])
    expect(coords).toEqual([
      [-2.65, 51.85],
      [-2.60, 51.85],
      [-2.60, 51.82],
      [-2.65, 51.82],
    ])
  })

  it('coerces string bounds to numbers', () => {
    const coords = boundsToCoordinates(['-2.65', '51.82', '-2.60', '51.85'])
    expect(coords[0]).toEqual([-2.65, 51.85])
  })

  it('returns null for malformed bounds', () => {
    expect(boundsToCoordinates(null)).toBeNull()
    expect(boundsToCoordinates([1, 2, 3])).toBeNull()
    expect(boundsToCoordinates('nope')).toBeNull()
  })
})
