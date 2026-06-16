import { fromArrayBuffer } from 'geotiff'
import proj4 from 'proj4'

// Common coordinate reference systems for UK / world survey data. geotiff.js
// reports the source CRS as an EPSG code in the image's geo keys. We warp the
// raster into Web Mercator (the basemap projection) so it aligns exactly with
// the MapLibre tiles, then hand MapLibre an `image` source. proj4 ships with
// WGS84 and Web Mercator, but projected national grids must be registered.
const PROJ_DEFINITIONS = {
  // British National Grid (OSGB36) — by far the most common for UK cave surveys
  27700: '+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +towgs84=446.448,-125.157,542.06,0.15,0.247,0.842,-20.489 +units=m +no_defs',
  // Irish Grid
  29903: '+proj=tmerc +lat_0=53.5 +lon_0=-8 +k=1.000035 +x_0=200000 +y_0=250000 +ellps=mod_airy +towgs84=482.5,-130.6,564.6,-1.042,-0.214,-0.631,8.15 +units=m +no_defs',
  // Web Mercator
  3857: '+proj=merc +a=6378137 +b=6378137 +lat_ts=0 +lon_0=0 +x_0=0 +y_0=0 +k=1 +units=m +nadgrids=@null +no_defs',
  3395: '+proj=merc +lon_0=0 +k=1 +x_0=0 +y_0=0 +datum=WGS84 +units=m +no_defs',
  // WGS84 lon/lat
  4326: '+proj=longlat +datum=WGS84 +no_defs',
}

const WGS84 = '+proj=longlat +datum=WGS84 +no_defs'
// EPSG:3857 — the projection the MapLibre basemap renders in
const WEB_MERCATOR = '+proj=merc +a=6378137 +b=6378137 +lat_ts=0 +lon_0=0 +x_0=0 +y_0=0 +k=1 +units=m +nadgrids=@null +no_defs'
const MERC_MAX = 20037508.342789244

// Largest dimension (px) of the warped overlay handed to MapLibre. Keeps the
// generated data URL and GPU texture manageable.
const MAX_OUTPUT_DIMENSION = 2048
// Largest dimension (px) the source raster is read at before warping.
const MAX_SOURCE_DIMENSION = 4096
// Control-point mesh density for the warp. Per-pixel reprojection would be too
// slow on large rasters, so we reproject a coarse grid and bilinearly
// interpolate the source coordinate within each cell (standard warp-mesh trick).
const WARP_GRID = 96

function getProjDefinition (epsgCode) {
  if (PROJ_DEFINITIONS[epsgCode]) {
    return PROJ_DEFINITIONS[epsgCode]
  }
  // Auto-derive UTM zones (EPSG 326xx northern, 327xx southern hemisphere)
  if (epsgCode >= 32601 && epsgCode <= 32660) {
    return `+proj=utm +zone=${epsgCode - 32600} +datum=WGS84 +units=m +no_defs`
  }
  if (epsgCode >= 32701 && epsgCode <= 32760) {
    return `+proj=utm +zone=${epsgCode - 32700} +south +datum=WGS84 +units=m +no_defs`
  }
  return null
}

function detectEpsg (geoKeys) {
  if (!geoKeys) return 4326
  if (geoKeys.ProjectedCSTypeGeoKey) return geoKeys.ProjectedCSTypeGeoKey
  if (geoKeys.GeographicTypeGeoKey) return geoKeys.GeographicTypeGeoKey
  return 4326
}

function mercToLngLat (x, y) {
  const lng = (x / MERC_MAX) * 180
  const latMerc = (y / MERC_MAX) * 180
  const lat = (180 / Math.PI) * (2 * Math.atan(Math.exp((latMerc * Math.PI) / 180)) - Math.PI / 2)
  return [lng, lat]
}

/**
 * Read the source samples and normalise bit depth, returning a flat RGBA-able
 * accessor plus the per-channel scale for >8-bit data.
 */
