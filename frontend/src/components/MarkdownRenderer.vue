<template>
  <div ref="container" class="markdown-renderer">
    <vue-markdown :source="source" :plugins="[geojsonPlugin, mermaidPlugin]" />

    <v-dialog v-model="showDiagramModal" max-width="95vw">
      <v-card class="rounded-lg d-flex flex-column" style="height: 90vh;">
        <v-toolbar density="comfortable" color="surface" flat>
          <v-toolbar-title class="text-subtitle-1 font-weight-bold">Diagram</v-toolbar-title>
          <v-spacer />
          <v-btn :icon="mdiMagnifyMinusOutline" variant="text" :disabled="diagramZoom <= 0.5" @click="zoomDiagram(-0.25)" />
          <span class="text-caption text-medium-emphasis mx-1" style="min-width: 48px; text-align: center;">
            {{ Math.round(diagramZoom * 100) }}%
          </span>
          <v-btn :icon="mdiMagnifyPlusOutline" variant="text" :disabled="diagramZoom >= 4" @click="zoomDiagram(0.25)" />
          <v-btn :icon="mdiRestore" variant="text" title="Reset zoom" @click="diagramZoom = 1" />
          <v-divider vertical class="mx-1" />
          <v-btn :icon="mdiClose" variant="text" @click="showDiagramModal = false" />
        </v-toolbar>
        <v-divider />
        <div class="diagram-scroll flex-grow-1">
          <div class="diagram-zoom" :style="{ transform: `scale(${diagramZoom})` }">
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div v-html="diagramSvg" />
          </div>
        </div>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
let mermaidInstance = null

const initMermaid = async () => {
    if (mermaidInstance) return mermaidInstance
    const mermaid = (await import('mermaid')).default
    mermaid.initialize({
        startOnLoad: false,
        theme: 'default',
        securityLevel: 'strict',
        // Render labels as native SVG <text> rather than HTML inside <foreignObject>.
        // The rendered SVG is run through DOMPurify with an SVG-only profile, which
        // strips foreignObject HTML and would otherwise leave every node label blank.
        htmlLabels: false,
        flowchart: { htmlLabels: false },
        class: { htmlLabels: false },
    })
    mermaidInstance = mermaid
    return mermaid
}

/**
 * Custom markdown-it plugin that converts ```mermaid code fences
 * into <div class="mermaid"> blocks so the mermaid library can render them.
 */
function mermaidPlugin(md) {
    const defaultFenceRenderer = md.renderer.rules.fence ||
        function (tokens, idx, options, env, self) {
            return self.renderToken(tokens, idx, options)
        }

    md.renderer.rules.fence = (tokens, idx, options, env, self) => {
        const token = tokens[idx]
        const info = (token.info || '').trim().toLowerCase()
        if (info === 'mermaid' || info.startsWith('mermaid')) {
            // Using v-pre-like behavior to ensure mermaid gets the raw text
            return `<div class="mermaid">${md.utils.escapeHtml(token.content)}</div>`
        }
        return defaultFenceRenderer(tokens, idx, options, env, self)
    }
}

/**
 * Custom markdown-it plugin that converts ```geojson code fences
 * into a placeholder div that renderGeoJSONMaps() will hydrate with a real MapLibre map.
 * The raw GeoJSON is stored as a percent-encoded data attribute to survive HTML serialisation.
 */
function geojsonPlugin(md) {
    const defaultFenceRenderer = md.renderer.rules.fence ||
        function (tokens, idx, options, env, self) {
            return self.renderToken(tokens, idx, options)
        }

    md.renderer.rules.fence = (tokens, idx, options, env, self) => {
        const token = tokens[idx]
        const info = (token.info || '').trim().toLowerCase()

        // Defer mermaid to mermaidPlugin if it's chained
        if (info === 'mermaid' || info.startsWith('mermaid')) {
            return defaultFenceRenderer(tokens, idx, options, env, self)
        }

        if (info === 'geojson') {
            // Encode the raw JSON so it survives being placed inside an HTML attribute
            const encoded = encodeURIComponent(token.content.trim())
            return `<div class="geojson-map" data-geojson="${encoded}">`
                + '<div class="geojson-placeholder"><span class="geojson-spinner"></span>Building map…</div>'
                + '</div>'
        }
        return defaultFenceRenderer(tokens, idx, options, env, self)
    }
}
</script>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import VueMarkdown from 'vue-markdown-render'
import DOMPurify from 'dompurify'
import { mdiClose, mdiMagnifyMinusOutline, mdiMagnifyPlusOutline, mdiRestore } from '@mdi/js'
import 'maplibre-gl/dist/maplibre-gl.css'

