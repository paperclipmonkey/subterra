import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CalloutIndex from '@/pages/callout/create.vue'
import axios from 'axios'

// Mock dependencies
const pushMock = vi.fn()
vi.mock('vue-router', () => ({
    useRouter: () => ({ push: pushMock }),
    onBeforeRouteLeave: vi.fn()
}))

// Mock Axios
const mockCaves = [
    { id: 1, name: 'Alum Pot', location_name: 'Yorkshire', location_country: 'United Kingdom', system: { id: 10, length: 500 } },
    { id: 2, name: 'Tiny Pot', location_name: 'Yorkshire', location_country: 'United Kingdom', system: { id: 11, length: 40 } }
]
const mockUsers = [{ id: 2, name: 'Alice' }, { id: 3, name: 'Bob' }]
const mockUserMe = { id: 1, name: 'Test User', email: 'test@example.com', clubs: [{ status: 'approved' }] }

vi.mock('axios', () => {
    const mock = {
        get: vi.fn((url) => {
            if (url === '/api/caves') return Promise.resolve({ data: { data: mockCaves } })
            if (url === '/api/users') return Promise.resolve({ data: { data: mockUsers } })
            if (url === '/api/users/me') return Promise.resolve({ data: { data: mockUserMe } })
            if (url === '/api/duty-officers/current') return Promise.resolve({ data: { data: { name: 'Officer Jenny', photo: null, is_covered: true } } })
            return Promise.resolve({ data: {} })
        }),
        post: vi.fn(() => Promise.resolve({ data: { callout: { id: 99 } } })),
        put: vi.fn(() => Promise.resolve({ data: {} })),
        delete: vi.fn(() => Promise.resolve({ data: {} })),
        patch: vi.fn(() => Promise.resolve({ data: {} })),
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

// Mock Store
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({
        getUser: vi.fn(),
        user: mockUserMe
    })
}))

