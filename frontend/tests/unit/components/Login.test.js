import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Login from '@/components/Login.vue'

const pushMock = vi.fn()

// Mock api plugin
vi.mock('@/plugins/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    }
}))

// Mock vue-router
vi.mock('vue-router', () => ({
    useRouter: () => ({ push: vi.fn() }),
    useRoute: () => ({ query: {} }),
    onBeforeRouteLeave: vi.fn()
}))

// Mock Pinia store
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({})
}))

describe('Login Component', () => {
    it('renders correctly', () => {
        const wrapper = mount(Login, {
            global: {
                stubs: {
                    'v-container': { template: '<div class="v-container"><slot /></div>' },
                    'v-row': { template: '<div class="v-row"><slot /></div>' },
                    'v-col': { template: '<div class="v-col"><slot /></div>' },
                    'v-img': true,
                    'v-btn': true,
                    'v-divider': true,
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-card-title': { template: '<div class="v-card-title"><slot /></div>' },
                    'v-card-text': { template: '<div class="v-card-text"><slot /></div>' },
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                    'v-form': { template: '<form><slot /></form>' },
                    'v-text-field': true,
                    'v-icon': true,
                    'v-avatar': true
                }
            }
        })
        expect(wrapper.text()).toContain('Subterra')
        expect(wrapper.text()).toContain('Explore the') // Hero text
        expect(wrapper.text()).toContain('Depths Together')
    })
})
