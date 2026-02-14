import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import ActiveCalloutMap from '@/components/ActiveCalloutMap.vue'
import { nextTick } from 'vue'

// Mock maplibre-gl
vi.mock('maplibre-gl', () => ({
    default: {
        LngLatBounds: class {
            constructor() {
                this.extend = vi.fn()
                this.isEmpty = vi.fn(() => false)
            }
        },
    },
}))

// Mock @indoorequal/vue-maplibre-gl
vi.mock('@indoorequal/vue-maplibre-gl', () => ({
    MglMap: { template: '<div><slot /></div>' },
    MglFullscreenControl: { template: '<div></div>' },
    MglNavigationControl: { template: '<div></div>' },
    MglMarker: { name: 'MglMarker', template: '<div><slot /></div>', props: ['coordinates'] },
    MglPopup: { name: 'MglPopup', template: '<div><slot /></div>' },
    useMap: vi.fn(() => ({
        isLoaded: true,
        map: {
            resize: vi.fn(),
            fitBounds: vi.fn(),
        },
    })),
}))

describe('ActiveCalloutMap', () => {
    it('renders markers for valid callouts', async () => {
        const callouts = [
            { id: 1, lat: 54.1, lng: -2.3, cave_name: 'Cave 1' },
            { id: 2, lat: 54.2, lng: -2.4, cave_name: 'Cave 2' },
        ]

        const wrapper = mount(ActiveCalloutMap, {
            props: { callouts },
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                }
            }
        })

        await nextTick()

        const markers = wrapper.findAllComponents({ name: 'MglMarker' })
        // In some test setups, we might need to find by name or element
        // Since we mocked MglMarker, let's see if we can find them.
        // If findAllComponents doesn't work well with our mock, we can check the html.

        expect(markers.length).toBe(2)
        expect(markers[0].props('coordinates')).toEqual([-2.3, 54.1])
        expect(markers[1].props('coordinates')).toEqual([-2.4, 54.2])
    })

    it('filters out invalid callouts', async () => {
        const callouts = [
            { id: 1, lat: 54.1, lng: -2.3, cave_name: 'Cave 1' },
            { id: 2, lat: null, lng: null, cave_name: 'Invalid Cave' },
        ]

        const wrapper = mount(ActiveCalloutMap, {
            props: { callouts },
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                }
            }
        })

        await nextTick()

        const markers = wrapper.findAllComponents({ name: 'MglMarker' })
        expect(markers.length).toBe(1)
        expect(markers[0].props('coordinates')).toEqual([-2.3, 54.1])
    })
})
