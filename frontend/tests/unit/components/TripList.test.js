import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import TripList from '@/components/TripList.vue'

// Mock moment
vi.mock('moment', () => {
  const mockMoment = (date) => ({
    isValid: () => !!date && date !== 'invalid',
    format: (format) => {
      if (!date || date === 'invalid') return 'Invalid Date'
      if (format === 'DD-MM-YYYY') return '15-12-2023'
      return '2023-12-15'
    }
  })
  return { default: mockMoment }
})

// Mock vue-router
vi.mock('vue-router', () => ({
  useRoute: () => ({
    query: {}
  }),
  onBeforeRouteLeave: vi.fn()
}))

// Mock the stores completely to avoid network calls
vi.mock('@/stores/app', () => ({
  useAppStore: () => ({
    getUser: vi.fn().mockResolvedValue({}),
    user: { id: 1, name: 'Test User' }
  })
}))

// Sample trips data for testing filtering
const mockTrips = [
  {
    id: 1,
    name: 'Expedition to Great Cave',
    entrance: { name: 'Main Entrance' },
    participants: [{ id: 1, name: 'Alice Smith' }, { id: 2, name: 'Bob Jones' }],
    start_time: '2024-01-15T10:00:00Z'
  },
  {
    id: 2,
    name: 'Training Session',
    entrance: { name: 'Side Entrance' },
    participants: [{ id: 3, name: 'Charlie Brown' }],
    start_time: '2024-01-10T10:00:00Z'
  },
  {
    id: 3,
    name: 'Survey Trip',
    entrance: { name: 'Main Entrance' },
    participants: [{ id: 1, name: 'Alice Smith' }],
    start_time: '2024-01-05T10:00:00Z'
  }
]

vi.mock('@/stores/trips', () => ({
  useTripStore: () => ({
    getTrips: vi.fn().mockResolvedValue([]),
    trips: mockTrips,
    loading: false
  })
}))

