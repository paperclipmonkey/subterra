import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CalloutIndex from '@/pages/callout/index.vue'
import { api } from '@/plugins/api'

// Mock dependencies
vi.mock('vue-router', () => ({
    useRouter: () => ({ push: vi.fn() }),
    onBeforeRouteLeave: vi.fn()
}))

// Mock data
const mockActiveCallouts = []
// Use a dynamic date that's always in the future
const mockDutyOfficer = {
    name: 'Officer Jenny',
    photo: null,
    next_gap_start: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
    is_covered: true
}

// Mock api plugin
vi.mock('@/plugins/api', () => ({
    api: {
        get: vi.fn((url) => {
            if (url === '/api/callouts/active') return Promise.resolve({ data: { data: [] } })
            if (url === '/api/duty-officers/current') return Promise.resolve({ data: { data: null } })
            return Promise.resolve({ data: {} })
        }),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    }
}))

// Mock Store
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({
        getUser: vi.fn(),
        user: { id: 1, name: 'Test User', active_callout: null, phone: '+447700900000', phone_verified: true }
    })
}))

// Mock ActiveCalloutMap component
vi.mock('@/components/ActiveCalloutMap.vue', () => ({
    default: { template: '<div class="active-callout-map"></div>' }
}))

// Shared stub configuration
const getStubConfig = () => ({
    'v-container': { template: '<div><slot /></div>' },
    'v-row': { template: '<div><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    'v-card': { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    'v-icon': true,
    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
    'v-btn': { template: '<button :disabled="disabled" :to="to"><slot /></button>', props: ['disabled', 'to'] },
    'v-avatar': { template: '<div><slot /></div>' },
    'v-img': true,
    'v-expansion-panels': { template: '<div><slot /></div>' },
    'v-expansion-panel': { template: '<div><slot /></div>' },
    'v-expansion-panel-title': { template: '<div><slot /></div>' },
    'v-expansion-panel-text': { template: '<div><slot /></div>' },
    'ActiveCalloutMap': { template: '<div class="active-callout-map"></div>' },
    'PhoneVerify': true
})

describe('Callout Index Page', () => {
    it('enables START CALLOUT button when duty officer is on call', async () => {
        // Reset mock to default behavior
        api.get.mockImplementation((url) => {
            if (url === '/api/callouts/active') return Promise.resolve({ data: { data: mockActiveCallouts } })
            if (url === '/api/duty-officers/current') return Promise.resolve({ data: { data: mockDutyOfficer } })
            return Promise.resolve({ data: {} })
        })

        const wrapper = mount(CalloutIndex, {
            global: {
                stubs: getStubConfig()
            }
        })

        // Wait for all promises to resolve
        await flushPromises()
        await wrapper.vm.$nextTick()

        // Verify duty officer is loaded
        expect(wrapper.vm.onCallOfficer).toBeTruthy()
        expect(wrapper.vm.onCallOfficer.name).toBe('Officer Jenny')

        // Verify the START CALLOUT button is NOT disabled
        const buttons = wrapper.findAll('button')
        const startButton = buttons.find(b => b.text().includes('START CALLOUT'))
        expect(startButton).toBeTruthy()
        expect(startButton.attributes('disabled')).toBeUndefined()
        expect(startButton.attributes('to')).toBe('/callout/create')
    })

    it('disables START CALLOUT button when no duty officer is on call', async () => {
        // Override api mock to return no duty officer
        api.get.mockImplementation((url) => {
            if (url === '/api/callouts/active') return Promise.resolve({ data: { data: mockActiveCallouts } })
            if (url === '/api/duty-officers/current') return Promise.resolve({ data: { data: { name: null, photo: null, is_covered: false } } })
            return Promise.resolve({ data: {} })
        })

        const wrapper = mount(CalloutIndex, {
            global: {
                stubs: getStubConfig()
            }
        })

        // Wait for all promises to resolve
        await flushPromises()
        await wrapper.vm.$nextTick()

        // Verify no duty officer is loaded
        expect(wrapper.vm.onCallOfficer).toBeNull()

        // Verify button IS disabled
        const buttons = wrapper.findAll('button')
        const startButton = buttons.find(b => b.text().includes('START CALLOUT'))
        expect(startButton).toBeTruthy()
        expect(startButton.attributes('disabled')).toBeDefined()

        // Verify warning alert is shown
        expect(wrapper.text()).toContain('No Officer On Call')
        expect(wrapper.text()).toContain('Callouts cannot be created at this time')
    })

    it('displays "No Officer On Call" message without using "unmonitored"', async () => {
        // Override api mock to return no duty officer
        api.get.mockImplementation((url) => {
            if (url === '/api/callouts/active') return Promise.resolve({ data: { data: mockActiveCallouts } })
            if (url === '/api/duty-officers/current') return Promise.resolve({ data: { data: { name: null, photo: null, is_covered: false } } })
            return Promise.resolve({ data: {} })
        })

        const wrapper = mount(CalloutIndex, {
            global: {
                stubs: getStubConfig()
            }
        })

        // Wait for all promises to resolve
        await flushPromises()
        await wrapper.vm.$nextTick()

        // Verify messaging doesn't use "unmonitored"
        expect(wrapper.text()).toContain('No Officer On Call')
        expect(wrapper.text()).not.toContain('Unmonitored')
        expect(wrapper.text()).not.toContain('unmonitored')

        // Verify it shows the new messaging
        expect(wrapper.text()).toContain('Callouts cannot be created at this time')
    })
})
