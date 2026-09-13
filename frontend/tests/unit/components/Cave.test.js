import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Cave from '@/components/Cave.vue'
import { api } from '@/plugins/api'

// Mock api plugin
vi.mock('@/plugins/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    }
}))

// Mock dependencies
const pushMock = vi.fn()
const replaceMock = vi.fn()
vi.mock('vue-router', () => ({
    useRouter: () => ({ push: pushMock, replace: replaceMock }),
    useRoute: () => ({ params: { id: '1' }, query: {} }),
    onBeforeRouteLeave: vi.fn()
}))

vi.mock('vuetify', () => ({
    useDisplay: () => ({
        smAndDown: { value: false }
    })
}))

// Mock Stores
const user = { id: 1, is_admin: false }
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({ user })
}))
vi.mock('@/stores/collections', () => ({
    useCollectionStore: () => ({})
}))
vi.mock('@/stores/notifications', () => ({
    useNotificationStore: () => ({
        showSuccess: vi.fn(),
        showError: vi.fn(),
    })
}))
vi.mock('@/stores/markAsDone', () => ({
    markCaveAsDone: vi.fn()
}))
vi.mock('@/stores/offline', () => ({
    useOfflineStore: () => ({
        isOnline: true,
        isPwa: false,
        downloadedCaveIds: [],
        downloadingCaveId: null,
        downloadProgress: 0,
        isCaveDownloaded: vi.fn(() => false),
        downloadedCaveCount: 0,
        getOfflineCave: vi.fn(() => Promise.resolve(null)),
    })
}))

