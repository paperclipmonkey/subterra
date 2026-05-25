import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Profile from '@/components/Profile.vue'

// Mock api plugin
const mockGet = vi.fn()
const mockRecentTrips = vi.fn()
vi.mock('@/plugins/api', () => ({
    api: {
        get: (url) => {
            if (url.includes('/activity-heatmap')) return Promise.resolve({ data: [] })
            if (url.includes('/recent-trips')) return mockRecentTrips()
            return mockGet()
        },
    }
}))

// Mock App Store
const mockGetUser = vi.fn()
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({
        getUser: mockGetUser
    })
}))

// Mock Router
vi.mock('vue-router', () => ({
    useRoute: () => ({
        params: { id: 1 }
    }),
    onBeforeRouteLeave: vi.fn()
}))

// Mock Calendar Heatmap
vi.mock('vue3-calendar-heatmap', () => ({
    CalendarHeatmap: { template: '<div>Heatmap</div>' }
}))

describe('Profile.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockGetUser.mockResolvedValue({ id: 1, name: 'Test User' })
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
        mockGet.mockResolvedValue({ data: mockProfile })
        mockRecentTrips.mockResolvedValue({ data: [] })

        const wrapper = mount(Profile, {
            global: {
                directives: {
                    tooltip: {}
                },
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
        mockGet.mockResolvedValue({ data: mockProfile })
        mockRecentTrips.mockResolvedValue({ data: [] })

        const wrapper = mount(Profile, {
            global: {
                directives: {
                    tooltip: {}
                },
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

        mockGet.mockResolvedValue({ data: mockProfile })
        mockRecentTrips.mockResolvedValue({ data: trips })

        const wrapper = mount(Profile, {
            global: {
                directives: {
                    tooltip: {}
                },
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
        // This is the key assertion for the fix
        expect(wrapper.text()).toContain('Mystery Cave Entrance')
    })

    it('shows medal introduction for own profile with no medals', async () => {
        const mockProfile = {
            id: 1,
            name: 'Test User',
            clubs: [],
            medals: [],
            stats: { caves: 0, trips: 0, duration: 0 }
        }
        mockGet.mockResolvedValue({ data: mockProfile })
        mockRecentTrips.mockResolvedValue({ data: [] })

        const wrapper = mount(Profile, {
            global: {
                directives: { tooltip: {} },
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

        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('Start your collection!')
        expect(wrapper.text()).toContain('Find a cave')
    })

    it('hides medal introduction for other profile with no medals', async () => {
        // Mock current user as ID 2, while profile is ID 1
        mockGetUser.mockResolvedValue({ id: 2, name: 'Other User' })

        const mockProfile = {
            id: 1,
            name: 'Test User',
            clubs: [],
            medals: [],
            stats: { caves: 0, trips: 0, duration: 0 }
        }
        mockGet.mockResolvedValue({ data: mockProfile })
        mockRecentTrips.mockResolvedValue({ data: [] })

        const wrapper = mount(Profile, {
            global: {
                directives: { tooltip: {} },
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

        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).not.toContain('Start your collection!')
        expect(wrapper.text()).not.toContain('Trophy Case')
    })

    it('hides medal introduction for own profile when medals are present', async () => {
        const mockProfile = {
            id: 1,
            name: 'Test User',
            clubs: [],
            medals: [
                { id: 1, name: 'First Trip', description: 'Awarded for completing your first trip.', image_url: 'first-trip.svg' }
            ],
            stats: { caves: 1, trips: 1, duration: 60 }
        }
        mockGet.mockResolvedValue({ data: mockProfile })
        mockRecentTrips.mockResolvedValue({ data: [] })

        const wrapper = mount(Profile, {
            global: {
                directives: { tooltip: {} },
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

        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).not.toContain('Start your collection!')
        expect(wrapper.text()).toContain('Trophy Case')
        expect(wrapper.text()).toContain('1') // Chip with medal count
    })
})
