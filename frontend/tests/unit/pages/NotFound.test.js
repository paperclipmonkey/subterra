import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import NotFound from '@/pages/[...path].vue'

const stubs = {
  'v-container': { template: '<div><slot /></div>' },
  'v-row': { template: '<div><slot /></div>' },
  'v-col': { template: '<div><slot /></div>' },
  'v-icon': { template: '<i />' },
  'v-btn': { props: ['to'], template: '<a :href="to"><slot /></a>' },
}

describe('[...path].vue', () => {
  it('explains the page is missing and links home', () => {
    const wrapper = mount(NotFound, { global: { stubs } })

    expect(wrapper.text()).toContain('Page not found')
    expect(wrapper.find('a').attributes('href')).toBe('/')
  })
})