const props = defineProps({
    source: {
        type: String,
        default: ''
    },
    // True while the source is still being token-streamed. Map hydration is
    // deferred until streaming finishes: a half-streamed ```geojson fence is
    // not valid JSON yet, and initialising MapLibre on a DOM node that the
    // next chunk's re-render will replace wastes WebGL contexts.
    streaming: {
        type: Boolean,
        default: false
    }
})

const router = useRouter()
const container = ref(null)
const showDiagramModal = ref(false)
const diagramSvg = ref('')
const diagramZoom = ref(1)

const zoomDiagram = (delta) => {
    diagramZoom.value = Math.min(4, Math.max(0.5, Math.round((diagramZoom.value + delta) * 100) / 100))
}
const sanitizeSvg = (svg) => DOMPurify.sanitize(svg, { USE_PROFILES: { svg: true, svgFilters: true } })

// Track MapLibre instances so we can destroy them when the component unmounts
const mapInstances = []

/**
 * Intercept clicks on relative links inside the rendered markdown and use
 * Vue Router's push() instead of a full page reload.
 */
const attachSpaLinks = () => {
    if (!container.value) return
    container.value.querySelectorAll('a[href]').forEach(el => {
        const href = el.getAttribute('href')
        if (!href) return
        // Only intercept relative paths (starts with / but not //)
        if (href.startsWith('/') && !href.startsWith('//')) {
            el.addEventListener('click', (e) => {
                e.preventDefault()
                router.push(href)
            }, { once: false })
            el.classList.add('spa-link')
        }
    })
}

const renderMermaidDiagrams = async () => {
    // While streaming, the fence content is incomplete — rendering it would
    // flash "Mermaid error" bubbles until the diagram finishes arriving.
    if (props.streaming) return

    // Wait for ticks and a small delay to ensure vue-markdown-render has completed its DOM update
    await nextTick()
    await nextTick()
    await new Promise(resolve => setTimeout(resolve, 200))

    if (!container.value) return

    const nodes = container.value.querySelectorAll('.mermaid')
    if (nodes.length === 0) return

    try {
        const mermaid = await initMermaid()
        if (!mermaid) return

        for (const node of nodes) {
            // Skip if already processed
            if (node.getAttribute('data-processed')) continue

            // Unique ID for each diagram
            const id = 'mermaid-svg-' + Math.random().toString(36).substring(2, 11)

            // Mermaid.render expects the raw text
            const text = node.textContent

            try {
                const { svg } = await mermaid.render(id, text)
                const sanitizedSvg = sanitizeSvg(svg)
                node.innerHTML = sanitizedSvg
                node.setAttribute('data-processed', 'true')
                node.style.cursor = 'pointer'
                node.title = 'Click to enlarge'
                node.addEventListener('click', () => {
                    diagramSvg.value = sanitizedSvg
                    diagramZoom.value = 1
                    showDiagramModal.value = true
                })
            } catch (renderError) {
                console.error('Mermaid individual render error:', renderError)
                const errorDiv = document.createElement('div')
                errorDiv.className = 'text-error'
                errorDiv.textContent = `Mermaid error: ${renderError.message}`
                node.replaceChildren(errorDiv)
            }
        }
    } catch (e) {
        console.error('Mermaid rendering loop error:', e)
    }
}

onMounted(() => {
    renderMermaidDiagrams()
    attachSpaLinks()
})

