import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Cave from '@/components/Cave.vue'

// Mock dependencies
const pushMock = vi.fn()
vi.mock('vue-router', () => ({
    useRouter: () => ({ push: pushMock }),
    useRoute: () => ({ params: { id: '1' } })
}))

// Mock Stores
const user = { id: 1, is_admin: false }
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({ user })
}))
vi.mock('@/stores/collections', () => ({
    useCollectionStore: () => ({})
}))
vi.mock('@/stores/markAsDone', () => ({
    markCaveAsDone: vi.fn()
}))

// Mock MapLibre (canvas dependency causes issues in jsdom)
vi.mock('@indoorequal/vue-maplibre-gl', () => ({
    MglMap: { template: '<div></div>' },
    MglNavigationControl: { template: '<div></div>' },
    MglMarker: { template: '<div></div>' },
    MglGeolocateControl: { template: '<div></div>' },
    MglFullscreenControl: { template: '<div></div>' }
}))

// Mock API response
const mockCave = {
    id: 1,
    name: 'Test Cave',
    location_lat: 54.0,
    location_lng: -2.0,
    collections: [
        {
            id: 101,
            slug: 'yorkshire-classics',
            name: 'Yorkshire Classics',
            caves_count: 5,
            curr_cave_index: 0
        }
    ]
}

global.fetch = vi.fn(() =>
    Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ data: mockCave })
    })
)

describe('Cave Component', () => {
    it('renders collections tab', async () => {
        const wrapper = mount(Cave, {
            global: {
                stubs: {
                    'v-container': { template: '<div class="v-container"><slot /></div>' },
                    'v-row': { template: '<div class="v-row"><slot /></div>' },
                    'v-col': { template: '<div class="v-col"><slot /></div>' },
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-img': { template: '<div class="v-img"><slot /></div>' },
                    'v-icon': true,
                    'v-btn': true,
                    'v-spacer': true,
                    'v-tabs': { template: '<div class="v-tabs"><slot /></div>' },
                    'v-tab': { template: '<div class="v-tab"><slot /></div>' },
                    'v-badge': true,
                    'v-divider': true,
                    'v-window': { template: '<div class="v-window"><slot /></div>' },
                    'v-window-item': { template: '<div class="v-window-item"><slot /></div>' },
                    'vue-markdown': true,
                    'v-alert': true,
                    'v-chip-group': true,
                    'v-chip': true,
                    'v-tooltip': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'CaveTripListItem': true,
                    'v-progress-circular': true,
                    'v-avatar': true,
                    'v-dialog': true,
                    'v-card-title': true,
                    'v-card-text': true,
                    'v-card-actions': true,
                    'v-list-item-subtitle': true,
                    'v-textarea': true,
                    'v-form': true,
                    'v-snackbar': true
                }
            }
        })

        // Wait for fetch
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('Test Cave')
        expect(wrapper.text()).toContain('Collections') // Tab label

        // Simulate clicking Collections tab
        wrapper.vm.activeTab = 'collections'
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('Yorkshire Classics')
        expect(wrapper.text()).toContain('5 Caves')
    })
})