describe('TripList', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('initializes with empty search', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()],
        stubs: { 'v-menu': true, 'v-icon': true }
      }
    })

    expect(wrapper.vm.search).toBe('')
  })

  it('has formatDate method that works correctly', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()],
        stubs: { 'v-menu': true, 'v-icon': true }
      }
    })

    const formattedDate = wrapper.vm.formatDate('2023-12-15T10:00:00Z')
    expect(formattedDate).toBe('15-12-2023')
  })

  it('returns "~" for invalid dates', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()],
        stubs: { 'v-menu': true, 'v-icon': true }
      }
    })

    const formattedDate = wrapper.vm.formatDate('invalid')
    expect(formattedDate).toBe('~')
  })

  it('returns "~" for null dates', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()],
        stubs: { 'v-menu': true, 'v-icon': true }
      }
    })

    const formattedDate = wrapper.vm.formatDate(null)
    expect(formattedDate).toBe('~')
  })

  it('has formatDuration method that works correctly', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()],
        stubs: { 'v-menu': true, 'v-icon': true }
      }
    })

    expect(wrapper.vm.formatDuration(30)).toBe('30m')
    expect(wrapper.vm.formatDuration(60)).toBe('1h')
    expect(wrapper.vm.formatDuration(90)).toBe('1h 30m')
    expect(wrapper.vm.formatDuration(0)).toBe('')
    expect(wrapper.vm.formatDuration(null)).toBe('')
  })

  it('renders component without errors', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()],
        stubs: { 'v-menu': true, 'v-icon': true }
      }
    })

    expect(wrapper.exists()).toBe(true)
  })

  it('renders download trips menu item', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()],
        stubs: {
          'v-container': { template: '<div><slot></slot></div>' },
          'v-menu': { template: '<div><slot name="activator" :props="{}"></slot><slot></slot></div>' },
          'v-list': { template: '<div><slot></slot></div>' },
          'v-list-item': { template: '<div class="v-list-item" :href="href"><slot></slot></div>', props: ['href'] },
          'v-list-item-title': true,
          'v-btn': true,
          'v-icon': true
        }
      }
    })

    const downloadItem = wrapper.findAll('.v-list-item').find(item => item.attributes('href') === '/api/me/trips/download')
    expect(downloadItem).toBeDefined()
    expect(downloadItem.exists()).toBe(true)
  })

  // Regression tests for search filtering
  describe('search filtering', () => {
    it('returns all trips when search is empty', () => {
      const wrapper = mount(TripList, {
        global: {
          plugins: [createPinia()],
          stubs: { 'v-menu': true, 'v-icon': true }
        }
      })

      wrapper.vm.search = ''
      expect(wrapper.vm.filteredTrips).toHaveLength(3)
    })

    it('filters trips by name (case-insensitive)', () => {
      const wrapper = mount(TripList, {
        global: {
          plugins: [createPinia()],
          stubs: { 'v-menu': true, 'v-icon': true }
        }
      })

      wrapper.vm.search = 'expedition'
      expect(wrapper.vm.filteredTrips).toHaveLength(1)
      expect(wrapper.vm.filteredTrips[0].name).toBe('Expedition to Great Cave')
    })

    it('filters trips by entrance name', () => {
      const wrapper = mount(TripList, {
        global: {
          plugins: [createPinia()],
          stubs: { 'v-menu': true, 'v-icon': true }
        }
      })

      wrapper.vm.search = 'Main Entrance'
      expect(wrapper.vm.filteredTrips).toHaveLength(2)
    })

    it('filters trips by participant name', () => {
      const wrapper = mount(TripList, {
        global: {
          plugins: [createPinia()],
          stubs: { 'v-menu': true, 'v-icon': true }
        }
      })

      wrapper.vm.search = 'Charlie'
      expect(wrapper.vm.filteredTrips).toHaveLength(1)
      expect(wrapper.vm.filteredTrips[0].name).toBe('Training Session')
    })

    it('returns empty array when no trips match the search', () => {
      const wrapper = mount(TripList, {
        global: {
          plugins: [createPinia()],
          stubs: { 'v-menu': true, 'v-icon': true }
        }
      })

      wrapper.vm.search = 'nonexistent'
      expect(wrapper.vm.filteredTrips).toHaveLength(0)
    })

    it('ignores whitespace-only search', () => {
      const wrapper = mount(TripList, {
        global: {
          plugins: [createPinia()],
          stubs: { 'v-menu': true, 'v-icon': true }
        }
      })

      wrapper.vm.search = '   '
      expect(wrapper.vm.filteredTrips).toHaveLength(3)
    })
  })

  describe('stubbed vs detailed trips', () => {
    it('correctly identifies stubbed trips', () => {
      const wrapper = mount(TripList, {
        global: {
          plugins: [createPinia()],
          stubs: { 'v-menu': true, 'v-icon': true }
        }
      })

      // Add a stubbed trip to the mock data
      const stubbedTrip = {
        id: 4,
        name: 'Marked as Done',
        entrance: { name: 'Stubbed Entrance' },
        participants: [],
        start_time: '2024-01-20T10:00:00Z'
      }

      // We need to modify the tripStore mock or data directly if possible, 
      // but since we mocked tripStore to return a static array, we should probably update the mock for this test
      // However, we can also test the computed property logic by updating the store state if the component uses it reactively.
      // But here we mocked useTripStore to return a static object.
      // Let's rely on the fact that we can push to the array if it's the same reference, or re-mount with different mock if needed.
      // Actually, since `filteredTrips` depends on `tripStore.trips`, and `tripStore` is mocked globally...

      // Let's just test the computed logic with a new local mount if we could, 
      // but with the current setup, let's look at `wrapper.vm.tripStore.trips`.

      wrapper.vm.tripStore.trips.push(stubbedTrip)

      expect(wrapper.vm.stubbedFilteredTrips).toHaveLength(1)
      expect(wrapper.vm.stubbedFilteredTrips[0].id).toBe(4)
      expect(wrapper.vm.detailedFilteredTrips).toHaveLength(3) // Original 3 are detailed
    })
  })
})