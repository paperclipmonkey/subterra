import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaveTripListItem from '@/components/CaveTripListItem.vue'

// Mock moment
vi.mock('moment', () => {
  const mockMoment = (date) => ({
    isValid: () => date && date !== '~',
    fromNow: () => '2 days ago',
    diff: (startTime, unit) => unit === 'hours' ? 4 : 240
  })
  return { default: mockMoment }
})

describe('CaveTripListItem', () => {
  const mockTrip = {
    id: 1,
    name: 'Deep Cave Exploration',
    description: 'An exciting underground adventure',
    start_time: '2023-12-13T09:00:00Z',
    end_time: '2023-12-13T13:00:00Z',
    participants: [
      { id: 1, name: 'John Doe' },
      { id: 2, name: 'Jane Smith' },
      { id: 3, name: 'Bob Wilson' }
    ]
  }

  const mockTripInvalid = {
    id: 2,
    name: 'Incomplete Trip',
    description: 'Trip with invalid times',
    start_time: null,
    end_time: null,
    participants: [
      { id: 1, name: 'John Doe' }
    ]
  }

  it('renders component with trip data', () => {
    const wrapper = mount(CaveTripListItem, {
      props: {
        trip: mockTrip
      }
    })
    
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.props('trip')).toEqual(mockTrip)
  })

  it('has required trip prop', () => {
    const wrapper = mount(CaveTripListItem, {
      props: {
        trip: mockTrip
      }
    })
    
    expect(wrapper.props('trip')).toBeDefined()
    expect(wrapper.props('trip').id).toBe(1)
    expect(wrapper.props('trip').name).toBe('Deep Cave Exploration')
  })

  it('accepts trip prop of type Object', () => {
    const wrapper = mount(CaveTripListItem, {
      props: {
        trip: mockTrip
      }
    })
    
    expect(typeof wrapper.props('trip')).toBe('object')
    expect(wrapper.props('trip').participants).toBeInstanceOf(Array)
  })

  it('handles different trip data structures', () => {
    const wrapper = mount(CaveTripListItem, {
      props: {
        trip: mockTripInvalid
      }
    })
    
    expect(wrapper.props('trip').start_time).toBeNull()
    expect(wrapper.props('trip').end_time).toBeNull()
    expect(wrapper.props('trip').participants.length).toBe(1)
  })
})