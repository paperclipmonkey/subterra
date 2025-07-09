import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import TripList from '@/components/TripList.vue'
import { useAppStore } from '@/stores/app'
import { useTripStore } from '@/stores/trips'

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

describe('TripList', () => {
  let appStore
  let tripStore

  beforeEach(() => {
    setActivePinia(createPinia())
    appStore = useAppStore()
    tripStore = useTripStore()
    
    // Mock store methods
    vi.spyOn(appStore, 'getUser').mockResolvedValue({})
    vi.spyOn(tripStore, 'getTrips').mockResolvedValue([])
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

  it('accesses trip store correctly', () => {
    const wrapper = mount(TripList, {
      global: {
        plugins: [createPinia()]
      }
    })
    
    expect(wrapper.vm.tripStore).toBeDefined()
    expect(wrapper.vm.store).toBeDefined()
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
})