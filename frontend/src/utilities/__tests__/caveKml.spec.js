import { describe, it, expect, vi, afterEach } from 'vitest'
import { buildCavesKml, downloadCavesKml } from '@/utilities/caveKml'

const sampleCaves = [
  {
    id: 1,
    slug: 'gaping-gill',
    name: 'Gaping Gill',
    location_name: 'Ingleborough',
    location_country: 'England',
    location_lat: 54.1543,
    location_lng: -2.3812,
    hero_image: { url: 'https://media.example/gg.jpg' },
    tags: [{ tag: 'SRT' }, { tag: 'Classic' }],
    system: { name: 'Gaping Gill System', length: 16500, vertical_range: 105 },
  },
  {
    id: 2,
    slug: 'no-coords',
    name: 'Unmapped Cave',
    location_lat: null,
    location_lng: null,
    tags: [],
    system: null,
  },
]

describe('buildCavesKml', () => {
  it('produces a valid KML document with a placemark per located cave', () => {
    const kml = buildCavesKml(sampleCaves, { origin: 'https://app.example' })

    expect(kml).toContain('<?xml version="1.0" encoding="UTF-8"?>')
    expect(kml).toContain('<kml xmlns="http://www.opengis.net/kml/2.2">')
    // Only the cave with coordinates is included
    expect((kml.match(/<Placemark>/g) || []).length).toBe(1)
    expect(kml).not.toContain('Unmapped Cave')
  })

  it('writes coordinates as lng,lat,alt', () => {
    const kml = buildCavesKml(sampleCaves, { origin: 'https://app.example' })
    expect(kml).toContain('<coordinates>-2.3812,54.1543,0</coordinates>')
  })

  it('includes details, image and a link to the cave page in the description', () => {
    const kml = buildCavesKml(sampleCaves, { origin: 'https://app.example' })
    expect(kml).toContain('https://app.example/caves/gaping-gill')
    expect(kml).toContain('https://media.example/gg.jpg')
    expect(kml).toContain('16.5 km')
    expect(kml).toContain('105 m')
    expect(kml).toContain('SRT, Classic')
  })

  it('escapes XML special characters in names', () => {
    const kml = buildCavesKml([
      { ...sampleCaves[0], name: 'Tom & Jerry <Pot>' },
    ], { origin: 'https://app.example' })
    expect(kml).toContain('<name>Tom &amp; Jerry &lt;Pot&gt;</name>')
  })
})

describe('downloadCavesKml', () => {
  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  it('creates a KML blob and triggers a download with the given filename', () => {
    const createObjectURL = vi.fn(() => 'blob:mock-url')
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })

    const click = vi.fn()
    const origCreate = document.createElement.bind(document)
    vi.spyOn(document, 'createElement').mockImplementation((tag) => {
      const el = origCreate(tag)
      if (tag === 'a') el.click = click
      return el
    })

    downloadCavesKml(sampleCaves, { filename: 'my-caves.kml' })

    expect(createObjectURL).toHaveBeenCalledTimes(1)
    const blob = createObjectURL.mock.calls[0][0]
    expect(blob).toBeInstanceOf(Blob)
    expect(blob.type).toBe('application/vnd.google-earth.kml+xml')

    expect(click).toHaveBeenCalledTimes(1)
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:mock-url')
  })
})
