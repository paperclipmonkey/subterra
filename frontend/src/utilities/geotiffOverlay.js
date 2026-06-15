import { fromArrayBuffer } from 'geotiff'
import proj4 from 'proj4'

// Common coordinate reference systems for UK / world survey data. geotiff.js
// reports the source CRS as an EPSG code in the image's geo keys; we reproject
// the image's corner coordinates into WGS84 (EPSG:4326) so MapLibre can place
// the raster via an `image` source. proj4 ships with WGS84 and Web Mercator,
// but projected national grids must be registered explicitly.
const PROJ_DEFINITIONS = {
  // British National Grid (OSGB36) — by far the most common for UK cave surveys
  27700: '+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +towgs84=446.448,-125.157,542.06,0.15,0.247,0.842,-20.489 +units=m +no_defs',
  // Irish Grid
  29903: '+proj=tmerc +lat_0=53.5 +lon_0=-8 +k=1.000035 +x_0=200000 +y_0=250000 +ellps=mod_airy +towgs84=482.5,-130.6,564.6,-1.042,-0.214,-0.631,8.15 +units=m +no_defs',
  // Web Mercator
  3857: '+proj=merc +a=6378137 +b=6378137 +lat_ts=0 +lon_0=0 +x_0=0 +y_0=0 +k=1 +units=m +nadgrids=@null +wgs84=0,0,0 +no_defs',
  3395: '+proj=merc +lon_0=0 +k=1 +x_0=0 +y_0=0 +datum=WGS84 +units=m +no_defs',
  // WGS84 lon/lat (identity)
  4326: '+proj=longlat +datum=WGS84 +no_defs',
}

const WGS84 = '+proj=longlat +datum=WGS84 +no_defs'

// Largest dimension (px) of the rasterised overlay. GeoTIFFs can be huge;
// resampling down keeps the generated data URL and GPU texture manageable.
const MAX_RASTER_DIMENSION = 2048

function getProjDefinition (epsgCode) {
  if (PROJ_DEFINITIONS[epsgCode]) {
    return PROJ_DEFINITIONS[epsgCode]
  }
  // Auto-derive UTM zones (EPSG 326xx northern, 327xx southern hemisphere)
  if (epsgCode >= 32601 && epsgCode <= 32660) {
    const zone = epsgCode - 32600
    return `+proj=utm +zone=${zone} +datum=WGS84 +units=m +no_defs`
  }
  if (epsgCode >= 32701 && epsgCode <= 32760) {
    const zone = epsgCode - 32700
    return `+proj=utm +zone=${zone} +south +datum=WGS84 +units=m +no_defs`
  }
  return null
}

function detectEpsg (geoKeys) {
  if (!geoKeys) return 4326
  // Projected CRS takes precedence over the geographic key when present
  if (geoKeys.ProjectedCSTypeGeoKey) return geoKeys.ProjectedCSTypeGeoKey
  if (geoKeys.GeographicTypeGeoKey) return geoKeys.GeographicTypeGeoKey
  return 4326
}

/**
 * Convert a [minX, minY, maxX, maxY] bounding box in the source CRS into the
 * four corner coordinates MapLibre's image source expects, in WGS84:
 * [top-left, top-right, bottom-right, bottom-left] as [lng, lat] pairs.
 */
function reprojectCorners (bbox, epsgCode) {
  const [minX, minY, maxX, maxY] = bbox

  if (epsgCode === 4326) {
    return [
      [minX, maxY],
      [maxX, maxY],
      [maxX, minY],
      [minX, minY],
    ]
  }

  const def = getProjDefinition(epsgCode)
  if (!def) {
    throw new Error(`Unsupported coordinate system (EPSG:${epsgCode}). Please supply a GeoTIFF in WGS84, British National Grid, or a UTM zone.`)
  }

  const project = (x, y) => proj4(def, WGS84, [x, y])
  return [
    project(minX, maxY),
    project(maxX, maxY),
    project(maxX, minY),
    project(minX, minY),
  ]
}

