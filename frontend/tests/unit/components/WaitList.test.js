import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import WaitList from '@/components/WaitList.vue'

// Mock fetch globally
global.fetch = vi.fn()

describe('WaitList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders component correctly', () => {
    // Mock successful fetch
    fetch.mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve({
        data: { clubs: [] }
      })
    })

    const wrapper = mount(WaitList)
    
    expect(wrapper.exists()).toBe(true)
  })

  it('initializes with empty pendingClubs array', () => {
    fetch.mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve({
        data: { clubs: [] }
      })
    })

    const wrapper = mount(WaitList)
    
    expect(wrapper.vm.pendingClubs).toEqual([])
  })

  it('has fetchPendingClubs method', () => {
    const wrapper = mount(WaitList)
    
    expect(typeof wrapper.vm.fetchPendingClubs).toBe('function')
  })

  it('handles component mounting', () => {
    fetch.mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve({
        data: { clubs: [] }
      })
    })

    const wrapper = mount(WaitList)
    
    // Component should mount without errors
    expect(wrapper.vm.$el).toBeDefined()
  })
})