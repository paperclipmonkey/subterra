import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import IncidentDetails from '@/pages/admin/incidents/[id].vue'
import axios from 'axios'
import moment from 'moment'

// Mock dependencies
vi.mock('axios', () => {
    const mock = {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        create: vi.fn().mockReturnThis(),
        interceptors: {
            request: { use: vi.fn(), eject: vi.fn() },
            response: { use: vi.fn(), eject: vi.fn() }
        }
    }
    return {
        default: mock,
        ...mock
    }
})
vi.mock('moment')
vi.mock('vue-router', () => ({
    useRoute: () => ({ params: { id: '1' } }),
    useRouter: () => ({ push: vi.fn() })
}))
vi.mock('@indoorequal/vue-maplibre-gl', () => ({
    MglMap: { template: '<div><slot /></div>' },
    MglNavigationControl: { template: '<div></div>' },
    MglMarker: { template: '<div><slot /></div>' },
    MglPopup: { template: '<div><slot /></div>' },
}))
vi.mock('maplibre-gl', () => ({
    default: {
        Map: vi.fn(),
        Marker: vi.fn(),
        Popup: vi.fn(),
        NavigationControl: vi.fn(),
    }
}))

describe('IncidentDetails.vue', () => {
    let wrapper
    const mockIncident = {
        id: 1,
        status: 'open',
        controller: null,
        police_log_number: null,
        notes: [],
        created_at: '2023-01-01T10:00:00Z',
        callout: {
            cave: { name: 'Test Cave' },
            participants: [{ id: 1, name: 'John Doe', phone: '123' }],
            callout_time: '2023-01-01T09:00:00Z',
            status: 'triggered'
        }
    }

    beforeEach(() => {
        axios.get.mockResolvedValue({ data: { data: mockIncident } })
        moment.mockReturnValue({ format: () => '10:00', fromNow: () => '1 hour ago' })
    })

    it('renders incident details correctly', async () => {
        wrapper = mount(IncidentDetails, {
            global: {
                stubs: {
                    'v-banner': { template: '<div><slot></slot></div>' },
                    'v-icon': true,
                    'v-spacer': true,
                    'v-btn': true,
                    'v-card': { template: '<div><slot></slot></div>' },
                    'v-card-title': { template: '<div><slot></slot></div>' },
                    'v-card-text': { template: '<div><slot></slot></div>' },
                    'v-row': { template: '<div><slot></slot></div>' },
                    'v-col': { template: '<div><slot></slot></div>' },
                    'v-timeline': { template: '<div><slot></slot></div>' },
                    'v-timeline-item': { template: '<div><slot></slot><slot name="opposite"></slot></div>' },
                    'v-checkbox': true,
                    'v-text-field': true,
                    'v-alert': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-content': true,
                    'v-list-item-title': true,
                    'v-list-item-subtitle': true,
                    'v-divider': true,
                    'v-textarea': true,
                    'v-card-actions': true,
                    'v-dialog': true,
                    'v-container': { template: '<div><slot></slot></div>' },
                    'v-list-item-avatar': true, // Add missing stub
                    'MglMap': { template: '<div class="mgl-map-stub"><slot /></div>' },
                    'MglNavigationControl': true,
                    'MglMarker': { template: '<div class="mgl-marker-stub"><slot /></div>' },
                    'MglPopup': { template: '<div class="mgl-popup-stub"><slot /></div>' },
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() },
                    $route: { params: { id: '1' } },
                    $router: { push: vi.fn() }
                }
            }
        })

        await flushPromises()
        expect(wrapper.text()).toContain('INCIDENT #1')
        expect(wrapper.text()).toContain('Test Cave')
    })

    it('shows acknowledge button when controller is null', async () => {
        axios.get.mockResolvedValue({ data: { data: { ...mockIncident, controller: null } } })

        wrapper = mount(IncidentDetails, {
            global: {
                stubs: {
                    'v-banner': { template: '<div><slot></slot></div>' },
                    'v-icon': true,
                    'v-spacer': true,
                    'v-btn': { template: '<button class="v-btn-stub" @click="$emit(\'click\')"><slot></slot></button>' },
                    'v-container': { template: '<div><slot></slot></div>' },
                    'v-card': { template: '<div><slot></slot></div>' },
                    'v-card-title': { template: '<div><slot></slot></div>' },
                    'v-card-text': { template: '<div><slot></slot></div>' },
                    'v-row': { template: '<div><slot></slot></div>' },
                    'v-col': { template: '<div><slot></slot></div>' },
                    'v-timeline': true,
                    'v-timeline-item': true, // Added stub
                    'v-checkbox': true,
                    'v-text-field': true,
                    'v-alert': { template: '<div><slot></slot></div>' }, // Render alert content
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-content': true,
                    'v-list-item-title': true,
                    'v-list-item-subtitle': true,
                    'v-divider': true,
                    'v-textarea': true,
                    'v-card-actions': true,
                    'v-dialog': true,
                    'v-list-item-avatar': true,
                    'MglMap': true,
                    'MglNavigationControl': true,
                    'MglMarker': true,
                    'MglPopup': true,
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() },
                    $route: { params: { id: '1' } },
                    $router: { push: vi.fn() }
                }
            }
        })

        await flushPromises()
        expect(wrapper.text()).toContain('ACKNOWLEDGE & TAKE CONTROL')
    })

    it('calls acknowledge api and re-fetches on click', async () => {
        axios.post.mockResolvedValue({})

        const fetchSpy = vi.spyOn(IncidentDetails.methods, 'fetchIncident')

        wrapper = mount(IncidentDetails, {
            global: {
                stubs: {
                    'v-banner': { template: '<div><slot></slot></div>' },
                    'v-icon': true,
                    'v-spacer': true,
                    'v-btn': { template: '<button class="v-btn-stub" @click="$emit(\'click\')"><slot></slot></button>' },
                    'v-container': { template: '<div><slot></slot></div>' },
                    'v-card': { template: '<div><slot></slot></div>' },
                    'v-card-title': { template: '<div><slot></slot></div>' },
                    'v-card-text': { template: '<div><slot></slot></div>' },
                    'v-row': { template: '<div><slot></slot></div>' },
                    'v-col': { template: '<div><slot></slot></div>' },
                    'v-timeline': true,
                    'v-timeline-item': true,
                    'v-checkbox': true,
                    'v-text-field': true,
                    'v-alert': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-content': true,
                    'v-list-item-title': true,
                    'v-list-item-subtitle': true,
                    'v-divider': true,
                    'v-textarea': true,
                    'v-card-actions': true,
                    'v-dialog': true,
                    'v-list-item-avatar': true,
                    'MglMap': true,
                    'MglNavigationControl': true,
                    'MglMarker': true,
                    'MglPopup': true,
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() },
                    $route: { params: { id: '1' } },
                    $router: { push: vi.fn() }
                }
            }
        })
        await flushPromises() // Initial fetch

        const btn = wrapper.find('.v-btn-stub')
        await btn.trigger('click')

        expect(axios.post).toHaveBeenCalledWith('/api/admin/incidents/1/acknowledge')
        // Should have been called twice: once mounted, once after acknowledge
        expect(fetchSpy.mock.calls.length).toBeGreaterThanOrEqual(2)
    })

    it('shows user safe banner when callout is cancelled', async () => {
        axios.get.mockResolvedValue({
            data: {
                data: {
                    ...mockIncident,
                    callout: { ...mockIncident.callout, status: 'cancelled' }
                }
            }
        })

        wrapper = mount(IncidentDetails, {
            global: {
                stubs: {
                    // Minimal stubs needed for this test
                    'v-container': { template: '<div><slot></slot></div>' },
                    'v-banner': { template: '<div><slot></slot></div>' },
                    'v-icon': true,
                    'v-spacer': true,
                    'v-btn': true,
                    'v-alert': { template: '<div class="alert"><slot></slot></div>' },
                    'v-row': { template: '<div><slot></slot></div>' },
                    'v-col': { template: '<div><slot></slot></div>' },
                    'v-card': { template: '<div><slot></slot></div>' },
                    'v-card-title': { template: '<div><slot></slot></div>' },
                    'v-card-text': { template: '<div><slot></slot></div>' },
                    'v-timeline': true,
                    'v-timeline-item': true, // Add missing stub
                    'v-checkbox': true,
                    'v-text-field': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-content': true,
                    'v-list-item-title': true,
                    'v-list-item-subtitle': true,
                    'v-divider': true,
                    'v-textarea': true,
                    'v-card-actions': true,
                    'v-dialog': true,
                    'v-list-item-avatar': true,
                    'MglMap': true,
                    'MglNavigationControl': true,
                    'MglMarker': true,
                    'MglPopup': true,
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() },
                    $route: { params: { id: '1' } },
                    $router: { push: vi.fn() }
                }
            }
        })

        await flushPromises()
        expect(wrapper.text()).toContain('USER MARKED SAFE')
    })
})
