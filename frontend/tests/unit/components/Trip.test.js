import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import Trip from '@/components/Trip.vue'
import { api } from '@/plugins/api'

// ... (existing mocks remain same)

// Mock api plugin
vi.mock('@/plugins/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    }
}))

// Mock vue-markdown-render
vi.mock('vue-markdown-render', () => ({
  default: {
    name: 'VueMarkdown',
    props: ['source'],
    template: '<div>{{ source }}</div>'
  }
}))

// Mock moment
vi.mock('moment', () => {
  const isDateValid = (date) => !!date && date !== 'invalid'
  const mockMoment = (date) => ({
    isValid: () => isDateValid(date),
    format: (format) => {
      if (!isDateValid(date)) return 'Invalid Date'
      if (format === 'ddd, D MMM YYYY') return 'Mon, 15 Dec 2023'
      if (format === 'HH:mm') return '14:30'
      return '2023-12-15'
    },
    diff: () => 4
  })
  mockMoment.duration = () => ({
    asHours: () => 4,
    minutes: () => 30
  })
  return { default: mockMoment }
})

// Mock fetch
// (replaced by api mock above)

// Mock the stores
vi.mock('@/stores/app', () => ({
  useAppStore: () => ({
    user: { id: 1 }
  })
}))

// Mock router
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn()
  }),
  useRoute: () => ({
    params: { id: '1' }
  }),
  onBeforeRouteLeave: vi.fn()
}))

const mockTrip = {
  id: 1,
  name: 'Test Trip',
  description: 'A test trip',
  start_time: '2023-12-15T14:30:00Z',
  end_time: '2023-12-15T18:30:00Z',
  entrance: { id: 1, name: 'Main Entrance', slug: 'main-entrance' },
  exit: { id: 2, name: 'Side Exit', slug: 'side-exit' },
  system: { name: 'Test System' },
  visibility: 'public',
  participants: [
    { id: 1, name: 'Test User', photo: null, clubs: [] }
  ],
  media: []
}

const getStubsConfig = () => ({
  'v-img': true,
  'v-btn': true,
  'v-container': { template: '<div><slot /></div>' },
  'v-spacer': true,
  'v-chip': true,
  'v-icon': true,
  'v-card': true,
  'v-card-title': true,
  'v-card-text': true,
  'v-divider': true,
  'v-list': true,
  'v-list-item': true,
  'v-list-item-title': true,
  'v-list-item-subtitle': true,
  'v-avatar': true,
  'v-row': true,
  'v-col': true,
  'v-dialog': true,
  'v-card-actions': true,
  'v-progress-circular': true,
  'v-hover': true,
  'vue-markdown': true
})

describe('Trip', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('formatDate handles valid dates correctly', async () => {
    api.get.mockResolvedValueOnce({ data: { data: mockTrip } })

    const wrapper = mount(Trip, {
      global: {
        plugins: [createPinia()],
        stubs: getStubsConfig()
      }
    })

    await flushPromises()

    const formattedDate = wrapper.vm.formatDate('2023-12-15T14:30:00Z')
    expect(formattedDate).toBe('Mon, 15 Dec 2023')
  })

  it('formatDate returns "-" for invalid dates', async () => {
    api.get.mockResolvedValueOnce({ data: { data: mockTrip } })

    const wrapper = mount(Trip, {
      global: {
        plugins: [createPinia()],
        stubs: getStubsConfig()
      }
    })

    await flushPromises()

    const formattedDate = wrapper.vm.formatDate('invalid')
    expect(formattedDate).toBe('-')
  })

  it('formatDate returns "-" for null dates', async () => {
    api.get.mockResolvedValueOnce({ data: { data: mockTrip } })

    const wrapper = mount(Trip, {
      global: {
        plugins: [createPinia()],
        stubs: getStubsConfig()
      }
    })

    await flushPromises()

    const formattedDate = wrapper.vm.formatDate(null)
    expect(formattedDate).toBe('-')
  })

  it('formatTime returns "-" for invalid dates', async () => {
    api.get.mockResolvedValueOnce({ data: { data: mockTrip } })

    const wrapper = mount(Trip, {
      global: {
        plugins: [createPinia()],
        stubs: getStubsConfig()
      }
    })

    await flushPromises()

    const formattedTime = wrapper.vm.formatTime(null)
    expect(formattedTime).toBe('-')
  })

  it('displays error message on 404', async () => {
    api.get.mockRejectedValueOnce({
      response: { status: 404 }
    })

    const wrapper = mount(Trip, {
      global: {
        plugins: [createPinia()],
        stubs: getStubsConfig()
      }
    })

    await flushPromises()
    expect(wrapper.text()).toContain('Trip not found')
    // Ensure loading is false (which it is if we see text)
  })

  it('displays generic error message on other errors', async () => {
    api.get.mockRejectedValueOnce({
      response: { status: 500 }
    })

    const wrapper = mount(Trip, {
      global: {
        plugins: [createPinia()],
        stubs: getStubsConfig()
      }
    })

    await flushPromises()
    expect(wrapper.text()).toContain('Failed to load trip')
  })

  describe('hero image', () => {
    const mountWith = async (trip) => {
      api.get.mockResolvedValueOnce({ data: { data: trip } })
      const wrapper = mount(Trip, {
        global: { plugins: [createPinia()], stubs: getStubsConfig() }
      })
      await flushPromises()
      return wrapper
    }

    it("prefers the trip's own first photo", async () => {
      const wrapper = await mountWith({
        ...mockTrip,
        media: [{ filename: 'a.webp', url: 'https://cdn/trip-photo.webp' }],
        entrance_hero_image: 'https://cdn/cave-hero.webp',
      })

      expect(wrapper.vm.heroImage).toBe('https://cdn/trip-photo.webp')
    })

    it("falls back to the entrance's hero photo when the trip has no photos", async () => {
      const wrapper = await mountWith({
        ...mockTrip,
        entrance_hero_image: 'https://cdn/cave-hero.webp',
        entrance_entrance_image: 'https://cdn/cave-entrance.webp',
      })

      expect(wrapper.vm.heroImage).toBe('https://cdn/cave-hero.webp')
    })

    it("falls back to the entrance photo when there is no hero photo", async () => {
      const wrapper = await mountWith({
        ...mockTrip,
        entrance_hero_image: null,
        entrance_entrance_image: 'https://cdn/cave-entrance.webp',
      })

      expect(wrapper.vm.heroImage).toBe('https://cdn/cave-entrance.webp')
    })

    it('uses the placeholder when the cave has no photos either', async () => {
      const wrapper = await mountWith({
        ...mockTrip,
        entrance_hero_image: null,
        entrance_entrance_image: null,
      })

      expect(wrapper.vm.heroImage).toBe('/placeholder-cave.jpg')
    })
  })
})

