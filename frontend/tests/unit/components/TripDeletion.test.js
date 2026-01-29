import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import Trip from '@/components/Trip.vue'
import { useRouter, useRoute } from 'vue-router'

// Mock dependencies
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({
        user: { id: 1 }, // Mock user matching participant
    })
}))

// Mock router
vi.mock('vue-router', () => ({
    useRouter: vi.fn(),
    useRoute: vi.fn(),
    RouterLink: {
        template: '<a><slot /></a>'
    }
}))

// Mock moment
vi.mock('moment', () => {
    const momentFn = (date) => ({
        format: () => 'formatted-date',
        diff: () => 3600000, // 1 hour
        isValid: () => true,
    })
    momentFn.duration = (diff) => ({
        asHours: () => 1,
        minutes: () => 0
    })
    return {
        default: momentFn
    }
})

// Mock vue-markdown-render
vi.mock('vue-markdown-render', () => ({
    default: { template: '<div>markdown</div>' }
}))

describe('Trip.vue', () => {
    let mockRouterPush
    let mockFetch

    const mockTrip = {
        id: 123,
        name: 'Test Trip',
        entrance: { id: 1, name: 'Entrance', slug: 'entrance' },
        exit: { id: 1, name: 'Entrance', slug: 'entrance' },
        start_time: '2023-01-01',
        end_time: '2023-01-01',
        visibility: 'public',
        description: 'desc',
        media: [],
        participants: [{ id: 1, name: 'Me' }]
    }

    beforeEach(() => {
        setActivePinia(createPinia())
        mockRouterPush = vi.fn()
        useRouter.mockReturnValue({
            push: mockRouterPush
        })
        useRoute.mockReturnValue({
            params: { id: '123' }
        })

        // Mock fetch for trip data and delete action
        mockFetch = vi.fn((url, options) => {
            if (options && options.method === 'DELETE') {
                return Promise.resolve({ ok: true })
            }
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve({ data: mockTrip })
            })
        })
        vi.stubGlobal('fetch', mockFetch)
    })

    it('redirects to /trips after successful deletion', async () => {
        const wrapper = mount(Trip, {
            global: {
                plugins: [createPinia()],
                stubs: {
                    'v-img': { template: '<div class="v-img-stub"><slot /><slot name="placeholder" /></div>' },
                    'v-icon': true,
                    'v-btn': true,
                    'v-spacer': true,
                    'v-container': { template: '<div class="v-container-stub"><slot /></div>' },
                    'v-chip': true,
                    'v-row': { template: '<div class="v-row-stub"><slot /></div>' },
                    'v-col': { template: '<div class="v-col-stub"><slot /></div>' },
                    'v-card': { template: '<div class="v-card-stub"><slot /></div>' },
                    'v-card-title': { template: '<div class="v-card-title-stub"><slot /></div>' },
                    'v-divider': true,
                    'v-card-text': { template: '<div class="v-card-text-stub"><slot /></div>' },
                    'v-hover': { template: '<div><slot :isHovering="false" :props="{}" /></div>' },
                    'v-progress-circular': true,
                    'v-list': { template: '<div><slot /></div>' },
                    'v-list-item': { template: '<div><slot /><slot name="prepend" /></div>' },
                    'v-avatar': { template: '<div><slot /></div>' },
                    'v-list-item-title': { template: '<div><slot /></div>' },
                    'v-list-item-subtitle': { template: '<div><slot /></div>' },
                    'v-dialog': {
                        template: '<div v-if="modelValue"><slot /><slot name="actions" /></div>',
                        props: ['modelValue']
                    },
                    'v-card-actions': true,
                    'router-link': true
                }
            }
        })

        // Wait for mount and data fetch
        await flushPromises()
        await wrapper.vm.$nextTick()

        // Assert GET fetch was called
        expect(mockFetch).toHaveBeenCalledWith('/api/trips/123', { headers: { 'Accept': 'application/json' } })

        // Verify trip loaded
        expect(wrapper.text()).toContain('Test Trip')

        // Simulate delete process
        // 1. Open dialog (setting helper ref directly since UI interaction is stubbed deeply)
        wrapper.vm.showDeleteConfirmDialog = true
        await wrapper.vm.$nextTick()

        // 2. Call confirmDelete directly
        await wrapper.vm.confirmDelete()

        // Wait for delete promise
        await flushPromises()

        // Assert fetch DELETE was called
        expect(mockFetch).toHaveBeenCalledWith('/api/trips/123', {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' }
        })

        // Assert router push was called with Correct path
        expect(mockRouterPush).toHaveBeenCalledWith('/trips')
    })
})
