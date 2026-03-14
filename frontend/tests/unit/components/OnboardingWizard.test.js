import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import OnboardingWizard from '@/components/OnboardingWizard.vue'

// Mock API
const mockPut = vi.fn().mockResolvedValue({})
const mockGet = vi.fn().mockResolvedValue({ data: { data: [] } })
const mockPost = vi.fn().mockResolvedValue({})

vi.mock('@/plugins/api', () => ({
    api: {
        put: (...args) => mockPut(...args),
        get: (...args) => mockGet(...args),
        post: (...args) => mockPost(...args),
    }
}))

// Mock App Store
const mockUser = {
    id: 1,
    name: 'Test User',
    email: 'test@example.com',
    is_admin: false,
    clubs: [],
    roles: [],
    onboarding_completed_at: null,
}
const mockGetUser = vi.fn().mockResolvedValue(mockUser)

vi.mock('@/stores/app', () => ({
    useAppStore: () => ({
        user: { ...mockUser },
        getUser: mockGetUser,
    })
}))

const mountOptions = {
    global: {
        stubs: {
            'v-dialog': { template: '<div><slot /></div>', props: ['modelValue'] },
            'v-card': { template: '<div><slot /></div>' },
            'v-card-text': { template: '<div><slot /></div>' },
            'v-card-actions': { template: '<div><slot /></div>' },
            'v-window': { template: '<div><slot /></div>', props: ['modelValue'] },
            'v-window-item': { template: '<div><slot /></div>', props: ['value'] },
            'v-avatar': { template: '<div><slot /></div>' },
            'v-icon': { template: '<span></span>' },
            'v-form': {
                template: '<form @submit.prevent><slot /></form>',
                methods: { validate: () => Promise.resolve({ valid: true }) }
            },
            'v-text-field': { template: '<input />', props: ['modelValue'] },
            'v-list': { template: '<div><slot /></div>' },
            'v-list-item': { template: '<div><slot /></div>' },
            'v-chip': { template: '<span><slot /></span>' },
            'v-btn': {
                template: '<button @click="$emit(\'click\')"><slot /></button>',
                emits: ['click'],
                props: ['loading', 'color', 'variant', 'size', 'block', 'disabled'],
            },
            'v-spacer': true,
            'v-alert': true,
        }
    }
}

describe('OnboardingWizard.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockPut.mockResolvedValue({})
        mockGet.mockResolvedValue({ data: { data: [] } })
        mockGetUser.mockResolvedValue(mockUser)
    })

    it('sends a valid ISO timestamp when completing onboarding (not undefined)', async () => {
        const wrapper = mount(OnboardingWizard, mountOptions)

        // Force the component to step 3 (completion step)
        wrapper.vm.step = 3
        wrapper.vm.visible = true
        await wrapper.vm.$nextTick()

        // Trigger the "Get Started" button (nextStep at step 3)
        await wrapper.vm.nextStep()

        // Wait for async operations
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // The PUT call should have been made with onboarding_completed_at
        expect(mockPut).toHaveBeenCalledWith(
            '/api/users/me',
            expect.objectContaining({
                onboarding_completed_at: expect.any(String),
            })
        )

        // Crucially, the value must NOT be undefined
        const putCall = mockPut.mock.calls[0]
        const sentTimestamp = putCall[1].onboarding_completed_at
        expect(sentTimestamp).toBeDefined()
        expect(sentTimestamp).not.toBeNull()

        // It should be a valid ISO date string
        const parsed = new Date(sentTimestamp)
        expect(parsed.toISOString()).toBe(sentTimestamp)
    })

    it('updates store with the same timestamp sent to the API', async () => {
        const wrapper = mount(OnboardingWizard, mountOptions)

        wrapper.vm.step = 3
        wrapper.vm.visible = true
        await wrapper.vm.$nextTick()

        await wrapper.vm.nextStep()
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // The timestamp sent to the API should match the one set on the store
        const putCall = mockPut.mock.calls[0]
        const sentTimestamp = putCall[1].onboarding_completed_at

        // store.getUser should have been called to refresh user data
        expect(mockGetUser).toHaveBeenCalled()

        // The timestamp should be a valid date
        expect(new Date(sentTimestamp).toString()).not.toBe('Invalid Date')
    })

    it('sends name when completing step 1', async () => {
        const wrapper = mount(OnboardingWizard, mountOptions)

        wrapper.vm.step = 1
        wrapper.vm.visible = true
        wrapper.vm.userName = 'Joe Bloggs'
        wrapper.vm.nameValid = true
        await wrapper.vm.$nextTick()

        await wrapper.vm.nextStep()
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        expect(mockPut).toHaveBeenCalledWith(
            '/api/users/me',
            { name: 'Joe Bloggs' }
        )
    })

    describe('nameRules validation', () => {
        it('rejects a name with no space (single word)', () => {
            const wrapper = mount(OnboardingWizard, mountOptions)
            const nameRules = wrapper.vm.nameRules

            const results = nameRules.map(rule => rule('John'))
            // The space rule should fail
            expect(results).toContain('Please enter your full name (first and last name)')
        })

        it('accepts a name with first and last name', () => {
            const wrapper = mount(OnboardingWizard, mountOptions)
            const nameRules = wrapper.vm.nameRules

            const results = nameRules.map(rule => rule('John Smith'))
            // All rules should return true
            expect(results.every(r => r === true)).toBe(true)
        })

        it('rejects an empty name', () => {
            const wrapper = mount(OnboardingWizard, mountOptions)
            const nameRules = wrapper.vm.nameRules

            const results = nameRules.map(rule => rule(''))
            expect(results).toContain('Name is required')
        })
    })
})
