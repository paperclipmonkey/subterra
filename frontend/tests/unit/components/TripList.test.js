import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import TripList from '@/components/TripList.vue'

// Mock moment
vi.mock('moment', () => {
  const mockMoment = (date) => ({
    isValid: () => true,
    format: (format) => {
      if (format === 'DD-MM-YYYY') return '15-12-2023'
      return '2023-12-15'
    }
  })
  return { default: mockMoment }
})

// Mock the stores completely to avoid network calls
vi.mock('@/stores/app', () => ({
  useAppStore: () => ({
    getUser: vi.fn().mockResolvedValue({})
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

  it('renders component without errors', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()],
        stubs: { 'v-menu': true, 'v-icon': true }
      }
    })

    expect(wrapper.exists()).toBe(true)
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
})