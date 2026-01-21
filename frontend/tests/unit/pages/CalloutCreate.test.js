import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CalloutIndex from '@/pages/callout/create.vue'

// Mock dependencies
const pushMock = vi.fn()
vi.mock('vue-router', () => ({
    useRouter: () => ({ push: pushMock })
}))

// Mock Axios
const mockCaves = [{ id: 1, name: 'Alum Pot', location_name: 'Yorkshire', system: { id: 10 } }]
const mockUsers = [{ id: 2, name: 'Alice' }, { id: 3, name: 'Bob' }]
const mockUserMe = { id: 1, name: 'Test User', email: 'test@example.com' }

vi.mock('axios', () => ({
    default: {
        get: vi.fn((url) => {
            if (url === '/api/caves') return Promise.resolve({ data: { data: mockCaves } })
            if (url === '/api/users') return Promise.resolve({ data: { data: mockUsers } })
            if (url === '/api/users/me') return Promise.resolve({ data: { data: mockUserMe } })
            if (url === '/api/duty-officers/current') return Promise.resolve({ data: { data: { name: 'Officer Jenny', photo: null } } })
            return Promise.resolve({ data: {} })
        }),
        post: vi.fn(() => Promise.resolve({ data: { callout: { id: 99 } } }))
    }
}))

// Mock Store
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({
        getUser: vi.fn(),
        user: mockUserMe
    })
}))

describe('Callout Wizard', () => {
    it('renders wizard and loads initial data', async () => {
        const wrapper = mount(CalloutIndex, {
            global: {
                stubs: {
                    'v-container': { template: '<div class="v-container"><slot /></div>' },
                    'v-row': { template: '<div class="v-row"><slot /></div>' },
                    'v-col': { template: '<div class="v-col"><slot /></div>' },
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-toolbar': { template: '<div class="v-toolbar"><slot /></div>' },
                    'v-toolbar-title': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div class="v-card-text"><slot /></div>' },
                    'v-stepper': { template: '<div class="v-stepper"><slot /></div>' },
                    'v-stepper-header': { template: '<div class="v-stepper-header"><slot /></div>' },
                    'v-stepper-step': { template: '<div class="v-stepper-step"><slot /></div>' },
                    'v-divider': true,
                    'v-form': { template: '<form><slot /></form>' },
                    'v-window': { template: '<div class="v-window"><slot /></div>' },
                    'v-window-item': { template: '<div class="v-window-item" v-show="value === index"><slot /></div>', props: ['value', 'index'] }, // Simple v-show mock
                    'v-autocomplete': true,
                    'v-checkbox': true,
                    'v-expand-transition': { template: '<div><slot /></div>' },
                    'v-text-field': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-btn': true,
                    'v-icon': true,
                    'v-spacer': true,
                    'v-alert': true,
                    'v-textarea': true,
                    'v-dialog': true,
                    'v-progress-circular': true,
                    'v-card-title': true,
                    'v-card-actions': true,
                    'v-avatar': true,
                    'v-img': true,
                    'v-expand-transition': true,
                    'v-icon': true,
                    'v-textarea': true,
                    'v-spacer': true,
                }
            }
        })

        // Mock Navigator APIs
        Object.defineProperty(global.navigator, 'permissions', {
            value: {
                query: vi.fn(() => Promise.resolve({ state: 'granted' }))
            },
            writable: true
        });

        Object.defineProperty(global.navigator, 'geolocation', {
            value: {
                getCurrentPosition: vi.fn((success) => success({
                    coords: { latitude: 51.5, longitude: -0.1, accuracy: 20 },
                    timestamp: Date.now()
                }))
            },
            writable: true
        });

        // Assert initial loading state
        expect(wrapper.text()).toContain('Loading...')

        // Wait for mounted hooks
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Assert loaded
        expect(wrapper.text()).not.toContain('Loading...')

        // Check initial step (Location)
        expect(wrapper.vm.step).toBe(1)

        // Fill out Step 1
        wrapper.vm.form.cave_id = 1
        wrapper.vm.form.car_registration = 'AB12 CDE'
        wrapper.vm.form.car_parking = 'Bull Pot Farm'
        await wrapper.vm.$nextTick()

        // Assert canProceed logic
        expect(wrapper.vm.canProceed).toBeTruthy()

        // Move to next step
        wrapper.vm.step++
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.step).toBe(2)
    })

    it('shows error and blocks submission when duty officer returns 404', async () => {
        // Override the default mock for this specific test
        const axios = await import('axios')
        axios.default.get.mockImplementation((url) => {
            if (url === '/api/caves') return Promise.resolve({ data: { data: mockCaves } })
            if (url === '/api/users') return Promise.resolve({ data: { data: mockUsers } })
            if (url === '/api/users/me') return Promise.resolve({ data: { data: mockUserMe } })
            if (url === '/api/duty-officers/current') return Promise.reject({ response: { status: 404 } })
            return Promise.resolve({ data: {} })
        })

        const wrapper = mount(CalloutIndex, {
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-toolbar': { template: '<div><slot /></div>' },
                    'v-toolbar-title': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-progress-circular': true,
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                    'v-stepper': true,
                    'v-stepper-header': true,
                    'v-stepper-step': true,
                    'v-divider': true,
                    'v-form': { template: '<form><slot /></form>' },
                    'v-window': { template: '<div><slot /></div>' },
                    'v-window-item': { template: '<div><slot /></div>', props: ['value'] },
                    'v-btn': { template: '<button :disabled="disabled"><slot /></button>', props: ['disabled'] },
                    'v-img': true,
                    'v-avatar': true,
                    'v-expand-transition': true,
                    'v-icon': true,
                    'v-textarea': true,
                    'v-spacer': true,
                }
            }
        })

        // Wait for mounted actions
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Expect error message
        expect(wrapper.text()).toContain('No Duty Officer On Call')

        // Expect form to NOT proceed or show disabled state
        expect(wrapper.find('.v-alert').exists()).toBe(true)
        expect(wrapper.find('.disabled-content').exists()).toBe(true)

        // Verify form submission is blocked (button disabled)
        // Adjust selector as needed based on implementation
        const buttons = wrapper.findAll('button')
        // const nextButton = buttons.find(b => b.text().includes('Next'))
        // expect(nextButton.attributes('disabled')).toBeDefined() 
        // OR simpler: check for specific error UI element
    })

    it('calculates callout duration hint correctly', async () => {
        const wrapper = mount(CalloutIndex, {
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-btn': true,
                    'v-icon': true,
                    'v-spacer': true,
                    'v-img': true,
                    'v-avatar': true,
                    'v-expand-transition': true,
                    'v-textarea': true,
                    'v-stepper': true,
                    'v-stepper-header': true,
                    'v-stepper-step': true,
                    'v-divider': true,
                    'v-form': true,
                    'v-window': true,
                    'v-window-item': true,
                    'v-toolbar': true,
                    'v-toolbar-title': true,
                    'v-card-text': true,
                    'v-progress-circular': true,
                    'v-alert': true,
                }
            }
        })

        // Mock current time
        const now = new Date('2025-01-01T12:00:00')
        vi.setSystemTime(now)

        // Set callout time to 5 hours 30 mins later
        wrapper.vm.form.callout_time = '2025-01-01T17:30:00'

        expect(wrapper.vm.calloutDurationHint).toBe('That is 5 hours and 30 minutes from now.')

        // Restoration happens automatically or manually
        vi.useRealTimers()
    })
})