function sampleScale (rasters) {
  let max = 0
  for (let i = 0; i < rasters.length; i++) {
    if (rasters[i] > max) max = rasters[i]
  }
  return max > 255 ? 255 / max : 1
}

/**
 * Parse a GeoTIFF ArrayBuffer and warp it into Web Mercator so it overlays
 * correctly on a MapLibre map regardless of the source projection (e.g. EPSG
 * 27700 British National Grid, which is strongly non-linear vs Web Mercator
 * over large extents).
 *
 * @param {ArrayBuffer} arrayBuffer raw GeoTIFF bytes
 * @returns {Promise<{dataUrl: string, coordinates: number[][], bounds: number[]}>}
 *   dataUrl: a PNG data URL of the warped image
 *   coordinates: 4 corner [lng, lat] pairs (TL, TR, BR, BL) for the image source
 *   bounds: [west, south, east, north] in WGS84
 */
export async function parseGeoTiff (arrayBuffer) {
  const tiff = await fromArrayBuffer(arrayBuffer)
  const image = await tiff.getImage()

  const fullWidth = image.getWidth()
  const fullHeight = image.getHeight()
  const samples = image.getSamplesPerPixel()
  const epsgCode = detectEpsg(image.getGeoKeys())
  const sourceDef = epsgCode === 4326 ? WGS84 : getProjDefinition(epsgCode)
  if (!sourceDef) {
    throw new Error(`Unsupported coordinate system (EPSG:${epsgCode}). Please supply a GeoTIFF in WGS84, British National Grid, Irish Grid, or a UTM zone.`)
  }

  const [minX, minY, maxX, maxY] = image.getBoundingBox()

  // Read the source raster (downsampled if very large) into memory to sample
  const srcLongest = Math.max(fullWidth, fullHeight)
  const srcRatio = srcLongest > MAX_SOURCE_DIMENSION ? MAX_SOURCE_DIMENSION / srcLongest : 1
  const srcW = Math.max(1, Math.round(fullWidth * srcRatio))
  const srcH = Math.max(1, Math.round(fullHeight * srcRatio))
  const src = await image.readRasters({ interleave: true, width: srcW, height: srcH })
  const scale = sampleScale(src)

  // Web Mercator bounding box = axis-aligned bounds of the source corners
  const toMerc = (x, y) => proj4(sourceDef, WEB_MERCATOR, [x, y])
  const srcCorners = [toMerc(minX, minY), toMerc(maxX, minY), toMerc(maxX, maxY), toMerc(minX, maxY)]
  const mXs = srcCorners.map(c => c[0])
  const mYs = srcCorners.map(c => c[1])
  const mW = Math.min(...mXs)
  const mE = Math.max(...mXs)
  const mS = Math.min(...mYs)
  const mN = Math.max(...mYs)

  // Output dimensions: preserve the Mercator aspect ratio, cap the longest side
  const mercAspect = (mE - mW) / (mN - mS)
  let outW, outH
  if (mercAspect >= 1) {
    outW = MAX_OUTPUT_DIMENSION
    outH = Math.max(1, Math.round(MAX_OUTPUT_DIMENSION / mercAspect))
  } else {
    outH = MAX_OUTPUT_DIMENSION
    outW = Math.max(1, Math.round(MAX_OUTPUT_DIMENSION * mercAspect))
  }

  // Build the warp mesh: for each grid node, find the source pixel that the
  // output (Mercator) location samples from.
  const fromMerc = (x, y) => proj4(WEB_MERCATOR, sourceDef, [x, y])
  const gridCols = new Float64Array((WARP_GRID + 1) * (WARP_GRID + 1))
  const gridRows = new Float64Array((WARP_GRID + 1) * (WARP_GRID + 1))
  const spanX = maxX - minX
  const spanY = maxY - minY
  for (let gj = 0; gj <= WARP_GRID; gj++) {
    const my = mN - ((mN - mS) * gj) / WARP_GRID
    for (let gi = 0; gi <= WARP_GRID; gi++) {
      const mx = mW + ((mE - mW) * gi) / WARP_GRID
      const [sx, sy] = fromMerc(mx, my)
      // Source CRS coordinate -> downsampled source pixel (row 0 = north)
      gridCols[gj * (WARP_GRID + 1) + gi] = ((sx - minX) / spanX) * srcW
      gridRows[gj * (WARP_GRID + 1) + gi] = ((maxY - sy) / spanY) * srcH
    }
  }

  // Render each output pixel by bilinearly interpolating its source coordinate
  // from the mesh, then nearest-sampling the source raster.
  const out = new Uint8ClampedArray(outW * outH * 4)
  const stride = WARP_GRID + 1
  for (let py = 0; py < outH; py++) {
    const fy = (py / (outH - 1 || 1)) * WARP_GRID
    const j0 = Math.min(WARP_GRID - 1, Math.floor(fy))
    const tj = fy - j0
    for (let px = 0; px < outW; px++) {
      const fx = (px / (outW - 1 || 1)) * WARP_GRID
      const i0 = Math.min(WARP_GRID - 1, Math.floor(fx))
      const ti = fx - i0

      const a = j0 * stride + i0
      const b = a + 1
      const c = a + stride
      const d = c + 1
      // bilinear interp of source pixel coordinate
      const col = (gridCols[a] * (1 - ti) + gridCols[b] * ti) * (1 - tj) +
                  (gridCols[c] * (1 - ti) + gridCols[d] * ti) * tj
      const row = (gridRows[a] * (1 - ti) + gridRows[b] * ti) * (1 - tj) +
                  (gridRows[c] * (1 - ti) + gridRows[d] * ti) * tj

      const sc = Math.round(col)
      const sr = Math.round(row)
      const dst = (py * outW + px) * 4
      if (sc < 0 || sc >= srcW || sr < 0 || sr >= srcH) {
        out[dst + 3] = 0 // outside source extent -> transparent
        continue
      }
      const sp = (sr * srcW + sc) * samples
      if (samples === 1) {
        const v = src[sp] * scale
        out[dst] = v
        out[dst + 1] = v
        out[dst + 2] = v
        out[dst + 3] = 255
      } else {
        out[dst] = src[sp] * scale
        out[dst + 1] = src[sp + 1] * scale
        out[dst + 2] = src[sp + 2] * scale
        out[dst + 3] = samples >= 4 ? src[sp + 3] * scale : 255
      }
    }
  }

  const canvas = document.createElement('canvas')
  canvas.width = outW
  canvas.height = outH
  canvas.getContext('2d').putImageData(new ImageData(out, outW, outH), 0, 0)

  // Image-source corners: the Mercator bbox converted back to lng/lat (an
  // axis-aligned lat/lng rectangle), ordered TL, TR, BR, BL.
  const coordinates = [
    mercToLngLat(mW, mN),
    mercToLngLat(mE, mN),
    mercToLngLat(mE, mS),
    mercToLngLat(mW, mS),
  ]
  const lngs = coordinates.map(c => c[0])
  const lats = coordinates.map(c => c[1])
  const bounds = [Math.min(...lngs), Math.min(...lats), Math.max(...lngs), Math.max(...lats)]

  return {
    dataUrl: canvas.toDataURL('image/png'),
    coordinates,
    bounds,
  }
}

/**
 * Derive MapLibre image-source corner coordinates from a stored WGS84 bounds
 * array ([west, south, east, north]). Used to place an overlay's extent before
 * (or instead of) downloading and warping the full GeoTIFF.
 */
export function boundsToCoordinates (bounds) {
  if (!Array.isArray(bounds) || bounds.length !== 4) return null
  const [west, south, east, north] = bounds.map(Number)
  return [
    [west, north],
    [east, north],
    [east, south],
    [west, south],
  ]
}