onBeforeUnmount(() => {
    // Clean up all MapLibre map instances to free WebGL contexts
    mapInstances.forEach(m => m.remove())
    mapInstances.length = 0
})

// This API key is intentionally included in the frontend bundle. MapTiler keys are
// designed to be public-facing; this one is domain-restricted via the MapTiler
// dashboard so it can only be used from this application's origin, limiting any
// abuse potential even if the key is extracted from the bundle.
const MAPTILER_STYLE = 'https://api.maptiler.com/maps/outdoor/style.json?key=0gGMv4po9Mjrpd64A528'

const renderGeoJSONMaps = async () => {
    // While streaming, leave the animated "Building map…" placeholder in
    // place — the fence content is incomplete and would fail to parse.
    if (props.streaming) return

    await nextTick()
    await nextTick()

    if (!container.value) return

    const nodes = container.value.querySelectorAll('.geojson-map:not([data-map-init])')
    if (nodes.length === 0) return

    const maplibre = (await import('maplibre-gl')).default

    for (const node of nodes) {
        node.setAttribute('data-map-init', 'true')
        node.style.height = '320px'
        node.style.borderRadius = '12px'
        node.style.overflow = 'hidden'
        node.style.marginBottom = '1rem'

        let geojson
        try {
            geojson = JSON.parse(decodeURIComponent(node.getAttribute('data-geojson') || ''))
        } catch {
            node.innerHTML = '<div class="pa-4 text-error">Invalid GeoJSON</div>'
            continue
        }

        // Clear the placeholder text
        node.innerHTML = ''

        const map = new maplibre.Map({
            container: node,
            style: MAPTILER_STYLE,
            center: [-2, 53.5],
            zoom: 6,
            attributionControl: false,
        })

        mapInstances.push(map)

        map.addControl(new maplibre.AttributionControl({ compact: true }))
        map.addControl(new maplibre.NavigationControl({ showCompass: false }))

        map.on('load', () => {
            map.addSource('pip-data', { type: 'geojson', data: geojson })

            // Glow halo beneath each pin
            map.addLayer({
                id: 'pip-halo',
                type: 'circle',
                source: 'pip-data',
                filter: ['==', ['geometry-type'], 'Point'],
                paint: {
                    'circle-radius': 14,
                    'circle-color': '#1867c0',
                    'circle-opacity': 0.2,
                    'circle-stroke-width': 0,
                },
            })

            // Main pin circle
            map.addLayer({
                id: 'pip-points',
                type: 'circle',
                source: 'pip-data',
                filter: ['==', ['geometry-type'], 'Point'],
                paint: {
                    'circle-radius': 8,
                    'circle-color': '#1867c0',
                    'circle-stroke-width': 2.5,
                    'circle-stroke-color': '#fff',
                },
            })

            // Cave name labels
            map.addLayer({
                id: 'pip-labels',
                type: 'symbol',
                source: 'pip-data',
                filter: ['==', ['geometry-type'], 'Point'],
                layout: {
                    'text-field': ['coalesce', ['get', 'name'], ''],
                    'text-offset': [0, 1.4],
                    'text-anchor': 'top',
                    'text-size': 12,
                    'text-font': ['Open Sans SemiBold', 'Arial Unicode MS Bold'],
                    'text-max-width': 10,
                },
                paint: {
                    'text-color': '#111827',
                    'text-halo-color': 'rgba(255,255,255,0.95)',
                    'text-halo-width': 2,
                },
            })

            // Fit map to feature bounds
            const coords = []
            const collectCoords = (geom) => {
                if (!geom) return
                if (geom.type === 'Point') coords.push(geom.coordinates)
                else if (geom.type === 'LineString') coords.push(...geom.coordinates)
                else if (geom.type === 'Polygon') geom.coordinates.forEach(ring => coords.push(...ring))
                else if (geom.type === 'MultiPoint') coords.push(...geom.coordinates)
                else if (['MultiLineString', 'MultiPolygon'].includes(geom.type))
                    geom.coordinates.forEach(g => collectCoords({ type: geom.type.replace('Multi', ''), coordinates: g }))
            }

            if (geojson.type === 'FeatureCollection') geojson.features.forEach(f => collectCoords(f.geometry))
            else if (geojson.geometry) collectCoords(geojson.geometry)

            if (coords.length === 1) {
                map.flyTo({ center: coords[0], zoom: 11 })
            } else if (coords.length > 1) {
                const bounds = coords.reduce(
                    (b, c) => b.extend(c),
                    new maplibre.LngLatBounds(coords[0], coords[0])
                )
                map.fitBounds(bounds, { padding: 60, maxZoom: 12, duration: 0 })
            }

            // Click → popup with link to cave system. Build the DOM with
            // textContent / setAttribute so LLM-generated property values can
            // never reach an HTML or JS sink. The slug is also validated as a
            // safe URL slug before being placed in the href.
            map.on('click', 'pip-points', (e) => {
                const props = e.features[0].properties
                const name = props.name || 'Cave'
                const rawSlug = props.slug
                const safeSlug = typeof rawSlug === 'string' && /^[a-z0-9-]+$/i.test(rawSlug) ? rawSlug : null

                const root = document.createElement('div')
                root.style.fontFamily = 'Roboto, sans-serif'
                root.style.fontSize = '14px'

                const title = document.createElement('strong')
                title.textContent = String(name)
                root.appendChild(title)

                if (safeSlug) {
                    root.appendChild(document.createElement('br'))
                    const link = document.createElement('a')
                    link.href = `/cave-systems/${safeSlug}`
                    link.textContent = 'View system →'
                    link.style.color = '#1867c0'
                    link.style.fontWeight = '600'
                    link.style.cursor = 'pointer'
                    link.addEventListener('click', (ev) => {
                        ev.preventDefault()
                        router.push(`/cave-systems/${safeSlug}`)
                    })
                    root.appendChild(link)
                }

                new maplibre.Popup({ closeButton: true, maxWidth: '220px' })
                    .setLngLat(e.lngLat)
                    .setDOMContent(root)
                    .addTo(map)
            })

            map.on('mouseenter', 'pip-points', () => { map.getCanvas().style.cursor = 'pointer' })
            map.on('mouseleave', 'pip-points', () => { map.getCanvas().style.cursor = '' })
        })

        map.on('error', (e) => {
            console.error('Pip map error:', e)
        })
    }
}

