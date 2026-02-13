import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import App from '@/App.vue'
import { createPinia, setActivePinia } from 'pinia'
import moment from 'moment'

// Mock dependencies
const mockPush = vi.fn()
vi.mock('vue-router', () => ({
    useRouter: () => ({ push: mockPush }),
    useRoute: vi.fn(() => ({ path: '/' }))
}))

const mockHideNotification = vi.fn()
vi.mock('@/stores/notifications', () => ({
    useNotificationStore: () => ({
        show: false,
        message: '',
        type: 'info',
        timeout: 3000,
        hideNotification: mockHideNotification
    })
}))

const mockAppStore = {
    user: { id: 1, active_callout: null },
    canSuggest: true
}
vi.mock('@/stores/app', () => ({
    useAppStore: () => mockAppStore
}))

vi.mock('@/components/PrivacyNotice.vue', () => ({
    default: { template: '<div class="privacy-notice"></div>' }
}))

describe('App.vue Callout Banner', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.clearAllMocks()
        mockAppStore.user.active_callout = null
    })

    it('shows the banner when an active callout exists and route is not /callout/active', async () => {
        const { useRoute } = await import('vue-router')
        useRoute.mockReturnValue({ path: '/' })

        mockAppStore.user.active_callout = { callout_time: moment().add(2, 'hours').toISOString() }

        const wrapper = mount(App, {
            global: {
                stubs: {
                    'v-app': { template: '<div><slot /></div>' },
                    'v-system-bar': { template: '<div class="v-system-bar"><slot /></div>', props: ['color'] },
                    'v-icon': true,
                    'v-spacer': true,
                    'v-main': { template: '<div><slot /></div>' },
                    'router-view': true,
                    'v-system-bar': { name: 'v-system-bar', template: '<div class="v-system-bar"><slot /></div>', props: ['color'] },
                    'v-icon': true,
                    'v-spacer': true,
                    'v-main': { template: '<div><slot /></div>' },
                    'router-view': true,
                    'v-snackbar': true,
                    'PrivacyNotice': true
                }
            }
        })

        const banner = wrapper.findComponent({ name: 'v-system-bar' })
        expect(banner.exists()).toBe(true)
        expect(banner.props('color')).toBe('warning')
    })

    it('hides the banner when the route is /callout/active', async () => {
        const { useRoute } = await import('vue-router')
        useRoute.mockReturnValue({ path: '/callout/active' })

        mockAppStore.user.active_callout = { callout_time: moment().add(2, 'hours').toISOString() }

        const wrapper = mount(App, {
            global: {
                stubs: {
                    'v-app': { template: '<div><slot /></div>' },
                    'v-system-bar': { name: 'v-system-bar', template: '<div class="v-system-bar"><slot /></div>', props: ['color'] },
                    'v-icon': true,
                    'v-spacer': true,
                    'v-main': { template: '<div><slot /></div>' },
                    'router-view': true,
                    'v-snackbar': true,
                    'PrivacyNotice': true
                }
            }
        })

        const banner = wrapper.findComponent({ name: 'v-system-bar' })
        expect(banner.exists()).toBe(false)
    })

    it('shows the banner in red when callout expires in less than 60 minutes', async () => {
        const { useRoute } = await import('vue-router')
        useRoute.mockReturnValue({ path: '/' })

        mockAppStore.user.active_callout = { callout_time: moment().add(30, 'minutes').toISOString() }

        const wrapper = mount(App, {
            global: {
                stubs: {
                    'v-app': { template: '<div><slot /></div>' },
                    'v-system-bar': { name: 'v-system-bar', template: '<div class="v-system-bar"><slot /></div>', props: ['color'] },
                    'v-icon': true,
                    'v-spacer': true,
                    'v-main': { template: '<div><slot /></div>' },
                    'router-view': true,
                    'v-snackbar': true,
                    'PrivacyNotice': true
                }
            }
        })

        const banner = wrapper.findComponent({ name: 'v-system-bar' })
        expect(banner.exists()).toBe(true)
        expect(banner.props('color')).toBe('red darken-2')
    })
})
