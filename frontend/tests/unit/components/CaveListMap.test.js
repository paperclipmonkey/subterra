import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
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
    MglFullscreenControl: { template: '<div></div>' },
    MglNavigationControl: { template: '<div></div>' },
    MglMarker: { template: '<div><slot /></div>' },
    MglPopup: { template: '<div><slot /></div>' },
    MglGeolocateControl: { template: '<div></div>' },
    useMap: () => ({
        map: { resize: vi.fn(), fitBounds: vi.fn() },
        isLoaded: true
    })
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
            user: { id: 1, is_approved: true }
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
            user: { id: 1, is_approved: false }
        })

        const wrapper = mount(CaveListMap, {
            global: {
                stubs: globalStubs
            }
        })

        expect(wrapper.findComponent({ name: 'MglMap' }).exists()).toBe(false)
        expect(wrapper.text()).toContain('Map View Locked')
    })
})