// Watch must be declared AFTER renderGeoJSONMaps to avoid temporal dead zone errors.
// streaming is watched too so maps hydrate the moment the stream finishes.
watch(() => [props.source, props.streaming], () => {
    renderMermaidDiagrams()
    renderGeoJSONMaps()
    nextTick(attachSpaLinks)
}, { immediate: true })
</script>

<style scoped>
.markdown-renderer {
  font-family: 'Roboto', sans-serif;
  line-height: 1.75;
  color: #374151;
  max-width: 100%;
}

/* Mermaid diagram zoom modal — scrollable/pannable viewport with a zoomable
   inner layer so large diagrams can be read at full size. */
.diagram-scroll {
  overflow: auto;
  background: #fff;
  padding: 1.5rem;
}

.diagram-zoom {
  transform-origin: top center;
  transition: transform 0.12s ease;
  display: block;
  width: max-content;
  min-width: 100%;
  margin: 0 auto;
}

.diagram-zoom :deep(svg) {
  max-width: none !important;
  height: auto;
  display: block;
  margin: 0 auto;
}

.markdown-renderer :deep(h1),
.markdown-renderer :deep(h2),
.markdown-renderer :deep(h3),
.markdown-renderer :deep(h4),
.markdown-renderer :deep(h5),
.markdown-renderer :deep(h6) {
  color: #111827;
  font-weight: 700;
  margin-top: 2rem;
  margin-bottom: 1rem;
  line-height: 1.3;
}

.markdown-renderer :deep(h1) {
  font-size: 2.25rem;
  border-bottom: 3px solid #2F6852;
  padding-bottom: 0.5rem;
  margin-top: 0;
}

