import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'

import SystemCaveList from '@/components/cave-systems/SystemCaveList.vue'

const slotStub = (tag = 'div') => ({ template: `<${tag}><slot /></${tag}>` })

const mountList = (caves) =>
  mount(SystemCaveList, {
    props: { caves },
    global: {
      stubs: {
        'v-card': slotStub(),
        'v-list': slotStub(),
        'v-list-item-title': slotStub('span'),
        'v-list-item-subtitle': slotStub('span'),
        'v-avatar': true,
        'v-icon': true,
        'v-divider': true,
        'v-alert': slotStub(),
        // Render as a link so we can read its href target.
        'v-list-item': {
          props: ['to'],
          template: '<a :href="to"><slot name="prepend" /><slot /><slot name="append" /></a>',
        },
      },
    },
  })

const caves = [
  { id: 1, name: 'Gaping Gill', slug: 'gaping-gill', location_name: 'Ingleborough', location_country: 'England' },
  { id: 2, name: 'Swildons Hole', slug: 'swildons-hole', location_name: 'Mendip', location_country: 'England' },
]

describe('SystemCaveList', () => {
  it('lists each cave linking to its slug page', () => {
    const wrapper = mountList(caves)
    const links = wrapper.findAll('a')
    expect(links).toHaveLength(2)
    expect(links[0].attributes('href')).toBe('/caves/gaping-gill')
    expect(links[1].attributes('href')).toBe('/caves/swildons-hole')
    expect(wrapper.text()).toContain('Gaping Gill')
    expect(wrapper.text()).toContain('Ingleborough, England')
  })

  it('shows an empty state when the system has no caves', () => {
    const wrapper = mountList([])
    expect(wrapper.findAll('a')).toHaveLength(0)
    expect(wrapper.text()).toContain('No caves in this system yet.')
  })
})
