import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CalloutIndex from '@/pages/callout/index.vue'

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
                    'v-card-actions': true
                }
            }
        })

        // Assert initial loading state
        expect(wrapper.text()).toContain('Loading...')

        // Wait for mounted hooks
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Assert loaded
        expect(wrapper.text()).not.toContain('Loading...')
        expect(wrapper.text()).toContain('Safety Callout')

        // Check initial step (Location)
        expect(wrapper.vm.step).toBe(1)

        // Fill out Step 1
        wrapper.vm.form.cave_id = 1
        wrapper.vm.form.car_details = 'Red Ford'
        await wrapper.vm.$nextTick()

        // Assert canProceed logic
        expect(wrapper.vm.canProceed).toBeTruthy()

        // Move to next step
        wrapper.vm.step++
        await wrapper.vm.$nextTick()
        expect(wrapper.vm.step).toBe(2)
    })
})