.markdown-renderer :deep(h2) {
  font-size: 1.875rem;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 0.25rem;
}

.markdown-renderer :deep(h3) {
  font-size: 1.5rem;
}

.markdown-renderer :deep(h4) {
  font-size: 1.25rem;
}

.markdown-renderer :deep(h5) {
  font-size: 1.125rem;
}

.markdown-renderer :deep(h6) {
  font-size: 1rem;
}

.markdown-renderer :deep(p) {
  margin-bottom: 1.25rem;
}

.markdown-renderer :deep(ul),
.markdown-renderer :deep(ol) {
  margin-bottom: 1.25rem;
  padding-left: 1.5rem;
}

.markdown-renderer :deep(li) {
  margin-bottom: 0.5rem;
}

.markdown-renderer :deep(blockquote) {
  border-left: 4px solid #2F6852;
  background: #f9fafb;
  padding: 1rem 1.5rem;
  margin: 1.5rem 0;
  font-style: italic;
  color: #4b5563;
  border-radius: 0 0.5rem 0.5rem 0;
}

.markdown-renderer :deep(pre) {
  background: #1e293b;
  color: #f8fafc;
  padding: 1.25rem;
  border-radius: 0.75rem;
  overflow-x: auto;
  margin: 1.5rem 0;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.markdown-renderer :deep(code) {
  font-family: 'Fira Code', 'Cascadia Code', 'Ubuntu Mono', monospace;
  font-size: 0.875rem;
}

.markdown-renderer :deep(:not(pre) > code) {
  background: #f1f5f9;
  color: #2F6852;
  padding: 0.2rem 0.4rem;
  border-radius: 0.375rem;
  font-weight: 500;
}

.markdown-renderer :deep(table) {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin: 1.5rem 0;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  overflow: hidden;
}

.markdown-renderer :deep(th) {
  background: #f8fafc;
  text-align: left;
  font-weight: 600;
  padding: 0.75rem 1rem;
  border-bottom: 2px solid #e5e7eb;
  color: #374151;
}

.markdown-renderer :deep(td) {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e5e7eb;
  color: #4b5563;
}

.markdown-renderer :deep(tr:last-child td) {
  border-bottom: none;
}

.markdown-renderer :deep(tr:nth-child(even)) {
  background: #f9fafb;
}

.markdown-renderer :deep(hr) {
  border: 0;
  border-top: 1px solid #e5e7eb;
  margin: 2.5rem 0;
}

.markdown-renderer :deep(.mermaid) {
  display: flex;
  justify-content: center;
  margin: 2rem 0;
  padding: 1.5rem;
  background: white;
  border-radius: 0.75rem;
  border: 1px solid #e5e7eb;
}

.markdown-renderer :deep(.mermaid svg) {
  max-width: 100%;
  height: auto;
}

/* Links (internal SPA links and external links) — styled to match the app's primary green */
.markdown-renderer :deep(a) {
  color: #2F6852;
  text-decoration: none;
  font-weight: 500;
  border-bottom: 1px solid transparent;
  transition: border-color 0.15s;
}

.markdown-renderer :deep(a:hover) {
  border-bottom-color: #2F6852;
}

/* GeoJSON map placeholder shown before MapLibre loads */
.markdown-renderer :deep(.geojson-map) {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  margin: 1.5rem 0;
  overflow: hidden;
  min-height: 320px;
}

.markdown-renderer :deep(.geojson-placeholder) {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  height: 320px;
  color: #9ca3af;
  font-size: 0.875rem;
}

.markdown-renderer :deep(.geojson-spinner) {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid #d1d5db;
  border-top-color: #2F6852;
  animation: geojson-spin 0.8s linear infinite;
}

@keyframes geojson-spin {
  to { transform: rotate(360deg); }
}

/* MapLibre GL popup tweaks */
.markdown-renderer :deep(.maplibregl-popup-content) {
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  padding: 10px 14px;
}
</style>
