import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminIndex from '@/pages/admin/index.vue'

describe('Admin Dashboard', () => {
  it('renders correctly', () => {
    const wrapper = mount(AdminIndex, {
      global: {
        stubs: {
          'v-container': { template: '<div class="v-container"><slot /></div>' },
          'v-row': { template: '<div class="v-row"><slot /></div>' },
          'v-col': { template: '<div class="v-col"><slot /></div>' },
          'v-list': { template: '<div class="v-list"><slot /></div>' },
          'v-list-item': { template: '<div class="v-list-item"><slot /></div>' }
        }
      }
    })
    
    expect(wrapper.html()).toContain('Admin Dashboard')
    expect(wrapper.html()).toContain('Select an administrative task below.')
  })

  it('has the expected component structure', () => {
    const wrapper = mount(AdminIndex)
    
    // Component should mount without errors
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.vm).toBeDefined()
  })
})