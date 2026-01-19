import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Profile from '@/components/Profile.vue'

// Mock mande
const mockGet = vi.fn()
const mockRecentTrips = vi.fn()
vi.mock('mande', () => ({
    mande: (url) => ({
        get: () => {
            if (url.includes('/activity-heatmap')) return Promise.resolve([])
            if (url.includes('/recent-trips')) return mockRecentTrips()
            return mockGet()
        },
    })
}))

// Mock App Store
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({
        getUser: () => Promise.resolve({ id: 1, name: 'Test User' })
    })
}))

// Mock Router
vi.mock('vue-router', () => ({
    useRoute: () => ({
        params: { id: 1 }
    })
}))

// Mock Calendar Heatmap
vi.mock('vue3-calendar-heatmap', () => ({
    CalendarHeatmap: { template: '<div>Heatmap</div>' }
}))

describe('Profile.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('displays clubs card when user has clubs', async () => {
        const mockProfile = {
            id: 1,
            name: 'Test User',
            clubs: [
                { name: 'Caving Club A', slug: 'caving-club-a', is_admin: true },
                { name: 'Caving Club B', slug: 'caving-club-b', is_admin: false }
            ],
            medals: [],
            stats: { caves: 5, trips: 10, duration: 100 }
        }
        mockGet.mockResolvedValue(mockProfile)
        mockRecentTrips.mockResolvedValue([])

        const wrapper = mount(Profile, {
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-avatar': { template: '<div><slot /></div>' },
                    'v-img': { template: '<div></div>' },
                    'v-chip': { template: '<div><slot /></div>' },
                    'v-icon': { template: '<div></div>' },
                    'v-btn': { template: '<button><slot /></button>' },
                    'v-spacer': true,
                    'v-divider': true,
                    'v-list': { template: '<div><slot /></div>' },
                    'v-list-item': {
                        template: '<div :data-to="to"><slot name="prepend" /><slot /><slot name="append" /></div>',
                        props: ['to']
                    },
                    'v-list-item-title': { template: '<div><slot /></div>' },
                    'v-list-item-subtitle': { template: '<div><slot /></div>' },
                    'v-dialog': true,
                    'v-tooltip': true
                }
            }
        })

        // Wait for onMounted fetch
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Check if "Clubs" section is visible
        expect(wrapper.text()).toContain('Clubs')

        // Check if club names are displayed
        expect(wrapper.text()).toContain('Caving Club A')
        expect(wrapper.text()).toContain('Caving Club B')

        // Check if Admin badge is displayed
        expect(wrapper.text()).toContain('Admin')

        // Verify correct link URL
        const link = wrapper.find('[data-to="/club/caving-club-a"]')
        expect(link.exists()).toBe(true)
    })

    it('hides clubs card when user has no clubs', async () => {
        const mockProfile = {
            id: 1,
            name: 'Test User',
            clubs: [],
            medals: [],
            stats: { caves: 5, trips: 10, duration: 100 }
        }
        mockGet.mockResolvedValue(mockProfile)
        mockRecentTrips.mockResolvedValue([])

        const wrapper = mount(Profile, {
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-avatar': { template: '<div><slot /></div>' },
                    'v-img': { template: '<div></div>' },
                    'v-chip': { template: '<div><slot /></div>' },
                    'v-icon': { template: '<div></div>' },
                    'v-btn': { template: '<button><slot /></button>' },
                    'v-spacer': true,
                    'v-divider': true,
                    'v-list': { template: '<div><slot /></div>' },
                    'v-list-item': { template: '<div><slot /></div>' },
                    'v-list-item-title': { template: '<div><slot /></div>' },
                    'v-list-item-subtitle': { template: '<div><slot /></div>' },
                    'v-dialog': true,
                    'v-tooltip': true
                }
            }
        })

        // Wait for onMounted fetch
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Check if "Clubs" section is NOT visible
        expect(wrapper.text()).not.toContain('Clubs')
    })

    it('displays recent trips with entrance name', async () => {
        const mockProfile = {
            id: 1,
            name: 'Test User',
            clubs: [],
            medals: [],
            stats: { caves: 5, trips: 10, duration: 100 }
        }

        const trips = [
            {
                id: 101,
                name: 'Cool Trip',
                start_time: '2025-01-01T10:00:00Z',
                entrance: { name: 'Mystery Cave Entrance' }
            }
        ]

        mockGet.mockResolvedValue(mockProfile)
        mockRecentTrips.mockResolvedValue(trips)

        const wrapper = mount(Profile, {
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-avatar': { template: '<div><slot /></div>' },
                    'v-img': { template: '<div></div>' },
                    'v-chip': { template: '<div><slot /></div>' },
                    'v-icon': { template: '<div></div>' },
                    'v-btn': { template: '<button><slot /></button>' },
                    'v-spacer': true,
                    'v-divider': true,
                    'v-list': { template: '<div><slot /></div>' },
                    'v-list-item': {
                        template: '<div><slot name="prepend" /><slot /><slot name="append" /></div>'
                    },
                    'v-list-item-title': { template: '<div><slot /></div>' },
                    'v-list-item-subtitle': { template: '<div><slot /></div>' },
                    'v-dialog': true,
                    'v-tooltip': true
                }
            }
        })

        // Wait for onMounted fetch
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Check recent trips section
        expect(wrapper.text()).toContain('Recent Trips')
        expect(wrapper.text()).toContain('Cool Trip')
        // This is the key assertion for the fix
        expect(wrapper.text()).toContain('Mystery Cave Entrance')
    })
})