/**
 * Render the raster samples into an RGBA ImageData, normalising bit depth and
 * handling 1 (grayscale), 3 (RGB) and 4 (RGBA) sample layouts.
 */
function rastersToImageData (rasters, width, height, samples) {
  const imageData = new Uint8ClampedArray(width * height * 4)

  // Determine a normalisation scale for non-8-bit data (e.g. 16-bit scans)
  let max = 0
  for (let i = 0; i < rasters.length; i++) {
    if (rasters[i] > max) max = rasters[i]
  }
  const scale = max > 255 ? 255 / max : 1

  for (let p = 0; p < width * height; p++) {
    const src = p * samples
    const dst = p * 4
    if (samples === 1) {
      const v = rasters[src] * scale
      imageData[dst] = v
      imageData[dst + 1] = v
      imageData[dst + 2] = v
      imageData[dst + 3] = 255
    } else {
      imageData[dst] = rasters[src] * scale
      imageData[dst + 1] = rasters[src + 1] * scale
      imageData[dst + 2] = rasters[src + 2] * scale
      imageData[dst + 3] = samples >= 4 ? rasters[src + 3] * scale : 255
    }
  }

  return new ImageData(imageData, width, height)
}

/**
 * Parse a GeoTIFF ArrayBuffer into everything needed to overlay it on a
 * MapLibre map.
 *
 * @param {ArrayBuffer} arrayBuffer raw GeoTIFF bytes
 * @returns {Promise<{dataUrl: string, coordinates: number[][], bounds: number[]}>}
 *   dataUrl: a PNG data URL of the rasterised image
 *   coordinates: 4 corner [lng, lat] pairs (TL, TR, BR, BL) for the image source
 *   bounds: [west, south, east, north] in WGS84
 */
export async function parseGeoTiff (arrayBuffer) {
  const tiff = await fromArrayBuffer(arrayBuffer)
  const image = await tiff.getImage()

  const fullWidth = image.getWidth()
  const fullHeight = image.getHeight()
  const samples = image.getSamplesPerPixel()
  const geoKeys = image.getGeoKeys()
  const epsgCode = detectEpsg(geoKeys)
  const bbox = image.getBoundingBox()

  const corners = reprojectCorners(bbox, epsgCode)

  // Resample down to keep the texture/data URL small while preserving aspect
  const longestSide = Math.max(fullWidth, fullHeight)
  const ratio = longestSide > MAX_RASTER_DIMENSION ? MAX_RASTER_DIMENSION / longestSide : 1
  const outWidth = Math.max(1, Math.round(fullWidth * ratio))
  const outHeight = Math.max(1, Math.round(fullHeight * ratio))

  const rasters = await image.readRasters({
    interleave: true,
    width: outWidth,
    height: outHeight,
  })

  const imageData = rastersToImageData(rasters, outWidth, outHeight, samples)

  const canvas = document.createElement('canvas')
  canvas.width = outWidth
  canvas.height = outHeight
  const ctx = canvas.getContext('2d')
  ctx.putImageData(imageData, 0, 0)

  // WGS84 axis-aligned bounds derived from the (possibly skewed) reprojected corners
  const lngs = corners.map(c => c[0])
  const lats = corners.map(c => c[1])
  const bounds = [Math.min(...lngs), Math.min(...lats), Math.max(...lngs), Math.max(...lats)]

  return {
    dataUrl: canvas.toDataURL('image/png'),
    coordinates: corners,
    bounds,
  }
}

/**
 * Derive MapLibre image-source corner coordinates from a stored WGS84 bounds
 * array ([west, south, east, north]). Used to place an overlay's extent before
 * (or instead of) downloading and rasterising the full GeoTIFF.
 */
export function boundsToCoordinates (bounds) {
  if (!Array.isArray(bounds) || bounds.length !== 4) return null
  const [west, south, east, north] = bounds
  return [
    [west, north],
    [east, north],
    [east, south],
    [west, south],
  ]
}
