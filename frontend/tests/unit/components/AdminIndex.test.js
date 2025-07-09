import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminIndex from '@/pages/admin/index.vue'

describe('Admin Dashboard', () => {
  it('renders correctly', () => {
    const wrapper = mount(AdminIndex, {
      global: {
        stubs: {
          'v-container': true,
          'v-row': true,
          'v-col': true,
          'v-list': true,
          'v-list-item': {
            template: '<div><slot /></div>',
            props: ['prepend-icon', 'title', 'subtitle', 'to', 'link']
          }
        }
      }
    })
    
    expect(wrapper.html()).toContain('Admin Dashboard')
    expect(wrapper.html()).toContain('Select an administrative task below.')
  })

  it('displays navigation links with correct routes', () => {
    const wrapper = mount(AdminIndex, {
      global: {
        stubs: {
          'v-container': true,
          'v-row': true,
          'v-col': true,
          'v-list': true,
          'v-list-item': {
            template: '<div data-testid="list-item" :data-to="to" :data-title="title"><slot /></div>',
            props: ['prepend-icon', 'title', 'subtitle', 'to', 'link']
          }
        }
      }
    })
    
    const listItems = wrapper.findAll('[data-testid="list-item"]')
    expect(listItems.length).toBe(3)
    
    // Check user administration link
    const userAdminItem = listItems.find(item => item.attributes('data-title') === 'User Administration')
    expect(userAdminItem).toBeTruthy()
    expect(userAdminItem.attributes('data-to')).toBe('/admin/users')
    
    // Check club administration link
    const clubAdminItem = listItems.find(item => item.attributes('data-title') === 'Club Administration')
    expect(clubAdminItem).toBeTruthy()
    expect(clubAdminItem.attributes('data-to')).toBe('/admin/clubs')
    
    // Check cave system link
    const caveSystemItem = listItems.find(item => item.attributes('data-title') === 'Add Cave System & Cave')
    expect(caveSystemItem).toBeTruthy()
    expect(caveSystemItem.attributes('data-to')).toBe('/admin/cave-system-with-cave')
  })
})