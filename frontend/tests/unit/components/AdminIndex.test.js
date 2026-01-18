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
          'v-list-item': { template: '<div class="v-list-item"><slot /></div>' },
          'v-alert': { template: '<div class="v-alert"><slot /></div>' },
          'v-card': { template: '<div class="v-card"><slot /></div>' },
          'v-card-title': { template: '<div class="v-card-title"><slot /></div>' },
          'v-card-text': { template: '<div class="v-card-text"><slot /></div>' },
          'v-icon': { template: '<div class="v-icon"><slot /></div>' },
          'v-progress-linear': true,
          'v-chip': true,
          'v-btn': true,
          'v-spacer': true
        }
      }
    })

    expect(wrapper.html()).toContain('System Status:')
    expect(wrapper.html()).toContain('Active &amp; Recent Incidents') // active & recent incidents
  })

  it('has the expected component structure', () => {
    const wrapper = mount(AdminIndex)

    // Component should mount without errors
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.vm).toBeDefined()
  })
})