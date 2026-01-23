import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CalloutIndex from '@/pages/callout/index.vue'

// Mock dependencies
vi.mock('vue-router', () => ({
    useRouter: () => ({ push: vi.fn() })
}))

// Mock data
const mockActiveCallouts = []
// Use a dynamic date that's always in the future
const mockDutyOfficer = { 
    name: 'Officer Jenny', 
    photo: null, 
    next_gap_start: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString() 
}

// Mock Axios
vi.mock('axios', () => ({
    default: {
        get: vi.fn((url) => {
            if (url === '/api/callouts/active') return Promise.resolve({ data: { data: mockActiveCallouts } })
            if (url === '/api/duty-officers/current') return Promise.resolve({ data: { data: mockDutyOfficer } })
            return Promise.resolve({ data: {} })
        })
    }
}))

// Mock Store
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({
        getUser: vi.fn(),
        user: { id: 1, name: 'Test User', active_callout: null }
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
    'ActiveCalloutMap': { template: '<div class="active-callout-map"></div>' }
})

describe('Callout Index Page', () => {
    it('enables START CALLOUT button when duty officer is on call', async () => {
        const axios = await import('axios')
        // Reset mock to default behavior
        axios.default.get.mockImplementation((url) => {
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

        // Verify button is NOT disabled
        const button = wrapper.find('button')
        expect(button.attributes('disabled')).toBeUndefined()
        expect(button.attributes('to')).toBe('/callout/create')
    })

    it('disables START CALLOUT button when no duty officer is on call', async () => {
        const axios = await import('axios')
        // Override axios mock to return 404 for duty officer
        axios.default.get.mockImplementation((url) => {
            if (url === '/api/callouts/active') return Promise.resolve({ data: { data: mockActiveCallouts } })
            if (url === '/api/duty-officers/current') return Promise.reject({ response: { status: 404 } })
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
        expect(wrapper.text()).toContain('Callouts Not Available')
        expect(wrapper.text()).toContain('There is no Duty Officer on call at this time')
    })

    it('displays "No Officer On Call" message without using "unmonitored"', async () => {
        const axios = await import('axios')
        // Override axios mock to return 404 for duty officer
        axios.default.get.mockImplementation((url) => {
            if (url === '/api/callouts/active') return Promise.resolve({ data: { data: mockActiveCallouts } })
            if (url === '/api/duty-officers/current') return Promise.reject({ response: { status: 404 } })
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
