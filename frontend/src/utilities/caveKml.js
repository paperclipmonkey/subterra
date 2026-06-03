// Build and download a KML (Google Earth) file from a list of caves.
// Runs entirely client-side off the current (filtered) cave list, so the
// export always reflects whatever search/filters the user has applied.

// Escape a string for use in XML text/attribute content.
const escapeXml = (value) => String(value ?? '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&apos;')

// Wrap HTML in CDATA, neutralising any stray ]]> that would close it early.
const cdata = (html) => `<![CDATA[${String(html ?? '').replace(/]]>/g, ']]&gt;')}]]>`

const formatLength = (length) =>
  length != null ? `${Math.round((length / 1000) * 10) / 10} km` : null

const formatVertical = (vertical) =>
  vertical != null ? `${vertical} m` : null

// Build the HTML shown in Google Earth's placemark balloon.
const buildDescription = (cave, caveUrl) => {
  const imageUrl = cave.hero_image?.url || cave.entrance_image?.url || cave.hero_video?.poster_url || null
  const rows = []

  const location = [cave.location_name, cave.location_country].filter(Boolean).join(', ')
  if (location) rows.push(`<tr><td><b>Location</b></td><td>${escapeXml(location)}</td></tr>`)

  if (cave.system?.name) rows.push(`<tr><td><b>System</b></td><td>${escapeXml(cave.system.name)}</td></tr>`)

  const length = formatLength(cave.system?.length)
  if (length) rows.push(`<tr><td><b>Length</b></td><td>${escapeXml(length)}</td></tr>`)

  const vertical = formatVertical(cave.system?.vertical_range)
  if (vertical) rows.push(`<tr><td><b>Vertical range</b></td><td>${escapeXml(vertical)}</td></tr>`)

  const tags = (cave.tags || []).map(t => t.tag).filter(Boolean)
  if (tags.length) rows.push(`<tr><td><b>Tags</b></td><td>${escapeXml(tags.join(', '))}</td></tr>`)

  const parts = []
  if (imageUrl) {
    parts.push(`<p><img src="${escapeXml(imageUrl)}" style="max-width:320px;width:100%;" /></p>`)
  }
  if (rows.length) {
    parts.push(`<table cellpadding="4">${rows.join('')}</table>`)
  }
  parts.push(`<p><a href="${escapeXml(caveUrl)}">View cave on Subterra</a></p>`)

  return parts.join('')
}

// Resolve the public URL of a cave's detail page.
const cavePageUrl = (cave, origin) => `${origin}/caves/${cave.slug}`

// Generate a KML document string from caves that have coordinates.
export const buildCavesKml = (caves, { origin = '', documentName = 'Subterra Caves' } = {}) => {
  const placemarks = caves
    .filter(c => c.location_lat != null && c.location_lng != null)
    .map((cave) => {
      const url = cavePageUrl(cave, origin)
      const alt = cave.location_alt != null ? cave.location_alt : 0
      const coordinates = `${cave.location_lng},${cave.location_lat},${alt}`
      return [
        '    <Placemark>',
        `      <name>${escapeXml(cave.name)}</name>`,
        `      <description>${cdata(buildDescription(cave, url))}</description>`,
        '      <Point>',
        `        <coordinates>${coordinates}</coordinates>`,
        '      </Point>',
        '    </Placemark>',
      ].join('\n')
    })
    .join('\n')

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<kml xmlns="http://www.opengis.net/kml/2.2">',
    '  <Document>',
    `    <name>${escapeXml(documentName)}</name>`,
    placemarks,
    '  </Document>',
    '</kml>',
    '',
  ].join('\n')
}

// Build the KML for the given caves and trigger a browser download.
export const downloadCavesKml = (caves, { filename = 'subterra-caves.kml' } = {}) => {
  const origin = typeof window !== 'undefined' ? window.location.origin : ''
  const kml = buildCavesKml(caves, { origin })

  const blob = new Blob([kml], { type: 'application/vnd.google-earth.kml+xml' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}
