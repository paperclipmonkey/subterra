import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import CaveListMap from '@/components/CaveListMap.vue'

// Mock useAppStore
const { mockUseAppStore } = vi.hoisted(() => {
    return { mockUseAppStore: vi.fn() }
})

vi.mock('@/stores/app', () => ({
    useAppStore: mockUseAppStore
}))

// Mock Caves Store
vi.mock('@/stores/caves', () => ({
    useCaveStore: () => ({
        caves: []
    })
}))

// Mock Map Libraries
vi.mock('@indoorequal/vue-maplibre-gl', () => ({
    MglMap: { name: 'MglMap', template: '<div><slot /></div>' },
    MglNavigationControl: { template: '<div></div>' },
    MglMarker: { name: 'MglMarker', template: '<div><slot /></div>', props: ['coordinates'] },
    MglPopup: { name: 'MglPopup', template: '<div><slot /></div>' },
    MglFullscreenControl: { template: '<div></div>' },
    MglGeolocateControl: { template: '<div></div>' },
    useMap: () => ({ map: { fitBounds: vi.fn(), resize: vi.fn(), setCenter: vi.fn(), setZoom: vi.fn() }, isLoaded: true })
}))

vi.mock('maplibre-gl', () => ({
    default: {
        LngLatBounds: vi.fn(() => ({
            extend: vi.fn()
        }))
    }
}))

const globalStubs = {
    'v-card': { template: '<div><slot /></div>' },
    'v-card-title': { template: '<div><slot /></div>' },
    'v-card-subtitle': { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    'v-card-actions': { template: '<div><slot /></div>' },
    'v-btn': { template: '<button><slot /></button>' },
    'v-icon': { template: '<i></i>' },
    'v-img': { template: '<img><slot /></img>' },
    'router-link': { template: '<a><slot /></a>' }
}

describe('CaveListMap', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    it('renders map for approved users', () => {
        // Mock Approved User
        mockUseAppStore.mockReturnValue({
            user: { id: 1, clubs: [{ status: 'approved' }] },
            canSuggest: true
        })

        const wrapper = mount(CaveListMap, {
            global: {
                stubs: globalStubs
            }
        })
        expect(wrapper.findComponent({ name: 'MglMap' }).exists()).toBe(true)
        expect(wrapper.text()).not.toContain('Map View Locked')
    })

    it('renders locked state for unapproved users', () => {
        // Mock Unapproved User
        mockUseAppStore.mockReturnValue({
            user: { id: 1, clubs: [] },
            canSuggest: false
        })

        const wrapper = mount(CaveListMap, {
            global: {
                stubs: globalStubs
            }
        })

        expect(wrapper.findComponent({ name: 'MglMap' }).exists()).toBe(false)
        expect(wrapper.text()).toContain('Map View Locked')
    })

    it('zooms into a cluster when it is clicked', async () => {
        mockUseAppStore.mockReturnValue({
            user: { id: 1, clubs: [{ status: 'approved' }] },
            canSuggest: true
        })

        // A minimal stand-in for the MapLibre instance AppMap hands over on load.
        const handlers = {}
        const source = { setData: vi.fn(), getClusterExpansionZoom: vi.fn().mockResolvedValue(11) }
        let sourceAdded = false
        const map = {
            hasImage: () => true,
            addControl: vi.fn(),
            addLayer: vi.fn(),
            addSource: vi.fn(() => { sourceAdded = true }),
            getSource: () => (sourceAdded ? source : undefined),
            on: vi.fn((event, layerOrHandler, handler) => {
                if (handler) handlers[`${event}:${layerOrHandler}`] = handler
            }),
            queryRenderedFeatures: () => [{ properties: { cluster_id: 42 }, geometry: { coordinates: [-2.3, 54.1] } }],
            easeTo: vi.fn(),
            fitBounds: vi.fn(),
            getCanvas: () => ({ style: {} }),
        }

        const wrapper = mount(CaveListMap, {
            global: { stubs: { ...globalStubs, AppMap: { name: 'AppMap', template: '<div />', emits: ['map:load'] } } }
        })
        wrapper.findComponent({ name: 'AppMap' }).vm.$emit('map:load', { map })
        await flushPromises()

        await handlers['click:caves-clusters']({ point: { x: 10, y: 10 } })
        await flushPromises()

        // MapLibre 5's getClusterExpansionZoom returns a Promise; the old callback
        // form was never called back, so clusters did nothing when clicked.
        expect(source.getClusterExpansionZoom).toHaveBeenCalledWith(42)
        expect(map.easeTo).toHaveBeenCalledWith({ center: [-2.3, 54.1], zoom: 11 })
    })
})
