import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import TripNew from '@/components/TripNew.vue'
import { nextTick } from 'vue'

// Mock moment
vi.mock('moment', () => {
  const mockMoment = (date) => {
    if (!date) {
      // Current time for default values
      return {
        format: (format) => {
          if (format === 'YYYY-MM-DD') return '2024-01-15'
          if (format === 'HH:mm') return '10:00'
          return '2024-01-15T10:00:00'
        },
        clone: function() { return this },
        add: function() { return this }
      }
    }
    
    // Parse specific test dates - create a moment-like object
    const testDate = new Date(date)
    const momentObj = {
      _date: testDate,
      format: (format) => {
        if (format === 'YYYY-MM-DD') return testDate.toISOString().split('T')[0]
        if (format === 'HH:mm') return testDate.toTimeString().split(' ')[0].substring(0, 5)
        return testDate.toISOString()
      },
      diff: (otherMoment, unit) => {
        if (unit === 'minutes') {
          let otherDate
          if (typeof otherMoment === 'string') {
            otherDate = new Date(otherMoment)
          } else if (otherMoment._date) {
            otherDate = otherMoment._date
          } else {
            otherDate = new Date(otherMoment)
          }
          return Math.floor((testDate - otherDate) / (1000 * 60))
        }
        return 0
      },
      clone: function() { return this },
      add: function() { return this }
    }
    
    return momentObj
  }
  
  return { default: mockMoment }
})

// Mock router
const mockRoute = {
  params: { id: '123' },
  query: {}
}

const mockRouter = {
  push: vi.fn()
}

vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  useRoute: () => mockRoute
}))

// Mock fetch globally
global.fetch = vi.fn()

describe('TripNew - Duration Loading', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    
    // Mock successful API responses
    fetch.mockImplementation((url) => {
      if (url === '/api/caves') {
        return Promise.resolve({
          json: () => Promise.resolve({
            data: [
              { id: 1, name: 'Test Cave', system: { id: 1 }, location_name: 'Test Location', location_country: 'Test Country' }
            ]
          })
        })
      }
      
      if (url === '/api/users/me') {
        return Promise.resolve({
          json: () => Promise.resolve({
            data: { id: 1 }
          })
        })
      }
      
      if (url === '/api/users') {
        return Promise.resolve({
          json: () => Promise.resolve({
            data: [
              { id: 1, name: 'Test User', photo: null, club: 'Test Club' }
            ]
          })
        })
      }
      
      if (url === '/api/trips/123') {
        return Promise.resolve({
          json: () => Promise.resolve({
            data: {
              id: 123,
              name: 'Test Trip',
              description: 'Test Description',
              start_time: '2024-01-15T10:00:00Z',
              end_time: '2024-01-15T11:30:00Z', // 1 hour 30 minutes duration
              entrance: { id: 1, name: 'Test Entrance' },
              exit: { id: 1, name: 'Test Exit' },
              system: { id: 1, name: 'Test System' },
              participants: [{ id: 1, name: 'Test User' }],
              media: [],
              visibility: 'public'
            }
          })
        })
      }
      
      return Promise.reject(new Error('Unknown URL'))
    })
  })

  it('correctly calculates and sets duration when loading existing trip', async () => {
    const wrapper = mount(TripNew, {
      global: {
        plugins: [createPinia()],
        stubs: {
          'v-container': { template: '<div><slot /></div>' },
          'v-card': { template: '<div><slot /></div>' },
          'v-progress-circular': { template: '<div>Loading...</div>' },
          'v-form': { template: '<div><slot /></div>' },
          'v-stepper': { template: '<div><slot /></div>' },
          'v-autocomplete': { template: '<div></div>' },
          'v-checkbox': { template: '<div></div>' },
          'v-text-field': { template: '<div></div>' },
          'v-select': { template: '<div></div>' },
          'v-file-input': { template: '<div></div>' },
          'v-btn': { template: '<div><slot /></div>' },
          'v-row': { template: '<div><slot /></div>' },
          'v-col': { template: '<div><slot /></div>' },
          'VuetifyTiptap': { template: '<div></div>' },
          'AddParticipantManual': { template: '<div></div>' }
        }
      }
    })

    // Wait for the component to finish loading
    await nextTick()
    await new Promise(resolve => setTimeout(resolve, 100))
    
    // The loading should be complete and duration should be calculated
    expect(wrapper.vm.loading).toBe(false)
    
    // Check that duration was correctly calculated from the mock data
    // start_time: '2024-01-15T10:00:00Z'
    // end_time: '2024-01-15T11:30:00Z'
    // Duration should be 1 hour 30 minutes
    expect(wrapper.vm.tripDurationHours).toBe(1)
    expect(wrapper.vm.tripDurationMinutes).toBe(30)
    
    // Also verify that start date and time were set correctly
    expect(wrapper.vm.tripStartDate).toBe('2024-01-15')
    expect(wrapper.vm.tripStartTime).toBe('10:00')
  })

  it('sets default duration values when creating new trip', async () => {
    // Override route to simulate creating new trip (no ID)
    mockRoute.params = {}
    
    const wrapper = mount(TripNew, {
      global: {
        plugins: [createPinia()],
        stubs: {
          'v-container': { template: '<div><slot /></div>' },
          'v-card': { template: '<div><slot /></div>' },
          'v-progress-circular': { template: '<div>Loading...</div>' },
          'v-form': { template: '<div><slot /></div>' },
          'v-stepper': { template: '<div><slot /></div>' },
          'v-autocomplete': { template: '<div></div>' },
          'v-checkbox': { template: '<div></div>' },
          'v-text-field': { template: '<div></div>' },
          'v-select': { template: '<div></div>' },
          'v-file-input': { template: '<div></div>' },
          'v-btn': { template: '<div><slot /></div>' },
          'v-row': { template: '<div><slot /></div>' },
          'v-col': { template: '<div><slot /></div>' },
          'VuetifyTiptap': { template: '<div></div>' },
          'AddParticipantManual': { template: '<div></div>' }
        }
      }
    })

    // Wait for the component to finish loading
    await nextTick()
    await new Promise(resolve => setTimeout(resolve, 100))
    
    // The loading should be complete and default duration should be set
    expect(wrapper.vm.loading).toBe(false)
    
    // Check that default duration values are used for new trips
    expect(wrapper.vm.tripDurationHours).toBe(4)
    expect(wrapper.vm.tripDurationMinutes).toBe(0)
  })
})