// Mock MapLibre (canvas dependency causes issues in jsdom)
vi.mock('@indoorequal/vue-maplibre-gl', () => ({
    MglMap: { name: 'MglMap', template: '<div><slot /></div>' },
    MglNavigationControl: { template: '<div></div>' },
    MglMarker: { name: 'MglMarker', template: '<div><slot /></div>', props: ['coordinates'] },
    MglPopup: { name: 'MglPopup', template: '<div><slot /></div>' },
    MglFullscreenControl: { template: '<div></div>' },
    MglGeolocateControl: { template: '<div></div>' },
    useMap: () => ({ map: { fitBounds: vi.fn(), resize: vi.fn(), setCenter: vi.fn(), setZoom: vi.fn() }, isLoaded: true })
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

describe('Cave Component', () => {
    it('renders collections tab', async () => {
        api.get.mockResolvedValue({ data: { data: mockCave } })
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

    it('does not render system description if missing', async () => {
        // Mock API response with empty system description
        api.get.mockResolvedValue({
            data: {
                data: {
                    ...mockCave,
                    system: {
                        id: 1,
                        name: 'Test System',
                        description: '' // Empty description
                    }
                }
            }
        })

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
                    'vue-markdown': { template: '<div class="vue-markdown">MARKDOWN CONTENT</div>' },
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
                    'v-snackbar': true,
                    'CorrectionModal': true,
                    'CaveWeather': true
                }
            }
        })

        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Switch to system tab
        wrapper.vm.activeTab = 'system'
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('Test System')
        expect(wrapper.find('.vue-markdown').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('_No system description._')
    })

    it('renders descriptive call-to-action when description is a stub', async () => {
        api.get.mockResolvedValue({
            data: {
                data: {
                    ...mockCave,
                    description: 'Short stub' // Less than 50 chars
                }
            }
        })

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
                    'vue-markdown': { template: '<div class="vue-markdown">MARKDOWN CONTENT</div>' },
                    'v-alert': { template: '<div class="v-alert-stub"><slot /></div>' },
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
                    'v-snackbar': true,
                    'CorrectionModal': true,
                    'CaveWeather': true,
                    'MarkdownRenderer': true,
                    'MediaViewModal': true
                }
            }
        })

        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Should render the call to action
        expect(wrapper.text()).toContain("This cave's description is a stub")
    })

    it('does not render descriptive call-to-action when description is sufficiently long', async () => {
        api.get.mockResolvedValue({
            data: {
                data: {
                    ...mockCave,
                    description: 'This is a sufficiently long description that is over fifty characters in length, so it is not a stub.'
                }
            }
        })

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
                    'vue-markdown': { template: '<div class="vue-markdown">MARKDOWN CONTENT</div>' },
                    'v-alert': { template: '<div class="v-alert-stub"><slot /></div>' },
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
                    'v-snackbar': true,
                    'CorrectionModal': true,
                    'CaveWeather': true,
                    'MarkdownRenderer': true,
                    'MediaViewModal': true
                }
            }
        })

        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Should NOT render the call to action
        expect(wrapper.text()).not.toContain("This cave's description is a stub")
    })

    describe('media ordering', () => {
        const mountCave = async (caveData) => {
            api.get.mockResolvedValue({ data: { data: caveData } })
            const wrapper = mount(Cave, {
                global: {
                    stubs: {
                        'v-container': { template: '<div><slot /></div>' },
                        'v-row': { template: '<div><slot /></div>' },
                        'v-col': { template: '<div><slot /></div>' },
                        'v-card': { template: '<div><slot /></div>' },
                        'v-img': true,
                        'v-icon': true,
                        'v-btn': true,
                        'v-spacer': true,
                        'v-tabs': { template: '<div><slot /></div>' },
                        'v-tab': { template: '<div><slot /></div>' },
                        'v-badge': true,
                        'v-divider': true,
                        'v-window': { template: '<div><slot /></div>' },
                        'v-window-item': { template: '<div><slot /></div>' },
                        'v-alert': true,
                        'v-chip-group': true,
                        'v-chip': true,
                        'v-tooltip': true,
                        'v-list': true,
                        'v-list-item': true,
                        'v-list-item-title': true,
                        'v-list-item-subtitle': true,
                        'v-progress-circular': true,
                        'v-avatar': true,
                        'v-dialog': true,
                        'v-card-title': true,
                        'v-card-text': true,
                        'v-card-actions': true,
                        'v-textarea': true,
                        'v-form': true,
                        'v-snackbar': true,
                        'CaveTripListItem': true,
                        'CorrectionModal': true,
                        'CaveWeather': true,
                        'MarkdownRenderer': true,
                        'MediaViewModal': true,
                    }
                }
            })
            await new Promise(resolve => setTimeout(resolve, 0))
            await wrapper.vm.$nextTick()
            return wrapper
        }

        it('leads with the hero video, hero image and entrance photo', async () => {
            const wrapper = await mountCave({
                ...mockCave,
                media: [
                    { id: 1, type: 'other', url: 'other-a.jpg' },
                    { id: 2, type: 'entrance', url: 'entrance.jpg' },
                    { id: 3, type: 'other', url: 'other-b.jpg' },
                    { id: 4, type: 'hero', url: 'hero.jpg' },
                    { id: 5, type: 'hero_video', url: 'hero.mp4' },
                ],
            })

            expect(wrapper.vm.allMedia.map(m => m.id)).toEqual([5, 4, 2, 1, 3])
        })

        it('keeps cave media ahead of trip photos and system files', async () => {
            const wrapper = await mountCave({
                ...mockCave,
                media: [{ id: 1, type: 'hero', url: 'hero.jpg' }],
                trips: [{ id: 't1', name: 'A trip', participants: [], media: [{ id: 20, url: 'trip.jpg' }] }],
                system: {
                    id: 1,
                    name: 'Test System',
                    files: [{ id: 30, is_image: true, title: 'Historic', url: 'historic.jpg' }],
                },
            })

            expect(wrapper.vm.allMedia.map(m => m.id)).toEqual([1, 20, 30])
        })

        it('leaves ordering alone when no hero or entrance photo is set', async () => {
            const wrapper = await mountCave({
                ...mockCave,
                media: [
                    { id: 1, type: 'other', url: 'a.jpg' },
                    { id: 2, type: 'other', url: 'b.jpg' },
                ],
            })

            expect(wrapper.vm.allMedia.map(m => m.id)).toEqual([1, 2])
        })

        it('preserves the upload order of everything that is not a hero or entrance shot', async () => {
            // The prioritised types move to the front; the long tail must keep
            // its original relative order rather than being shuffled. Well past
            // the 10-element threshold where engines historically switched to an
            // unstable sort (Array#sort has been stable since ES2019).
            const others = Array.from({ length: 40 }, (_, i) => ({ id: 100 + i, type: 'other', url: `o${i}.jpg` }))
            const wrapper = await mountCave({
                ...mockCave,
                media: [
                    ...others.slice(0, 20),
                    { id: 4, type: 'hero', url: 'hero.jpg' },
                    ...others.slice(20),
                    { id: 2, type: 'entrance', url: 'entrance.jpg' },
                ],
            })

            const ids = wrapper.vm.allMedia.map(m => m.id)
            expect(ids.slice(0, 2)).toEqual([4, 2])
            expect(ids.slice(2)).toEqual(others.map(o => o.id))
        })
    })
})
