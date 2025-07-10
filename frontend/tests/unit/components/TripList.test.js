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

vi.mock('@/stores/trips', () => ({
  useTripStore: () => ({
    getTrips: vi.fn().mockResolvedValue([]),
    trips: [],
    loading: false
  })
}))

describe('TripList', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('initializes with correct data', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()]
      }
    })
    
    expect(wrapper.vm.search).toBe('')
    expect(Array.isArray(wrapper.vm.headers)).toBe(true)
    expect(wrapper.vm.headers.length).toBeGreaterThan(0)
  })

  it('has formatDate method that works correctly', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()]
      }
    })
    
    const formattedDate = wrapper.vm.formatDate('2023-12-15T10:00:00Z')
    expect(formattedDate).toBe('15-12-2023')
  })

  it('has proper table headers configuration', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()]
      }
    })
    
    const expectedHeaders = ['Name', 'Date', 'entrance', 'participants']
    const actualHeaders = wrapper.vm.headers.map(h => h.title)
    
    expect(actualHeaders).toEqual(expectedHeaders)
  })

  it('renders component without errors', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()]
      }
    })
    
    expect(wrapper.exists()).toBe(true)
  })
})