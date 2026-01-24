import { mount, flushPromises } from '@vue/test-utils'
import CaveEdit from '@/components/CaveEdit.vue'
import { createRouter, createWebHistory } from 'vue-router'
import { vi, describe, it, expect, beforeEach } from 'vitest'

// Mock maplibre-gl
vi.mock('maplibre-gl', () => ({
    LngLat: {
        convert: vi.fn((coords) => ({ lng: coords[0] || 0, lat: coords[1] || 0 }))
    }
}))

vi.mock('@indoorequal/vue-maplibre-gl', () => ({
    MglMap: { template: '<div><slot/></div>' },
    MglNavigationControl: { template: '<div></div>' },
    MglMarker: { template: '<div></div>' },
    MglGeolocateControl: { template: '<div></div>' }
}))

// Mock fetch
global.fetch = vi.fn()

const router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/caves/:id/edit', component: CaveEdit }]
})

describe('CaveEdit.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        // Mock fetch implementation
        global.fetch.mockImplementation((url) => {
            if (url.includes('/api/caves/')) {
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({
                        data: {
                            name: 'Test Cave',
                            description: 'Cave Description',
                            access_info: 'Access Info',
                            location_name: 'Location',
                            location_country: 'Country',
                            system: { name: 'Test System', caves: [], description: 'System description', length: 0, vertical_range: 0, tags: [] },
                            tags: [], // Ensure tags is an array
                            trips: [],
                            location_lat: 0,
                            location_lng: 0,
                            hero_image: null,
                            entrance_image: null
                        }
                    })
                })
            } else if (url.includes('/api/tags')) {
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({
                        'Cave type': [{ tag: 'Cave', assignable: true }],
                        'Other': [{ tag: 'Something', assignable: true }]
                    })
                })
            }
            return Promise.resolve({ ok: true, json: () => Promise.resolve({}) })
        })
    })

    it('sends Accept: application/json header when saving', async () => {
        router.push('/caves/1/edit')
        await router.isReady()

        const wrapper = mount(CaveEdit, {
            global: {
                plugins: [router],
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-btn': { template: '<button><slot /></button>' },
                    'v-icon': { template: '<span><slot /></span>' },
                    'v-toolbar-title': { template: '<h1><slot /></h1>' },
                    'v-divider': { template: '<hr />' },
                    'v-form': {
                        template: '<form><slot /></form>',
                        methods: { validate: () => Promise.resolve({ valid: true }) }
                    },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<h2><slot /></h2>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-card-subtitle': { template: '<h3><slot /></h3>' },
                    'v-text-field': { template: '<input />' },
                    'v-textarea': { template: '<textarea />' },
                    'v-file-input': { template: '<input type="file" />' },
                    'v-chip-group': { template: '<div><slot /></div>' },
                    'v-chip': { template: '<span><slot /></span>' },
                    'v-snackbar': { template: '<div><slot /></div>' },
                    'vue-markdown': { template: '<div></div>' },
                    'mgl-map': { template: '<div><slot /></div>' },
                    'mgl-navigation-control': { template: '<div></div>' },
                    'mgl-geolocate-control': { template: '<div></div>' },
                    'mgl-marker': { template: '<div></div>' },
                    'CaveForm': { template: '<div></div>' }
                }
            }
        })

        await flushPromises() // Wait for component to mount and fetch data

        // Call saveCave directly
        await wrapper.vm.saveCave()

        await flushPromises()

        // Check the calls
        const putCall = global.fetch.mock.calls.find(call => call[1] && call[1].method === 'PUT')

        expect(putCall).toBeDefined()
        expect(putCall[1].headers).toHaveProperty('Accept', 'application/json')
    })
})
