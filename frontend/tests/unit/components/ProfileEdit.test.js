import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfileEdit from '@/components/ProfileEdit.vue'

// Mock API
const mockGet = vi.fn()
const mockPut = vi.fn()

vi.mock('@/plugins/api', () => ({
    api: {
        get: (...args) => mockGet(...args),
        put: (...args) => mockPut(...args),
    }
}))

// Mock toast
vi.mock('vue-toastification', () => ({
    useToast: () => ({
        success: vi.fn(),
        error: vi.fn(),
    })
}))

// Mock router
const mockPush = vi.fn()
vi.mock('@/router', () => ({
    default: { push: (...args) => mockPush(...args) }
}))

vi.mock('vue-router', () => ({
    useRoute: () => ({ params: { id: 1 } }),
}))

// Mock composable
vi.mock('@/composables/useFormErrors', () => ({
    useFormErrors: () => ({
        setErrors: vi.fn(),
        clearErrors: vi.fn(),
        errorMessages: () => [],
    })
}))

const mountOptions = {
    global: {
        stubs: {
            'v-container': { template: '<div><slot /></div>' },
            'v-card': { template: '<div><slot /></div>' },
            'v-card-title': { template: '<div><slot /></div>' },
            'v-card-actions': { template: '<div><slot /></div>' },
            'v-card-text': { template: '<div><slot /></div>' },
            'v-divider': true,
            'v-avatar': { template: '<div><slot /></div>' },
            'v-icon': { template: '<span></span>' },
            'v-text-field': { template: '<div><input /><div class="hint">{{ hint }}</div></div>', props: ['modelValue', 'hint', 'persistentHint', 'label', 'rules', 'errorMessages', 'required'] },
            'v-textarea': { template: '<textarea />', props: ['modelValue'] },
            'v-list': { template: '<div><slot /></div>' },
            'v-list-item': { template: '<div><slot /></div>' },
            'v-chip': { template: '<span><slot /></span>' },
            'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>', emits: ['click'], props: ['loading', 'color', 'variant', 'disabled'] },
            'v-btn-toggle': { template: '<div><slot /></div>', props: ['modelValue'] },
            'v-switch': { template: '<input type="checkbox" />', props: ['modelValue'] },
            'v-spacer': true,
            'v-dialog': { template: '<div><slot /></div>', props: ['modelValue'] },
            'v-autocomplete': { template: '<div></div>', props: ['modelValue'] },
        }
    }
}

describe('ProfileEdit.vue', () => {
    const mockProfile = {
        id: 1,
        name: 'John Smith',
        photo: '',
        bio: '',
        phone: '',
        clubs: [],
        email_trophies: true,
        email_tagged: true,
        email_platform_news: true,
        visibility_addable: 'public',
    }

    beforeEach(() => {
        vi.clearAllMocks()
        // Return different shapes depending on the endpoint
        mockGet.mockImplementation((url) => {
            if (url === '/api/clubs') return Promise.resolve({ data: { data: [] } })
            return Promise.resolve({ data: { data: { ...mockProfile } } })
        })
        mockPut.mockResolvedValue({ data: { data: { name: 'John Smith', bio: '', phone: '' } } })
    })

    describe('nameRules validation', () => {
        it('rejects a name with no space (single word)', () => {
            const wrapper = mount(ProfileEdit, mountOptions)
            const nameRules = wrapper.vm.nameRules

            const results = nameRules.map(rule => rule('Jane'))
            expect(results).toContain('Please enter your full name (first and last name)')
        })

        it('accepts a name with first and last name', () => {
            const wrapper = mount(ProfileEdit, mountOptions)
            const nameRules = wrapper.vm.nameRules

            const results = nameRules.map(rule => rule('Jane Doe'))
            expect(results.every(r => r === true)).toBe(true)
        })

        it('rejects an empty name', () => {
            const wrapper = mount(ProfileEdit, mountOptions)
            const nameRules = wrapper.vm.nameRules

            const results = nameRules.map(rule => rule(''))
            expect(results).toContain('Name is required')
        })

        it('rejects a name that is too short', () => {
            const wrapper = mount(ProfileEdit, mountOptions)
            const nameRules = wrapper.vm.nameRules

            const results = nameRules.map(rule => rule('A B'))
            expect(results).toContain('Name must be at least 4 characters')
        })

        it('rejects a name that exceeds 100 characters', () => {
            const wrapper = mount(ProfileEdit, mountOptions)
            const nameRules = wrapper.vm.nameRules

            const longName = 'A'.repeat(51) + ' ' + 'B'.repeat(51) // 103 chars
            const results = nameRules.map(rule => rule(longName))
            expect(results).toContain('Name must be less than 100 characters')
        })
    })

    it('renders the cave rescue legal name note', () => {
        const wrapper = mount(ProfileEdit, mountOptions)
        expect(wrapper.text()).toContain('cave rescue')
        expect(wrapper.text()).toContain('legal first and last name')
    })
})
