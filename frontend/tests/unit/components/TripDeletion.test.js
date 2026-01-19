import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
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
    return {
        default: (date) => ({
            format: () => 'formatted-date',
            diff: () => 3600000, // 1 hour
            duration: {
                asHours: () => 1,
                minutes: () => 0
            }
        }),
        duration: (diff) => ({
            asHours: () => 1,
            minutes: () => 0
        })
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
        mockFetch = vi.fn((url) => {
            if (url.includes('DELETE')) {
                return Promise.resolve({ ok: true })
            }
            return Promise.resolve({
                json: () => Promise.resolve({ data: mockTrip })
            })
        })
        global.fetch = mockFetch
    })

    it('redirects to /trips after successful deletion', async () => {
        const wrapper = mount(Trip, {
            global: {
                plugins: [createPinia()],
                stubs: {
                    'v-img': true,
                    'v-icon': true,
                    'v-btn': true,
                    'v-spacer': true,
                    'v-container': true,
                    'v-chip': true,
                    'v-row': true,
                    'v-col': true,
                    'v-card': true,
                    'v-card-title': true,
                    'v-divider': true,
                    'v-card-text': true,
                    'v-hover': true,
                    'v-progress-circular': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-avatar': true,
                    'v-list-item-title': true,
                    'v-list-item-subtitle': true,
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
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Verify trip loaded
        expect(wrapper.text()).toContain('Test Trip')

        // Simulate delete process
        // 1. Open dialog (setting helper ref directly since UI interaction is stubbed deeply)
        wrapper.vm.showDeleteConfirmDialog = true
        await wrapper.vm.$nextTick()

        // 2. Call confirmDelete directly
        await wrapper.vm.confirmDelete()

        // Assert fetch DELETE was called
        expect(mockFetch).toHaveBeenCalledWith('/api/trips/123', { method: 'DELETE' })

        // Assert router push was called with Correct path
        expect(mockRouterPush).toHaveBeenCalledWith('/trips')
    })
})