describe('Callout Wizard', () => {
    beforeEach(() => {
        // Reset the default get mock between tests to avoid bleed-over
        axios.get.mockImplementation((url) => {
            if (url === '/api/caves') return Promise.resolve({ data: { data: mockCaves } })
            if (url === '/api/users') return Promise.resolve({ data: { data: mockUsers } })
            if (url === '/api/users/me') return Promise.resolve({ data: { data: mockUserMe } })
            if (url === '/api/duty-officers/current') return Promise.resolve({ data: { data: { name: 'Officer Jenny', photo: null, is_covered: true } } })
            return Promise.resolve({ data: {} })
        })
    })

    it('renders wizard and loads initial data', async () => {
        // Mock Navigator APIs
        Object.defineProperty(global.navigator, 'permissions', {
            value: {
                query: vi.fn(() => Promise.resolve({ state: 'granted' }))
            },
            writable: true
        })

        Object.defineProperty(global.navigator, 'geolocation', {
            value: {
                getCurrentPosition: vi.fn((success) => success({
                    coords: { latitude: 51.5, longitude: -0.1, accuracy: 20 },
                    timestamp: Date.now()
                }))
            },
            writable: true
        })

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
                    'v-stepper-item': { template: '<div class="v-stepper-item"><slot /></div>' },
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
                    'v-spacer': true,
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
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

        // Assert initial loading state
        expect(wrapper.text()).toContain('Loading...')

        // Wait for mounted hooks
        await flushPromises()
        await wrapper.vm.$nextTick()

        // Assert loaded
        expect(wrapper.text()).not.toContain('Loading...')

        // Check initial step (Location)
        expect(wrapper.vm.step).toBe(1)

        // Fill out Step 1
        await wrapper.setData({
            form: {
                ...wrapper.vm.form,
                cave_id: 1,
                car_registration: 'AB12 CDE',
                car_parking: 'Bull Pot Farm',
                location_data: { latitude: 51.5, longitude: -0.1 }
            }
        })
        await wrapper.vm.$nextTick()

        // Assert canProceed logic
        expect(wrapper.vm.canProceed).toBe(true)

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
            if (url === '/api/duty-officers/current') return Promise.resolve({ data: { data: { name: null, photo: null, is_covered: false } } })
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
                    'v-stepper-item': true,
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
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
                }
            }
        })

        // Wait for mounted actions
        await flushPromises()
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
                    'v-stepper-item': true,
                    'v-divider': true,
                    'v-form': true,
                    'v-window': true,
                    'v-window-item': true,
                    'v-toolbar': true,
                    'v-toolbar-title': true,
                    'v-card-text': true,
                    'v-progress-circular': true,
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
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

    it('shows warning dialog when trying to leave with incomplete form', async () => {
        const wrapper = mount(CalloutIndex, {
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-dialog': { template: '<div v-if="modelValue"><slot /></div>', props: ['modelValue'] },
                    'v-btn': true,
                    'v-icon': true,
                    'v-spacer': true,
                    'v-img': true,
                    'v-avatar': true,
                    'v-expand-transition': true,
                    'v-textarea': true,
                    'v-stepper': true,
                    'v-stepper-header': true,
                    'v-stepper-item': true,
                    'v-divider': true,
                    'v-form': true,
                    'v-window': true,
                    'v-window-item': true,
                    'v-toolbar': true,
                    'v-toolbar-title': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-card-actions': true,
                    'v-progress-circular': true,
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
                }
            }
        })

        await flushPromises()
        await wrapper.vm.$nextTick()

        // Set up incomplete form
        wrapper.vm.step = 2
        wrapper.vm.form.participants = [{ name: 'Test User', phone: '' }]

        // Simulate navigation attempt
        const next = vi.fn()
        const to = { path: '/more' }
        const from = { path: '/callout/create' }

        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, to, from, next)

        // Should show dialog and block navigation
        expect(wrapper.vm.showLeaveDialog).toBe(true)
        expect(next).toHaveBeenCalledWith(false)
    })

    it('allows navigation when form is complete', async () => {
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
                    'v-stepper-item': true,
                    'v-divider': true,
                    'v-form': true,
                    'v-window': true,
                    'v-window-item': true,
                    'v-toolbar': true,
                    'v-toolbar-title': true,
                    'v-card-text': true,
                    'v-progress-circular': true,
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
                }
            }
        })

        await flushPromises()
        await wrapper.vm.$nextTick()

        // Set allowLeave flag (as if user clicked "Leave Anyway")
        wrapper.vm.allowLeave = true

        // Simulate navigation attempt
        const next = vi.fn()
        const to = { path: '/more' }
        const from = { path: '/callout/create' }

        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, to, from, next)

        // Should allow navigation
        expect(next).toHaveBeenCalledWith()
        expect(wrapper.vm.allowLeave).toBe(false) // Should reset flag
    })

    it('stores pending route when showing leave dialog', async () => {
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
                    'v-stepper-item': true,
                    'v-divider': true,
                    'v-form': true,
                    'v-window': true,
                    'v-window-item': true,
                    'v-toolbar': true,
                    'v-toolbar-title': true,
                    'v-card-text': true,
                    'v-progress-circular': true,
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
                }
            }
        })

        await flushPromises()
        await wrapper.vm.$nextTick()

        // Incomplete form
        wrapper.vm.step = 1
        wrapper.vm.form.participants = [{ name: 'Test' }]

        // Simulate navigation to a specific route
        const next = vi.fn()
        const to = { path: '/trips', name: 'trips' }
        const from = { path: '/callout/create' }

        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, to, from, next)

        // Should store the destination route
        expect(wrapper.vm.pendingRoute).toEqual(to)
    })

    it('shows general error alert when API returns 422 with message', async () => {
        const axios = await import('axios')
        axios.default.post.mockImplementationOnce(() =>
            Promise.reject({
                response: {
                    status: 422,
                    data: {
                        message: 'Cannot create callout: No administrator is on-call'
                    }
                }
            })
        )

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
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                    'v-stepper': true,
                    'v-stepper-header': true,
                    'v-stepper-item': true,
                    'v-divider': true,
                    'v-form': { template: '<form @submit.prevent><slot /></form>' },
                    'v-window': true,
                    'v-window-item': true,
                    'v-progress-circular': true,
                    'v-avatar': true,
                    'v-img': true,
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                    'v-icon': true,
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
                }
            }
        })

        await flushPromises()

        // Ensure form is valid so it doesn't bail early
        wrapper.vm.form.participants[0].phone = '07123456789'
        wrapper.vm.form.cave_id = 1
        wrapper.vm.form.car_registration = 'AB12 CDE'
        wrapper.vm.form.car_parking = 'Bull Pot Farm'
        wrapper.vm.form.trip_plan = 'Plan'

        // Trigger submission
        await wrapper.vm.submitCallout()
        await wrapper.vm.$nextTick()

        // Expect general error alert to be visible
        expect(wrapper.vm.generalError).toBe('Cannot create callout: No administrator is on-call')

        // Find the alert in the DOM - there might be multiple alerts, check them all
        const alerts = wrapper.findAll('.v-alert')
        const alertTexts = alerts.map(a => a.text())
        expect(alertTexts).toContain('Cannot create callout: No administrator is on-call')
    })

    it('blocks progression to Step 3 if any manual guest has an invalid phone number', async () => {
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
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                    'v-stepper': true,
                    'v-stepper-header': true,
                    'v-stepper-item': true,
                    'v-divider': true,
                    'v-form': true,
                    'v-window': true,
                    'v-window-item': true,
                    'v-toolbar': true,
                    'v-toolbar-title': true,
                    'v-card-text': true,
                    'v-progress-circular': true,
                    'v-avatar': true,
                    'v-img': true,
                    'v-textarea': true,
                    'v-text-field': true,
                    'v-expand-transition': true,
                    'v-autocomplete': true,
                    'v-chip': true,
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
                }
            }
        })

        await flushPromises()
        await wrapper.vm.$nextTick()

        // Move to Step 2
        wrapper.vm.step = 2

        // Form participants array has current user added by prefillForm
        expect(wrapper.vm.form.participants.length).toBe(1)
        expect(wrapper.vm.form.participants[0].isCurrentUser).toBe(true)

        // Use setData to replace the entire participants array for proper reactivity.
        // Current user with valid phone + invalid manual guest
        await wrapper.setData({
            form: {
                ...wrapper.vm.form,
                participants: [
                    { ...wrapper.vm.form.participants[0], phone: '07123456789' },
                    {
                        local_id: 'abc', name: 'Invalid Guest', phone: '1234',
                        user_id: null, locked: false, isCurrentUser: false,
                        photo: null, clubs: [], hasPhone: false
                    }
                ]
            }
        })
        await wrapper.vm.$nextTick()

        // Should be blocked due to invalid phone!
        expect(wrapper.vm.phoneError).toBe(true)
        expect(wrapper.vm.canProceed).toBe(false)

        // Correcting the phone number — replace full array to trigger reactivity
        await wrapper.setData({
            form: {
                ...wrapper.vm.form,
                participants: [
                    wrapper.vm.form.participants[0],
                    { ...wrapper.vm.form.participants[1], phone: '07999999999' }
                ]
            }
        })
        await wrapper.vm.$nextTick()

        expect(wrapper.vm.phoneError).toBe(false)
        expect(wrapper.vm.canProceed).toBe(true)
    })

    it('allows progression when a registered user with a hidden phone is added alongside valid guests', async () => {
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
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                    'v-stepper': true,
                    'v-stepper-header': true,
                    'v-stepper-item': true,
                    'v-divider': true,
                    'v-form': true,
                    'v-window': true,
                    'v-window-item': true,
                    'v-toolbar': true,
                    'v-toolbar-title': true,
                    'v-card-text': true,
                    'v-progress-circular': true,
                    'v-avatar': true,
                    'v-img': true,
                    'v-textarea': true,
                    'v-text-field': true,
                    'v-expand-transition': true,
                    'v-autocomplete': true,
                    'v-chip': true,
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
                }
            }
        })

        await flushPromises()
        await wrapper.vm.$nextTick()

        wrapper.vm.step = 2

        // Replace participants: current user (valid phone) + registered user with hidden phone
        await wrapper.setData({
            form: {
                ...wrapper.vm.form,
                participants: [
                    { ...wrapper.vm.form.participants[0], phone: '07123456789' },
                    {
                        local_id: 'def', user_id: 2, name: 'Alice', phone: '🔒 Hidden',
                        email: 'alice@example.com', locked: true, photo: null,
                        clubs: [], hasPhone: true, isCurrentUser: false,
                    }
                ]
            }
        })
        await wrapper.vm.$nextTick()

        // Should be valid — hidden phone is skipped in validation
        expect(wrapper.vm.phoneError).toBe(false)
        expect(wrapper.vm.canProceed).toBe(true)

        // Add one more invalid manual guest by replacing array
        await wrapper.setData({
            form: {
                ...wrapper.vm.form,
                participants: [
                    ...wrapper.vm.form.participants,
                    {
                        local_id: 'ghi', name: 'Bad Guest', phone: '+44123',
                        user_id: null, locked: false, isCurrentUser: false,
                    }
                ]
            }
        })
        await wrapper.vm.$nextTick()

        expect(wrapper.vm.phoneError).toBe(true)
        expect(wrapper.vm.canProceed).toBe(false)
    })

    it('shows general error alert and blocks submission if Callout Time is set in the past', async () => {
        // Enable fake timers BEFORE mount so moment() uses the fake clock
        vi.useFakeTimers()
        const now = new Date('2025-01-01T12:00:00')
        vi.setSystemTime(now)

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
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                    'v-stepper': true,
                    'v-stepper-header': true,
                    'v-stepper-item': true,
                    'v-divider': true,
                    'v-form': true,
                    'v-window': true,
                    'v-window-item': true,
                    'v-toolbar': true,
                    'v-toolbar-title': true,
                    'v-card-text': true,
                    'v-progress-circular': true,
                    'v-avatar': true,
                    'v-img': true,
                    'v-textarea': true,
                    'v-text-field': true,
                    'v-expand-transition': true,
                    'v-autocomplete': true,
                    'v-chip': true,
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
                }
            }
        })

        await flushPromises()
        await wrapper.vm.$nextTick()

        // Clear any prior post calls from other tests
        axios.post.mockClear()

        // Set up a valid form with a callout_time in the PAST relative to our fake clock
        await wrapper.setData({
            step: 4,
            form: {
                ...wrapper.vm.form,
                cave_id: 1,
                car_registration: 'AB12 CDE',
                car_parking: 'Parking',
                trip_plan: 'Plan',
                callout_time: '2025-01-01T11:00:00',
                participants: [
                    { ...wrapper.vm.form.participants[0], phone: '07123456789' }
                ]
            }
        })
        await wrapper.vm.$nextTick()

        // Verify isFormValid rejects past time
        expect(wrapper.vm.isFormValid).toBe(false)

        await wrapper.vm.submitCallout()
        await flushPromises()
        await wrapper.vm.$nextTick()

        // Expect generalError to be set — the API should NOT have been called
        expect(wrapper.vm.generalError).toBe('Callout time must be in the future.')
        expect(axios.post).not.toHaveBeenCalled()

        vi.useRealTimers()
    })

    it('allows current user to save phone number to profile', async () => {
        const wrapper = mount(CalloutIndex, {
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': true,
                    'v-spacer': true,
                    'v-chip': true,
                    'v-avatar': true,
                    'v-img': true,
                    'v-text-field': { template: '<input @input="$emit(\'update:modelValue\', $event.target.value)" />' },
                    'v-divider': true,
                    'v-toolbar': true,
                    'v-toolbar-title': true,
                    'v-card-text': true,
                    'v-card-actions': true,
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                    'v-dialog': true,
                    'v-progress-circular': true,
                    'v-expand-transition': true,
                    'v-form': true,
                    'v-window': true,
                    'v-window-item': true,
                    'v-autocomplete': true,
                    'v-textarea': true,
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
                }
            }
        })

        await flushPromises()
        await wrapper.vm.$nextTick()

        // Setup: Step 2, current user participant without phone
        wrapper.vm.step = 2
        wrapper.vm.form.participants[0].phone = ''
        wrapper.vm.form.participants[0].hasPhone = false
        wrapper.vm.form.participants[0].locked = false

        await wrapper.vm.$nextTick()

        // Mock the PUT request
        axios.put.mockImplementationOnce(() => Promise.resolve({
            data: { data: { ...mockUserMe, phone: '07111111111' } }
        }))

        // Call the save method
        await wrapper.vm.savePhoneToProfile('07111111111', 0)
        await flushPromises()

        // Check if API was called correctly
        expect(axios.put).toHaveBeenCalledWith('/api/users/me', expect.objectContaining({
            phone: '07111111111'
        }))

        // Check if state was updated
        expect(wrapper.vm.form.participants[0].locked).toBe(true)
        expect(wrapper.vm.form.participants[0].phone).toBe('🔒 Hidden')
        expect(wrapper.vm.$toast.success).toHaveBeenCalledWith('Phone number saved to your profile.')
    })

    it('defaults entrance options to curated caves and allows toggling all caves', async () => {
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
                    'v-chip': true,
                    'v-avatar': true,
                    'v-img': true,
                    'v-text-field': true,
                    'v-divider': true,
                    'v-toolbar': true,
                    'v-toolbar-title': true,
                    'v-card-text': true,
                    'v-card-actions': true,
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                    'v-dialog': true,
                    'v-progress-circular': true,
                    'v-expand-transition': true,
                    'v-form': true,
                    'v-window': true,
                    'v-window-item': true,
                    'v-autocomplete': true,
                    'v-textarea': true,
                },
                mocks: {
                    $toast: { success: vi.fn(), error: vi.fn() }
                }
            }
        })

        await flushPromises()
        await wrapper.vm.$nextTick()

        expect(wrapper.vm.entranceCaveOptions.map(c => c.name)).toEqual(['Alum Pot'])
        wrapper.vm.toggleAllCaveChoices()
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.entranceCaveOptions.map(c => c.name)).toEqual(['Alum Pot', 'Tiny Pot'])
    })
})
